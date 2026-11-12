<?php

namespace Tests\Feature\Microvix;

use App\Models\MicrovixProduct;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Microvix\MicrovixVinculador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MicrovixVincularTest extends TestCase
{
    use RefreshDatabase;

    protected string $planilha;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // planilha de exemplo: nunca a real
        $this->planilha = tempnam(sys_get_temp_dir(), 'vinc').'.csv';
        file_put_contents($this->planilha, implode("\n", [
            'grupo,nome,marca,volume,sku,preco,foto_frente,foto_verso',
            'boulevardier,Boulevardier,aptk,375ml,BOU375,165.00,,',
            'boulevardier,Boulevardier,aptk,750ml,BOU750,210.00,,',
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->planilha);
        parent::tearDown();
    }

    protected function produto(string $nome, string $slug, array $sizes, ?string $sku = null, ?string $categoria = null): Product
    {
        $cat = $categoria
            ? ProductCategory::firstOrCreate(['slug' => $categoria], ['name' => ucfirst($categoria), 'active' => true])
            : null;

        return Product::create([
            'name' => $nome, 'slug' => $slug, 'sku' => $sku, 'sizes' => $sizes,
            'price' => 100, 'category_id' => $cat?->id,
        ]);
    }

    protected function mp(int $codigo, string $nome, ?string $aux = null, array $extra = []): MicrovixProduct
    {
        return MicrovixProduct::create(['codigoproduto' => $codigo, 'nome_produto' => $nome, 'codauxiliar1' => $aux] + $extra);
    }

    /** @return array<int, array> codigoproduto => linha do plano */
    protected function plano(bool $forcar = false): array
    {
        $plano = (new MicrovixVinculador())->plano($forcar, $this->planilha);

        return collect($plano)->keyBy(fn ($l) => $l['mp']->codigoproduto)->all();
    }

    protected function vincular(array $opcoes = []): void
    {
        $this->artisan('microvix:vincular', ['--planilha' => $this->planilha] + $opcoes)->assertSuccessful();
    }

    public function test_casa_por_sku_do_produto_e_por_sku_por_volume_da_planilha(): void
    {
        $bou = $this->produto('Boulevardier', 'boulevardier', ['375 ml', '750 ml'], 'BOU750');
        $neg = $this->produto('Negroni', 'negroni', ['375 ml', '750 ml'], 'NEG750');

        $this->mp(1, 'APTK BOULEVARDIER 375ML', ' bou375 ');   // planilha, volume da planilha
        $this->mp(2, 'APTK NEGRONI 750 ML', 'NEG750.');        // products.sku, volume do nome

        $p = $this->plano();

        $this->assertSame('exato', $p[1]['status']);
        $this->assertSame($bou->id, $p[1]['product']->id);
        $this->assertSame('375 ml', $p[1]['size']);

        $this->assertSame('exato', $p[2]['status']);
        $this->assertSame($neg->id, $p[2]['product']->id);
        $this->assertSame('750 ml', $p[2]['size']);
    }

    public function test_sku_normalizado_repetido_marca_os_dois_como_ambiguos_e_nao_grava(): void
    {
        $this->produto('APTK Gin Nº2 Neutral', 'aptk-gin-2', ['750 ml'], 'G2750');
        $this->mp(10, 'KALEIDO 750ML', 'G2750');
        $this->mp(11, 'BURDEOS 750ML', 'G2750.');

        $p = $this->plano();
        $this->assertSame('ambiguo', $p[10]['status']);
        $this->assertSame('ambiguo', $p[11]['status']);

        $this->vincular(['--aplicar' => true, '--minimo' => 'medio']);
        $this->assertSame(0, MicrovixProduct::whereNotNull('product_id')->count());
    }

    public function test_casa_por_nome_com_volume(): void
    {
        $prod = $this->produto('Espresso Martini', 'espresso-martini', ['375 ml', '750 ml']);
        $this->mp(20, 'APTK ESPRESSO MARTINI 375ML');

        $l = $this->plano()[20];
        $this->assertSame('alto', $l['status']);
        $this->assertSame('nome', $l['criterio']);
        $this->assertSame($prod->id, $l['product']->id);
        $this->assertSame('375 ml', $l['size']);
    }

    public function test_volume_sem_tamanho_correspondente_no_site(): void
    {
        $this->produto('Espresso Martini', 'espresso-martini', ['750 ml']);
        $this->mp(30, 'APTK ESPRESSO MARTINI 375ML');

        $l = $this->plano()[30];
        $this->assertSame('sem_tamanho', $l['status']);
        $this->assertNull($l['size']);
    }

    public function test_linha_de_100_ml_e_ignorada(): void
    {
        $this->produto('Boulevardier', 'boulevardier', ['375 ml', '750 ml'], 'BOU750');
        $this->mp(40, 'APTK BOULEVARDIER 100ML', 'BOU100');

        $l = $this->plano()[40];
        $this->assertSame('ignorado', $l['status']);
        $this->assertSame('100 ml', $l['volume']);

        $this->artisan('microvix:vincular', ['--planilha' => $this->planilha])
            ->expectsOutputToContain('ignorado (100 ml)')
            ->assertSuccessful();
    }

    public function test_dois_codigos_pelo_mesmo_produto_e_tamanho_fica_o_de_maior_score(): void
    {
        $this->produto('Espresso Martini', 'espresso-martini', ['750 ml']);
        $this->mp(50, 'APTK ESPRESSO MARTINI 750ML');
        $this->mp(51, 'APTK ESPRESSO MARTIN 750ML');

        $p = $this->plano();
        $this->assertSame('alto', $p[50]['status']);
        $this->assertSame('conflito', $p[51]['status']);
    }

    public function test_aplicar_respeita_o_nivel_minimo(): void
    {
        $this->produto('Espresso Martini', 'espresso-martini', ['750 ml']);
        $this->produto('Vermute Circollo Rosso', 'vermute-circollo-rosso', ['750 ml']);
        $this->mp(60, 'APTK ESPRESSO MARTINI 750ML');   // alto
        $this->mp(61, 'VERMUTE CIRCOLO ROSSO SECO 750ML'); // médio

        $p = $this->plano();
        $this->assertSame('alto', $p[60]['status']);
        $this->assertSame('medio', $p[61]['status']);

        $this->vincular(); // prévia não grava
        $this->assertSame(0, MicrovixProduct::whereNotNull('product_id')->count());
        $this->assertTrue(Storage::disk('local')->exists('microvix/vinculos-'.now()->format('d-m-Y').'.csv'));

        $this->vincular(['--aplicar' => true]);
        $this->assertNotNull(MicrovixProduct::where('codigoproduto', 60)->value('product_id'));
        $this->assertNull(MicrovixProduct::where('codigoproduto', 61)->value('product_id'));

        $this->vincular(['--aplicar' => true, '--minimo' => 'medio']);
        $this->assertNotNull(MicrovixProduct::where('codigoproduto', 61)->value('product_id'));
        $this->assertSame('750 ml', MicrovixProduct::where('codigoproduto', 61)->value('size'));
    }

    public function test_nao_sobrescreve_product_id_existente_sem_forcar(): void
    {
        $outro = $this->produto('Outro', 'outro', ['750 ml']);
        $this->produto('Espresso Martini', 'espresso-martini', ['750 ml']);
        $this->mp(70, 'APTK ESPRESSO MARTINI 750ML', null, ['product_id' => $outro->id, 'size' => '750 ml']);

        $this->assertSame('ja_vinculado', $this->plano()[70]['status']);

        $this->vincular(['--aplicar' => true, '--minimo' => 'medio']);
        $this->assertSame($outro->id, MicrovixProduct::where('codigoproduto', 70)->value('product_id'));

        $this->vincular(['--aplicar' => true, '--forcar' => true]);
        $this->assertNotSame($outro->id, MicrovixProduct::where('codigoproduto', 70)->value('product_id'));
    }

    public function test_par_ja_vinculado_no_banco_vira_conflito_para_quem_disputa(): void
    {
        $prod = $this->produto('Espresso Martini', 'espresso-martini', ['750 ml']);
        $this->mp(80, 'CODIGO ANTIGO 750ML', null, ['product_id' => $prod->id, 'size' => '750 ml']);
        $this->mp(81, 'APTK ESPRESSO MARTINI 750ML');

        $this->assertSame('conflito', $this->plano()[81]['status']);
    }

    public function test_marca_barin_filtra_candidatos_nos_dois_sentidos(): void
    {
        $this->produto('APTK Gin Nº2 Neutral', 'aptk-gin-2', ['750 ml']);
        $this->produto('Vodka 22', 'aptk-vodka', ['750 ml']);
        $vodkaBarin = $this->produto('Vodka BARIN', 'vodka-barin', ['750 ml'], null, 'barin');

        $this->mp(90, 'GIN BARIN 750ML');
        $this->mp(91, 'VODKA 750ML');

        $p = $this->plano();
        // Gin Barin não tem par Barín: nunca cai no Gin da APTK
        $this->assertSame('sem_par', $p[90]['status']);
        $this->assertNotSame('APTK Gin Nº2 Neutral', $p[90]['product']?->name);
        // sem "barin" no nome nunca casa com produto Barín
        $this->assertNotSame($vodkaBarin->id, $p[91]['product']?->id);
    }

    public function test_nome_de_uma_palavra_fica_limitado_a_medio(): void
    {
        $this->produto('Gin BARIN', 'gin-barin', ['750 ml'], null, 'barin');
        $this->mp(95, 'GIN BARIN 750ML');

        $l = $this->plano()[95];
        $this->assertSame('medio', $l['status']);
        $this->assertSame(100.0, $l['score']);

        $this->vincular(['--aplicar' => true]);
        $this->assertNull(MicrovixProduct::where('codigoproduto', 95)->value('product_id'));
    }
}
