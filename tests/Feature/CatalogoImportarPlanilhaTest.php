<?php

namespace Tests\Feature;

use App\Models\MicrovixProduct;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * catalogo:importar-planilha (leva 07) — catálogo pela planilha da gestão.
 * O CSV atualiza o que tem valor e nunca remove o que já está à venda;
 * ativo só com preço E imagem; produto do ERP fica intocado.
 */
class CatalogoImportarPlanilhaTest extends TestCase
{
    use RefreshDatabase;

    private string $fotos;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD indisponível.');
        }

        Storage::fake('public');

        // Fotos de origem: uma grande (testa o redimensionamento), o resto mínimo.
        $this->fotos = storage_path('framework/testing/catalogo-fotos');
        File::ensureDirectoryExists($this->fotos);
        foreach (['f1' => [1600, 2400], 'f2' => [30, 45], 'f3' => [30, 45], 'f4' => [30, 45], 'f5' => [30, 45], 'f6' => [30, 45], 'f7' => [30, 45], 'f8' => [30, 45], 'f9' => [30, 45], 'f10' => [30, 45], 'f11' => [30, 45]] as $nome => [$w, $h]) {
            $img = imagecreatetruecolor($w, $h);
            imagejpeg($img, "{$this->fotos}/{$nome}.jpg");
            imagedestroy($img);
        }

        foreach (['classicos' => 'Clássicos', 'bases' => 'Bases', 'barin' => 'Barin'] as $slug => $nome) {
            ProductCategory::create(['name' => $nome, 'slug' => $slug, 'active' => true]);
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fotos);

        parent::tearDown();
    }

    private function importar(bool $dryRun = false)
    {
        return $this->artisan('catalogo:importar-planilha', array_filter([
            '--csv'     => base_path('tests/fixtures/catalogo/planilha.csv'),
            '--fotos'   => $this->fotos,
            '--dry-run' => $dryRun,
        ]));
    }

    private function negroniExistente(): Product
    {
        $p = Product::create([
            'name'        => 'Negroni Clássico',
            'slug'        => 'negroni-classico',
            'sku'         => 'NEG-750',
            'price'       => 189.90,
            'sizes'       => ['375 ml', '750 ml'],
            'size_prices' => ['375 ml' => 123.44, '750 ml' => 189.90],
            'stock_qty'   => 30,
            'active'      => true,
        ]);
        Storage::disk('public')->put('products/negroni-classico.jpg', 'antiga');
        ProductImage::create(['product_id' => $p->id, 'path' => 'products/negroni-classico.jpg', 'is_primary' => true, 'sort_order' => 0]);
        ProductImage::create(['product_id' => $p->id, 'path' => 'products/negroni-classico-serve.jpg', 'is_primary' => false, 'sort_order' => 1]);

        return $p;
    }

    public function test_volume_sem_preco_fica_fora_de_sizes_e_da_galeria(): void
    {
        $this->importar()->assertSuccessful();

        $p = Product::where('slug', 'boulevardier')->with('images')->firstOrFail();

        $this->assertSame(['100 ml', '375 ml'], $p->sizes);
        $this->assertEquals(['100 ml' => 80.0, '375 ml' => 165.0], $p->size_prices);
        $this->assertSame('375 ml', $p->defaultSize());
        $this->assertEquals(165.0, (float) $p->price);
        $this->assertSame('BOU375', $p->sku);
        $this->assertTrue($p->active);
        $this->assertSame(30, $p->stock_qty);
        $this->assertSame('classicos', $p->category->slug);

        // O 750 tem foto mas não está à venda: fica fora da galeria (nem é gerado).
        $this->assertSame([
            'products/boulevardier-375ml-frente.jpg',
            'products/boulevardier-375ml-verso.jpg',
        ], $p->images->pluck('path')->all());
        $this->assertTrue($p->images->first()->is_primary);
        $this->assertSame(1, $p->images->where('is_primary', true)->count());
        Storage::disk('public')->assertMissing('products/boulevardier-750ml-frente.jpg');

        // 1600x2400 → lado maior 1200
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get('products/boulevardier-375ml-frente.jpg'));
        $this->assertSame([800, 1200], [$w, $h]);
    }

    public function test_principal_acompanha_o_volume_padrao_e_galeria_vai_do_maior_pro_menor(): void
    {
        $this->importar()->assertSuccessful();

        $p = Product::where('slug', 'fitzgerald')->with('images')->firstOrFail();

        $this->assertSame('750 ml', $p->defaultSize());
        $this->assertSame([
            'products/fitzgerald-750ml-frente.jpg',
            'products/fitzgerald-750ml-verso.jpg',
            'products/fitzgerald-375ml-frente.jpg',
            'products/fitzgerald-375ml-verso.jpg',
        ], $p->images->pluck('path')->all());
        $this->assertSame('products/fitzgerald-750ml-frente.jpg', $p->primaryImage->path);
    }

    public function test_produto_sem_preco_entra_inativo(): void
    {
        $this->importar()->assertSuccessful();

        $p = Product::where('slug', 'scarlatti')->firstOrFail();

        $this->assertFalse($p->active);
        $this->assertNull($p->sizes);
        $this->assertSame(1, $p->images()->count());

        // Leva 08: com preço e sem foto entra no ar (o site mostra o placeholder).
        $cosmo = Product::where('slug', 'cosmopolitan')->firstOrFail();
        $this->assertTrue($cosmo->active);
        $this->assertSame(0, $cosmo->images()->count());
    }

    public function test_produto_existente_mantem_volume_sem_preco_e_slug_galeria_so_com_foto_nova(): void
    {
        $antes = $this->negroniExistente();

        $this->importar()
            ->expectsOutputToContain('negroni-classico 750 ml: R$ 189,90 (sem preço na planilha)')
            ->assertSuccessful();

        $p = $antes->fresh('images');

        $this->assertSame('negroni-classico', $p->slug);
        $this->assertSame(['375 ml', '750 ml'], $p->sizes);
        $this->assertEquals(['375 ml' => 165.0, '750 ml' => 189.90], $p->size_prices);
        $this->assertSame('NC750', $p->sku);
        $this->assertTrue($p->active);

        // Padrão é o 750 (mantido, sem foto): a principal cai na frente do 375.
        $this->assertSame('750 ml', $p->defaultSize());
        // Garrafa antiga e -serve saem da galeria; os arquivos ficam.
        $this->assertSame([
            'products/negroni-classico-375ml-frente.jpg',
            'products/negroni-classico-375ml-verso.jpg',
        ], $p->images->pluck('path')->all());
        $this->assertTrue($p->images->first()->is_primary);
        $this->assertFalse($p->images->last()->is_primary);
        Storage::disk('public')->assertExists('products/negroni-classico.jpg');
    }

    public function test_produto_com_preco_e_so_foto_antiga_fica_ativo_e_a_foto_antiga_sai_da_galeria(): void
    {
        $p = Product::create([
            'name'        => 'Cosmopolitan',
            'slug'        => 'cosmopolitan',
            'price'       => 169.90,
            'sizes'       => ['375 ml'],
            'size_prices' => ['375 ml' => 110.44],
            'stock_qty'   => 30,
            'active'      => false,
        ]);
        Storage::disk('public')->put('products/cosmopolitan.jpg', 'antiga');
        ProductImage::create(['product_id' => $p->id, 'path' => 'products/cosmopolitan.jpg', 'is_primary' => true, 'sort_order' => 0]);
        ProductImage::create(['product_id' => $p->id, 'path' => 'products/cosmopolitan-serve.jpg', 'is_primary' => false, 'sort_order' => 1]);

        $this->importar()
            ->expectsOutputToContain('com placeholder, sem foto (1): cosmopolitan')
            ->assertSuccessful();

        $p->refresh();
        $this->assertTrue($p->active);
        $this->assertEquals(['375 ml' => 165.0], $p->size_prices);
        $this->assertSame(0, $p->images()->count());
        Storage::disk('public')->assertExists('products/cosmopolitan.jpg'); // só a linha sai

        $this->importar()->assertSuccessful();
        $this->assertTrue($p->fresh()->active);
        $this->assertSame(0, $p->images()->count());
    }

    public function test_upload_do_admin_sobrevive_a_reexecucao_e_fica_na_frente(): void
    {
        $negroni = $this->negroniExistente();
        $this->importar()->assertSuccessful();

        // A gestão sobe uma foto pelo admin (nome hash): vira a principal.
        ProductImage::where('product_id', $negroni->id)->increment('sort_order');
        ProductImage::where('product_id', $negroni->id)->update(['is_primary' => false]);
        ProductImage::create(['product_id' => $negroni->id, 'path' => 'products/Hx9aQ2upload.jpg', 'is_primary' => true, 'sort_order' => 0]);

        // Produto sem foto do pacote também guarda o upload.
        $this->importar()->assertSuccessful();
        $cosmo = Product::where('slug', 'cosmopolitan')->firstOrFail();
        ProductImage::create(['product_id' => $cosmo->id, 'path' => 'products/Zk3upload.jpg', 'is_primary' => true, 'sort_order' => 0]);

        $this->importar()->assertSuccessful();

        $galeria = $negroni->fresh('images')->images;
        $this->assertSame([
            'products/Hx9aQ2upload.jpg',
            'products/negroni-classico-375ml-frente.jpg',
            'products/negroni-classico-375ml-verso.jpg',
        ], $galeria->pluck('path')->all());
        $this->assertSame([true, false, false], $galeria->pluck('is_primary')->all());
        $this->assertSame(['products/Zk3upload.jpg'], $cosmo->images()->pluck('path')->all());
    }

    public function test_preco_descartado_entra_inativo_sem_preco(): void
    {
        $licor = Product::create([
            'name'        => 'Licor de Chocolate Cacau Show',
            'slug'        => 'licor-chocolate-cacau-show',
            'price'       => 42.79,
            'sizes'       => ['375 ml'],
            'size_prices' => ['375 ml' => 42.79],
            'stock_qty'   => 30,
            'active'      => true,
        ]);

        $csv = storage_path('framework/testing/catalogo-descartado.csv');
        File::put($csv, "grupo,nome,marca,volume,sku,preco,foto_frente,foto_verso\n"
            ."licor-chocolate-cacau-show,Licor de Chocolate Cacau Show,aptk,375ml,LCCS375,,,\n");

        try {
            $this->artisan('catalogo:importar-planilha', ['--csv' => $csv, '--fotos' => $this->fotos])
                ->expectsOutputToContain('sem preço (descartado, confirmar com a gestão) (1): licor-chocolate-cacau-show')
                ->assertSuccessful();
        } finally {
            File::delete($csv);
        }

        $licor->refresh();
        $this->assertFalse($licor->active);
        $this->assertNull($licor->size_prices);
        $this->assertNull($licor->sizes);
        $this->assertEquals(0.0, (float) $licor->price);
    }

    public function test_desativa_produto_fora_da_planilha_da_lista_sem_mexer_no_resto(): void
    {
        $cachaca = Product::create([
            'name'      => 'Cachaça Prata',
            'slug'      => 'cachaca-prata',
            'price'     => 129.90,
            'sizes'     => ['750 ml'],
            'stock_qty' => 30,
            'active'    => true,
        ]);
        ProductImage::create(['product_id' => $cachaca->id, 'path' => 'products/cachaca-prata.jpg', 'is_primary' => true, 'sort_order' => 0]);
        ProductImage::create(['product_id' => $cachaca->id, 'path' => 'products/cachaca-prata-serve.jpg', 'is_primary' => false, 'sort_order' => 1]);
        $antes = $cachaca->fresh()->getAttributes();

        $this->importar(dryRun: true)
            ->expectsOutputToContain('[DESATIVA] cachaca-prata')
            ->assertSuccessful();
        $this->assertTrue($cachaca->fresh()->active);
        $this->assertSame(2, $cachaca->images()->count());

        $this->importar()
            ->expectsOutputToContain('fora da planilha desativados (1): cachaca-prata')
            ->assertSuccessful();

        $depois = $cachaca->fresh()->getAttributes();
        $this->assertSame(0, (int) $depois['active']);
        unset($antes['active'], $antes['updated_at'], $depois['active'], $depois['updated_at']);
        $this->assertSame($antes, $depois);
        $this->assertSame(0, $cachaca->images()->count()); // foto antiga sai da galeria (leva 08)

        // Reexecução: já inativo, nada a fazer.
        $this->importar()
            ->expectsOutputToContain('fora da planilha desativados (0): nenhum')
            ->assertSuccessful();
    }

    public function test_rodar_duas_vezes_nao_duplica_produto_nem_imagem(): void
    {
        $this->negroniExistente();

        $this->importar()->assertSuccessful();
        $produtos = Product::count();
        $imagens = ProductImage::orderBy('id')->get(['id', 'product_id', 'path', 'sort_order', 'is_primary'])->toArray();

        $this->importar()
            ->expectsOutputToContain('sem mudança: 5')
            ->assertSuccessful();

        $this->assertSame($produtos, Product::count());
        $this->assertSame($imagens, ProductImage::orderBy('id')->get(['id', 'product_id', 'path', 'sort_order', 'is_primary'])->toArray());
    }

    public function test_reaproveita_destino_sem_a_foto_de_origem(): void
    {
        $this->importar()->assertSuccessful();
        Product::query()->delete();
        ProductImage::query()->delete();

        // Produção: sem as fotos de origem, só as geradas (vindas pelo git).
        File::deleteDirectory($this->fotos);
        File::ensureDirectoryExists($this->fotos);

        $this->importar()->assertSuccessful();

        $this->assertSame(4, Product::where('slug', 'fitzgerald')->firstOrFail()->images()->count());
    }

    public function test_dry_run_nao_grava_nada(): void
    {
        $existente = $this->negroniExistente();

        $this->importar(dryRun: true)
            ->expectsOutputToContain('nada gravado')
            ->assertSuccessful();

        $this->assertSame(1, Product::count());
        $this->assertSame(2, ProductImage::count());
        $this->assertEquals(['375 ml' => 123.44, '750 ml' => 189.90], $existente->fresh()->size_prices);
        $this->assertSame(['products/negroni-classico.jpg'], Storage::disk('public')->allFiles());
    }

    public function test_produto_gerenciado_pelo_erp_fica_intocado(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);

        $p = Product::create([
            'name'        => 'Cosmopolitan',
            'slug'        => 'cosmopolitan',
            'price'       => 169.90,
            'sizes'       => ['375 ml'],
            'size_prices' => ['375 ml' => 110.44],
            'stock_qty'   => 30,
            'active'      => true,
        ]);
        MicrovixProduct::create(['codigoproduto' => 900001, 'product_id' => $p->id, 'size' => '375 ml', 'nome_produto' => 'COSMO']);
        $antes = $p->fresh()->getAttributes();

        $this->importar()
            ->expectsOutputToContain('[PULADO ERP] cosmopolitan')
            ->assertSuccessful();

        $this->assertSame($antes, $p->fresh()->getAttributes());
    }

    // ---- catalogo:produto-teste ------------------------------------------

    public function test_produto_teste_cria_ativo_em_bases_a_um_real_com_placeholder(): void
    {
        $this->artisan('catalogo:produto-teste')
            ->expectsOutputToContain('Criado o produto de teste')
            ->assertSuccessful();

        $p = Product::where('slug', 'teste')->sole();
        $this->assertSame('Teste', $p->name);
        $this->assertSame('TESTE', $p->sku);
        $this->assertTrue($p->active);
        $this->assertSame('bases', $p->category->slug);
        $this->assertSame(['100 ml'], $p->sizes);
        $this->assertSame(1.0, $p->priceForSize('100 ml'));
        $this->assertTrue($p->temPreco());
        $this->assertSame(0, $p->images()->count()); // vitrine mostra o placeholder
    }

    public function test_produto_teste_e_idempotente_e_remover_so_desativa(): void
    {
        $this->artisan('catalogo:produto-teste')->assertSuccessful();
        $this->artisan('catalogo:produto-teste')->assertSuccessful();
        $this->assertSame(1, Product::where('slug', 'teste')->count());

        $this->artisan('catalogo:produto-teste --remover')
            ->expectsOutputToContain('desativado')
            ->assertSuccessful();
        $p = Product::where('slug', 'teste')->sole();
        $this->assertFalse($p->active);

        // Alguém mexeu no preço pelo admin; rodar de novo reativa e repõe.
        $p->update(['size_prices' => ['100 ml' => 50.00], 'price' => 50.00]);
        $this->artisan('catalogo:produto-teste')
            ->expectsOutputToContain('Reativado')
            ->assertSuccessful();
        $p->refresh();
        $this->assertTrue($p->active);
        $this->assertSame(1.0, $p->priceForSize('100 ml'));
        $this->assertSame(1, Product::where('slug', 'teste')->count());
    }

    public function test_remover_sem_produto_nao_quebra(): void
    {
        $this->artisan('catalogo:produto-teste --remover')
            ->expectsOutputToContain('não existe')
            ->assertSuccessful();
        $this->assertSame(0, Product::count());
    }

    public function test_importar_planilha_nao_mexe_no_produto_teste(): void
    {
        $this->artisan('catalogo:produto-teste')->assertSuccessful();
        $antes = Product::where('slug', 'teste')->sole()->getAttributes();

        $this->importar()->assertSuccessful();

        $this->assertSame($antes, Product::where('slug', 'teste')->sole()->getAttributes());
    }

    public function test_grupo_teste_na_planilha_e_ignorado(): void
    {
        $this->artisan('catalogo:produto-teste')->assertSuccessful();
        $antes = Product::where('slug', 'teste')->sole()->getAttributes();

        $csv = storage_path('framework/testing/planilha-com-teste.csv');
        File::put($csv, "grupo,nome,marca,volume,sku,preco,foto_frente,foto_verso\nteste,Teste Planilha,aptk,750ml,X750,999.00,,\n");

        $this->artisan('catalogo:importar-planilha', ['--csv' => $csv, '--fotos' => $this->fotos])
            ->expectsOutputToContain('produto de teste de pagamento, ignorado')
            ->assertSuccessful();

        File::delete($csv);
        $this->assertSame($antes, Product::where('slug', 'teste')->sole()->getAttributes());
        $this->assertSame(1, Product::where('slug', 'teste')->count());
    }
}
