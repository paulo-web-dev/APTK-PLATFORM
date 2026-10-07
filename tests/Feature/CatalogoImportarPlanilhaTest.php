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

        // Sem imagem também fica inativo, mesmo com preço.
        $this->assertFalse(Product::where('slug', 'cosmopolitan')->firstOrFail()->active);
    }

    public function test_produto_existente_mantem_volume_sem_preco_slug_e_serve(): void
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
        $this->assertSame([
            'products/negroni-classico-375ml-frente.jpg',
            'products/negroni-classico-375ml-verso.jpg',
            'products/negroni-classico-serve.jpg',
        ], $p->images->pluck('path')->all());
        $this->assertTrue($p->images->first()->is_primary);
        $this->assertFalse($p->images->last()->is_primary);

        // A foto antiga da garrafa sai da galeria, mas o arquivo fica.
        Storage::disk('public')->assertExists('products/negroni-classico.jpg');
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
}
