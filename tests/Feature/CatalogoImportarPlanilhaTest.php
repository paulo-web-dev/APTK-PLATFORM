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
 * catalogo:importar-planilha — catálogo pela planilha da gestão.
 * O CSV atualiza nome, volumes, preços, SKU e categoria e nunca remove o que
 * já está à venda; NÃO mexe em active nem em product_images (isso é do
 * produtos:aplicar-fotos); produto do ERP fica intocado.
 */
class CatalogoImportarPlanilhaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        foreach (['classicos' => 'Clássicos', 'bases' => 'Bases', 'barin' => 'Barin'] as $slug => $nome) {
            ProductCategory::create(['name' => $nome, 'slug' => $slug, 'active' => true]);
        }
    }

    private function importar(bool $dryRun = false)
    {
        return $this->artisan('catalogo:importar-planilha', array_filter([
            '--csv'     => base_path('tests/fixtures/catalogo/planilha.csv'),
            '--dry-run' => $dryRun,
        ]));
    }

    private function negroniExistente(bool $ativo = true): Product
    {
        $p = Product::create([
            'name'        => 'Negroni Clássico',
            'slug'        => 'negroni-classico',
            'sku'         => 'NEG-750',
            'price'       => 189.90,
            'sizes'       => ['375 ml', '750 ml'],
            'size_prices' => ['375 ml' => 123.44, '750 ml' => 189.90],
            'stock_qty'   => 30,
            'active'      => $ativo,
        ]);
        ProductImage::create(['product_id' => $p->id, 'path' => 'products/negroni-classico.jpg', 'is_primary' => true, 'sort_order' => 0]);
        ProductImage::create(['product_id' => $p->id, 'path' => 'products/negroni-classico-375ml-frente.jpg', 'is_primary' => false, 'sort_order' => 1]);

        return $p;
    }

    private function galeria(): array
    {
        return ProductImage::orderBy('id')->get(['id', 'product_id', 'path', 'alt', 'sort_order', 'is_primary'])->toArray();
    }

    private function csvTemporario(string $nome, string $conteudo): string
    {
        $csv = storage_path("framework/testing/{$nome}.csv");
        File::ensureDirectoryExists(dirname($csv));
        File::put($csv, $conteudo);

        return $csv;
    }

    public function test_cria_produto_novo_inativo_com_volumes_precos_sku_e_categoria(): void
    {
        $this->importar()->assertSuccessful();

        $p = Product::where('slug', 'boulevardier')->firstOrFail();

        // 750 sem preço fica fora; 100 ml é volume desativado (leva 15).
        $this->assertSame(['375 ml'], $p->sizes);
        $this->assertEquals(['375 ml' => 165.0], $p->size_prices);
        $this->assertSame('375 ml', $p->defaultSize());
        $this->assertEquals(165.0, (float) $p->price);
        $this->assertSame('BOU375', $p->sku);
        $this->assertSame(30, $p->stock_qty);
        $this->assertSame('classicos', $p->category->slug);

        // Ativação e galeria são do produtos:aplicar-fotos.
        $this->assertFalse($p->active);
        $this->assertSame(0, ProductImage::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_importar_nao_altera_active_nem_galeria(): void
    {
        $ativo = $this->negroniExistente(ativo: true);
        // Com preço e foto, mas inativo: a planilha não religa.
        $fitz = Product::create([
            'name' => 'Fitzgerald', 'slug' => 'fitzgerald', 'price' => 100, 'sizes' => ['375 ml'],
            'size_prices' => ['375 ml' => 100], 'stock_qty' => 30, 'active' => false,
        ]);
        ProductImage::create(['product_id' => $fitz->id, 'path' => 'products/fitzgerald-375ml-frente.jpg', 'is_primary' => true, 'sort_order' => 0]);
        $galeria = $this->galeria();

        $this->importar()->assertSuccessful();
        $this->importar()->assertSuccessful();

        $this->assertTrue($ativo->fresh()->active);
        $this->assertFalse($fitz->fresh()->active);
        $this->assertSame($galeria, $this->galeria());
        // Os preços mudaram de verdade: o comando rodou, só não tocou no resto.
        $this->assertEquals(['375 ml' => 165.0, '750 ml' => 210.0], $fitz->fresh()->size_prices);
    }

    public function test_produto_existente_mantem_volume_sem_preco_e_slug(): void
    {
        $antes = $this->negroniExistente();

        $this->importar()
            ->expectsOutputToContain('negroni-classico 750 ml: R$ 189,90 (sem preço na planilha)')
            ->assertSuccessful();

        $p = $antes->fresh();

        $this->assertSame('negroni-classico', $p->slug);
        $this->assertSame(['375 ml', '750 ml'], $p->sizes);
        $this->assertEquals(['375 ml' => 165.0, '750 ml' => 189.90], $p->size_prices);
        $this->assertSame('NC750', $p->sku);
        $this->assertTrue($p->active);
    }

    public function test_produto_sem_preco_e_criado_inativo(): void
    {
        $this->importar()->assertSuccessful();

        $p = Product::where('slug', 'scarlatti')->firstOrFail();

        $this->assertFalse($p->active);
        $this->assertNull($p->sizes);
        $this->assertSame('SCA750', $p->sku);
    }

    public function test_preco_descartado_zera_o_preco_mas_nao_desativa_e_avisa(): void
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

        $csv = $this->csvTemporario('catalogo-descartado', "grupo,nome,marca,volume,sku,preco,foto_frente,foto_verso\n"
            ."licor-chocolate-cacau-show,Licor de Chocolate Cacau Show,aptk,375ml,LCCS375,,,\n");

        try {
            $this->artisan('catalogo:importar-planilha', ['--csv' => $csv])
                ->expectsOutputToContain('sem preço (descartado, confirmar com a gestão) (1): licor-chocolate-cacau-show')
                ->expectsOutputToContain('Ativos e sem preço (1): licor-chocolate-cacau-show')
                ->assertSuccessful();
        } finally {
            File::delete($csv);
        }

        $licor->refresh();
        $this->assertTrue($licor->active); // quem desliga é o produtos:aplicar-fotos
        $this->assertNull($licor->size_prices);
        $this->assertNull($licor->sizes);
        $this->assertEquals(0.0, (float) $licor->price);
    }

    public function test_csv_sem_colunas_de_foto_e_aceito(): void
    {
        $csv = $this->csvTemporario('catalogo-sem-foto', "grupo,nome,marca,volume,sku,preco\ncanarinha,Canarinha,aptk,375ml,CNA375,165.00\n");

        try {
            $this->artisan('catalogo:importar-planilha', ['--csv' => $csv])->assertSuccessful();
        } finally {
            File::delete($csv);
        }

        $this->assertSame('CNA375', Product::where('slug', 'canarinha')->sole()->sku);
    }

    public function test_produto_fora_da_planilha_nao_e_tocado(): void
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
        $antes = $cachaca->fresh()->getAttributes();

        $this->importar()->assertSuccessful();

        $this->assertSame($antes, $cachaca->fresh()->getAttributes());
        $this->assertSame(1, $cachaca->images()->count());
    }

    public function test_rodar_duas_vezes_nao_duplica_produto(): void
    {
        $this->negroniExistente();

        $this->importar()->assertSuccessful();
        $produtos = Product::count();

        $this->importar()
            ->expectsOutputToContain('sem mudança: 5')
            ->assertSuccessful();

        $this->assertSame($produtos, Product::count());
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

        $this->artisan('catalogo:importar-planilha', ['--csv' => $csv])
            ->expectsOutputToContain('produto de teste de pagamento, ignorado')
            ->assertSuccessful();

        File::delete($csv);
        $this->assertSame($antes, Product::where('slug', 'teste')->sole()->getAttributes());
        $this->assertSame(1, Product::where('slug', 'teste')->count());
    }
}
