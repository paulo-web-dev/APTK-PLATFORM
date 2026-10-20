<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * produtos:aplicar-fotos — dono da ativação (e da galeria, só com manifesto
 * fundo branco). Ativo = Product::podeAtivar(): preço E foto no padrão.
 */
class ProdutosAplicarFotosTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->dir = storage_path('framework/testing/aplicar-fotos');
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function manifesto(array $entradas): string
    {
        File::put("{$this->dir}/manifesto.json", json_encode($entradas));

        return "{$this->dir}/manifesto.json";
    }

    private function produto(string $slug, bool $ativo, bool $comPreco, array $fotos = []): Product
    {
        $p = Product::create([
            'name'        => ucfirst($slug),
            'slug'        => $slug,
            'sku'         => strtoupper($slug),
            'price'       => $comPreco ? 165 : 0,
            'sizes'       => $comPreco ? ['375 ml'] : null,
            'size_prices' => $comPreco ? ['375 ml' => 165] : null,
            'stock_qty'   => 30,
            'active'      => $ativo,
        ]);
        foreach ($fotos as $i => $path) {
            ProductImage::create(['product_id' => $p->id, 'path' => $path, 'sort_order' => $i, 'is_primary' => $i === 0]);
        }

        return $p;
    }

    private function galeria(): array
    {
        return ProductImage::orderBy('id')->get()->map->getAttributes()->all();
    }

    /** Cenário: um de cada caso da regra. */
    private function cenario(): array
    {
        return [
            'liga'         => $this->produto('liga', false, true, ['products/foto-branco-liga375-frente.jpg']),
            'upload'       => $this->produto('upload', false, true, ['products/'.str_repeat('a', 40).'.jpg']),
            'sem_foto'     => $this->produto('sem-foto', true, true),
            'foto_antiga'  => $this->produto('foto-antiga', true, true, ['products/foto-antiga.jpg', 'products/foto-antiga-serve.jpg']),
            'fundo_preto'  => $this->produto('fundo-preto', true, true, ['products/fundo-preto-375ml-frente.jpg']),
            'sem_preco'    => $this->produto('sem-preco', true, false, ['products/foto-branco-x750-frente.jpg']),
            'mantem'       => $this->produto('mantem', true, true, ['products/foto-branco-m375-frente.jpg', 'products/foto-branco-m375-verso.jpg']),
        ];
    }

    public function test_sem_galeria_so_altera_active(): void
    {
        $p = $this->cenario();
        $teste = $this->produto('teste', true, true); // produto de teste de pagamento: intocado
        $galeria = $this->galeria();
        $manifesto = $this->manifesto([
            ['sku' => 'LIGA', 'produto' => 'Liga', 'tamanho' => '375ml', 'frente' => '375ML/LIGA_frente.jpg', 'verso' => null, 'tem_sku' => true, 'fundo' => 'branco'],
            ['sku' => null, 'produto' => 'Sem SKU', 'tamanho' => '375ml', 'frente' => '375ML/x.jpg', 'verso' => null, 'tem_sku' => false, 'fundo' => 'branco'],
        ]);

        $this->artisan('produtos:aplicar-fotos', ['--sem-galeria' => true, '--manifesto' => $manifesto])
            ->expectsOutputToContain('Sem SKU 375 ml')
            ->assertSuccessful();

        $ativos = Product::where('active', true)->orderBy('slug')->pluck('slug')->all();
        $this->assertSame(['liga', 'mantem', 'teste', 'upload'], $ativos);
        $this->assertTrue($teste->fresh()->active);

        // Nenhuma linha nem arquivo de imagem mudou.
        $this->assertSame($galeria, $this->galeria());
        $this->assertSame([], Storage::disk('public')->allFiles());

        // Backup gravado antes de mudar.
        $this->assertCount(1, Storage::disk('local')->files('fotos-manifesto'));
    }

    public function test_sem_galeria_roda_sem_manifesto_como_em_producao(): void
    {
        $this->cenario();

        $this->artisan('produtos:aplicar-fotos', ['--sem-galeria' => true, '--manifesto' => "{$this->dir}/nao-existe.json"])
            ->expectsOutputToContain('relatório do manifesto fica de fora')
            ->assertSuccessful();
        $this->assertSame(['liga', 'mantem', 'upload'], Product::where('active', true)->orderBy('slug')->pluck('slug')->all());

        // Montar galeria sem manifesto continua sendo erro.
        $this->artisan('produtos:aplicar-fotos', ['--manifesto' => "{$this->dir}/nao-existe.json"])->assertFailed();
    }

    public function test_dry_run_nao_grava_nada(): void
    {
        $this->cenario();
        $antes = Product::orderBy('id')->pluck('active', 'slug')->all();

        $this->artisan('produtos:aplicar-fotos', ['--sem-galeria' => true, '--dry-run' => true, '--manifesto' => $this->manifesto([])])
            ->expectsOutputToContain('DESATIVA (4)')
            ->assertSuccessful();

        $this->assertSame($antes, Product::orderBy('id')->pluck('active', 'slug')->all());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_reverter_ultimo_volta_o_estado_anterior(): void
    {
        $this->cenario();
        $antes = Product::orderBy('id')->pluck('active', 'slug')->all();
        $galeria = $this->galeria();
        $manifesto = $this->manifesto([]);

        $this->artisan('produtos:aplicar-fotos', ['--sem-galeria' => true, '--manifesto' => $manifesto])->assertSuccessful();
        $this->assertNotSame($antes, Product::orderBy('id')->pluck('active', 'slug')->all());

        $this->artisan('produtos:aplicar-fotos', ['--reverter' => 'ultimo'])
            ->expectsOutputToContain('revertido')
            ->assertSuccessful();

        $this->assertSame($antes, Product::orderBy('id')->pluck('active', 'slug')->all());
        $this->assertSame(
            array_map(fn ($i) => array_diff_key($i, ['updated_at' => 1]), $galeria),
            array_map(fn ($i) => array_diff_key($i, ['updated_at' => 1]), $this->galeria()),
        );
    }

    public function test_galeria_com_manifesto_fundo_preto_e_recusada(): void
    {
        $p = $this->produto('liga', false, true);
        $manifesto = $this->manifesto([
            ['sku' => 'LIGA', 'produto' => 'Liga', 'tamanho' => '375ml', 'frente' => '375ML/LIGA_frente.jpg', 'verso' => null, 'tem_sku' => true, 'fundo' => 'preto'],
        ]);

        $this->artisan('produtos:aplicar-fotos', ['--manifesto' => $manifesto])
            ->expectsOutputToContain('galeria só com fundo branco')
            ->assertFailed();

        $this->assertFalse($p->fresh()->active);
        $this->assertSame(0, ProductImage::count());
    }

    public function test_galeria_com_manifesto_fundo_branco_gera_foto_e_ativa(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD indisponível.');
        }

        // Pacote fundo preto (rótulo antigo) sai da galeria; upload do admin fica no fim.
        $upload = 'products/'.str_repeat('b', 40).'.jpg';
        $p = $this->produto('gin', false, true, ['products/gin-750ml-frente.jpg', $upload]);
        $p->update(['sku' => 'G750', 'sizes' => ['750 ml'], 'size_prices' => ['750 ml' => 200]]);

        File::ensureDirectoryExists("{$this->dir}/750ML");
        foreach (['G750_frente', 'G750_verso'] as $nome) {
            $img = imagecreatetruecolor(300, 300); // quadrada: recorte 2:3
            imagejpeg($img, "{$this->dir}/750ML/{$nome}.jpg");
            imagedestroy($img);
        }
        $entrada = ['produto' => 'Gin', 'tamanho' => '750ml', 'frente' => '750ML/G750_frente.jpg', 'verso' => '750ML/G750_verso.jpg', 'tem_sku' => true];
        $manifesto = $this->manifesto([
            ['sku' => 'G750'] + $entrada,
            ['sku' => 'G750.'] + $entrada, // alias com ponto: conta uma vez só
        ]);

        $this->artisan('produtos:aplicar-fotos', ['--manifesto' => $manifesto, '--fundo' => 'branco'])->assertSuccessful();

        $p->refresh();
        $this->assertTrue($p->active);
        $this->assertSame(
            ['products/foto-branco-g750-frente.jpg', 'products/foto-branco-g750-verso.jpg', $upload],
            $p->images()->orderBy('sort_order')->pluck('path')->all(),
        );
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get('products/foto-branco-g750-frente.jpg'));
        $this->assertSame([800, 1200], [$w, $h]);
    }

    public function test_entrada_sem_sku_casa_pelo_grupo_e_pacote_preto_sai_sem_foto_nova(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD indisponível.');
        }

        $tonic = $this->produto('ale-tonic', false, true, ['products/ale-tonic-375ml-frente.jpg']);
        $tonic->update(['sku' => null]);
        $velho = $this->produto('velho', true, true, ['products/velho-375ml-frente.jpg', 'products/velho-375ml-verso.jpg']);

        File::ensureDirectoryExists("{$this->dir}/375ML");
        $img = imagecreatetruecolor(300, 300);
        imagejpeg($img, "{$this->dir}/375ML/sem-sku_ale-tonic_375ml_frente.jpg");
        imagedestroy($img);
        $manifesto = $this->manifesto([
            ['sku' => null, 'produto' => 'Ale Tonic', 'grupo' => 'ale-tonic', 'tamanho' => '375ml', 'frente' => '375ML/sem-sku_ale-tonic_375ml_frente.jpg', 'verso' => null, 'tem_sku' => false, 'fundo' => 'branco'],
        ]);

        $this->artisan('produtos:aplicar-fotos', ['--manifesto' => $manifesto])->assertSuccessful();

        $this->assertSame(['products/foto-branco-ale-tonic-375ml-frente.jpg'], $tonic->images()->pluck('path')->all());
        $this->assertTrue($tonic->fresh()->active);
        // Sem foto no manifesto: o pacote preto sai e o produto fica no placeholder, inativo.
        $this->assertSame([], $velho->images()->pluck('path')->all());
        $this->assertFalse($velho->fresh()->active);
    }

    public function test_pode_ativar_exige_preco_e_foto_no_padrao(): void
    {
        $this->assertTrue(Product::ehFotoNoPadrao('products/foto-branco-g750-frente.jpg'));
        $this->assertTrue(Product::ehFotoNoPadrao('products/foto-branco-ale-tonic-375ml-verso.jpg'));
        $this->assertTrue(Product::ehFotoNoPadrao('products/'.str_repeat('Z', 40).'.png'));
        $this->assertFalse(Product::ehFotoNoPadrao('products/aptk-gin-2-750ml-frente.jpg')); // pacote fundo preto
        $this->assertFalse(Product::ehFotoNoPadrao('products/foto-preto-g750-verso.jpg'));
        $this->assertFalse(Product::ehFotoNoPadrao('products/negroni-classico.jpg'));
        $this->assertFalse(Product::ehFotoNoPadrao('products/negroni-classico-serve.jpg'));
        $this->assertFalse(Product::ehFotoNoPadrao('products/foto-g750-frente.jpg'));

        $comTudo = $this->produto('a', false, true, ['products/foto-branco-a375-frente.jpg']);
        $semFoto = $this->produto('b', false, true);
        $semPreco = $this->produto('c', false, false, ['products/foto-branco-c375-frente.jpg']);

        $this->assertTrue($comTudo->podeAtivar());
        $this->assertFalse($semFoto->podeAtivar());
        $this->assertTrue($semFoto->podeAtivar(['products/foto-branco-b375-frente.jpg'])); // galeria candidata
        $this->assertFalse($semPreco->podeAtivar());
    }
}
