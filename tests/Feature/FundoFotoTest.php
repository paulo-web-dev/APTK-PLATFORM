<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\FundoFoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A moldura da foto de produto acompanha o fundo da foto: product_images.fundo
 * (amostrado nas bordas) vai em --media-bg; null cai no --color-bg-media.
 */
class FundoFotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * Foto 2:3 com "garrafa" clara no meio encostando na borda de baixo e
     * 1 px de borda de outra cor (artefato comum de JPG).
     */
    protected function foto(array $fundo, array $borda, string $formato = 'png'): string
    {
        $img = imagecreatetruecolor(400, 600);
        imagefill($img, 0, 0, imagecolorallocate($img, ...$fundo));
        imagefilledrectangle($img, 140, 120, 260, 599, imagecolorallocate($img, 200, 120, 40));
        imagerectangle($img, 0, 0, 399, 599, imagecolorallocate($img, ...$borda));

        ob_start();
        $formato === 'jpg' ? imagejpeg($img, null, 90) : imagepng($img);
        imagedestroy($img);

        return ob_get_clean();
    }

    protected function produto(string $slug = 'cosmopolitan'): Product
    {
        return Product::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'sku' => strtoupper(substr($slug, 0, 3)).'375', 'price' => 165.00,
            'sizes' => ['375 ml'], 'size_prices' => ['375 ml' => 165.00], 'stock_qty' => 10, 'active' => true,
        ]);
    }

    public function test_detecta_fundo_preto_e_branco_ignorando_borda_e_garrafa(): void
    {
        $this->assertSame('#000000', FundoFoto::detectar($this->foto([0, 0, 0], [255, 255, 255])));
        $this->assertSame('#FEFEFE', FundoFoto::detectar($this->foto([254, 254, 254], [237, 237, 237])));
        $this->assertSame('#E5DBC9', FundoFoto::detectar($this->foto([229, 219, 201], [0, 0, 0])));
    }

    public function test_conteudo_que_nao_e_imagem_e_arquivo_ausente_dao_null(): void
    {
        $this->assertNull(FundoFoto::detectar('isto não é imagem'));
        $this->assertNull(FundoFoto::doDisco('products/nao-existe.jpg'));
        $this->assertNull(FundoFoto::doDisco(null));
    }

    public function test_so_hex_valido_vai_pro_style(): void
    {
        $this->assertSame('#0A0B0C', FundoFoto::valida('#0a0b0c'));
        $this->assertNull(FundoFoto::valida('red'));
        $this->assertNull(FundoFoto::valida('#000; background:url(x)'));
        $this->assertNull(FundoFoto::valida(null));
    }

    public function test_upload_do_admin_grava_o_fundo_da_foto_reduzida(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@aptk.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $p = $this->produto();
        $arquivo = tempnam(sys_get_temp_dir(), 'fnd').'.jpg';
        file_put_contents($arquivo, $this->foto([0, 0, 0], [255, 255, 255], 'jpg'));

        $this->actingAs($admin)
            ->put("/admin/produtos/{$p->id}", [
                'name' => $p->name, 'price' => '165', 'stock_qty' => '10',
                'image' => new UploadedFile($arquivo, 'cosmo.jpg', 'image/jpeg', null, true),
            ])
            ->assertSessionHasNoErrors();
        @unlink($arquivo);

        $img = $p->images()->sole();
        $this->assertTrue($img->isUploadDoAdmin());
        [$r, $g, $b] = sscanf($img->fundo, '#%02x%02x%02x');
        $this->assertLessThanOrEqual(6, max($r, $g, $b), "fundo detectado {$img->fundo}");
    }

    public function test_gravar_imagem_com_arquivo_no_disco_calcula_o_fundo(): void
    {
        Storage::disk('public')->put('products/foto-branco-cos375-frente.jpg', $this->foto([254, 254, 254], [237, 237, 237]));
        $p = $this->produto();

        // O caminho do produtos:aplicar-fotos (updateOrCreate pelo path).
        $img = ProductImage::updateOrCreate(
            ['product_id' => $p->id, 'path' => 'products/foto-branco-cos375-frente.jpg'],
            ['alt' => $p->name, 'sort_order' => 0, 'is_primary' => true],
        );
        $this->assertSame('#FEFEFE', $img->fresh()->fundo);

        // Sem arquivo: fica null (fallback), sem erro.
        $semArquivo = ProductImage::create(['product_id' => $p->id, 'path' => 'products/sumiu.jpg', 'sort_order' => 1]);
        $this->assertNull($semArquivo->fresh()->fundo);

        // Fundo informado explicitamente prevalece.
        $img->update(['fundo' => '#123456']);
        $this->assertSame('#123456', $img->fresh()->fundo);
    }

    public function test_comando_lista_e_grava_so_sem_dry_run(): void
    {
        $preto = $this->produto('cosmopolitan');
        $branco = $this->produto('negroni');
        Storage::disk('public')->put('products/cosmo.jpg', $this->foto([0, 0, 0], [255, 255, 255]));
        Storage::disk('public')->put('products/negroni.jpg', $this->foto([254, 254, 254], [237, 237, 237]));
        // Linhas antigas, gravadas antes da coluna existir (fundo null).
        foreach ([[$preto, 'products/cosmo.jpg'], [$branco, 'products/negroni.jpg'], [$branco, 'products/sumiu.jpg']] as $i => [$p, $path]) {
            ProductImage::create(['product_id' => $p->id, 'path' => $path, 'fundo' => null, 'sort_order' => $i, 'is_primary' => $i < 2]);
        }
        ProductImage::query()->update(['fundo' => null]);

        $this->artisan('produtos:detectar-fundo', ['--dry-run' => true])
            // Uma linha da tabela por foto (cada linha só satisfaz uma expectativa).
            ->expectsOutputToContain('#000000')
            ->expectsOutputToContain('#FEFEFE')
            ->expectsOutputToContain('arquivo ausente')
            ->expectsOutputToContain('3 foto(s); 2 mudariam; 1 sem arquivo')
            ->assertSuccessful();
        $this->assertSame(3, ProductImage::whereNull('fundo')->count());

        $this->artisan('produtos:detectar-fundo')
            ->expectsOutputToContain('2 gravada(s)')
            ->assertSuccessful();
        $this->assertSame('#000000', ProductImage::where('path', 'products/cosmo.jpg')->value('fundo'));
        $this->assertSame('#FEFEFE', ProductImage::where('path', 'products/negroni.jpg')->value('fundo'));
        $this->assertNull(ProductImage::where('path', 'products/sumiu.jpg')->value('fundo'));

        $this->artisan('produtos:detectar-fundo')->expectsOutputToContain('0 gravada(s)')->assertSuccessful();
    }

    public function test_loja_e_pagina_do_produto_usam_a_cor_da_foto(): void
    {
        $p = $this->produto();
        ProductImage::create(['product_id' => $p->id, 'path' => 'products/cosmo-frente.jpg', 'fundo' => '#000000', 'sort_order' => 0, 'is_primary' => true]);
        ProductImage::create(['product_id' => $p->id, 'path' => 'products/cosmo-verso.jpg', 'fundo' => '#FEFEFE', 'sort_order' => 1]);

        $this->get('/loja')->assertOk()->assertSee('--media-bg: #000000', false);

        $this->get('/produto/cosmopolitan')->assertOk()
            ->assertSee('--media-bg: #000000', false)
            ->assertSee('data-fundo="#000000"', false)
            ->assertSee('data-fundo="#FEFEFE"', false);
    }

    public function test_sem_fundo_nao_poe_variavel_e_o_css_cai_no_padrao(): void
    {
        $p = $this->produto();
        ProductImage::create(['product_id' => $p->id, 'path' => 'products/sem-fundo.jpg', 'sort_order' => 0, 'is_primary' => true]);
        ProductImage::query()->update(['fundo' => 'lixo']); // valor inválido também não vai pro style

        $this->get('/loja')->assertOk()
            ->assertSee('/storage/products/sem-fundo.jpg', false)
            ->assertDontSee('--media-bg:', false)
            ->assertSee('var(--media-bg, var(--color-bg-media))', false);

        $css = file_get_contents(public_path('css/aptk-produto.css'));
        $this->assertStringContainsString('background: var(--media-bg, var(--color-bg-media));', $css);
        $this->assertStringContainsString('--media-bg: var(--color-bg-placeholder);', $css);
    }

    public function test_css_de_moldura_nao_tem_cor_fixa(): void
    {
        $css = file_get_contents(public_path('css/aptk-produto.css'));
        $this->assertDoesNotMatchRegularExpression('/#[0-9A-Fa-f]{3,8}\b|rgba?\(/', $css);
    }
}
