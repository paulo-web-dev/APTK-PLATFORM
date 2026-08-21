<?php

namespace Tests\Feature\Microvix;

use App\Models\MicrovixProduct;
use App\Models\Product;
use App\Services\Microvix\MicrovixMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MicrovixMatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function mp(array $attrs): MicrovixProduct
    {
        return MicrovixProduct::create($attrs + ['codigoproduto' => random_int(900001, 999999)]);
    }

    public function test_sugere_por_codauxiliar_casando_a_base_do_sku_e_o_volume(): void
    {
        Product::create(['name' => 'Cosmopolitan', 'slug' => 'cosmo', 'sku' => 'COSMO-750', 'sizes' => ['375 ml', '750 ml']]);
        $products = Product::all();

        $s = (new MicrovixMatcher())->sugerir(
            $this->mp(['codauxiliar1' => 'COSMO-375', 'nome_produto' => 'APTK COSMOPOLITAN 375ML']),
            $products,
        );

        $this->assertNotNull($s);
        $this->assertSame('Cosmopolitan', $s['product']->name);
        $this->assertSame('375 ml', $s['size']);
        $this->assertSame('sku', $s['criterio']);
        $this->assertSame('alta', $s['confianca']);
    }

    public function test_sugere_por_referencia_com_volume_vindo_do_nome_do_erp(): void
    {
        Product::create(['name' => 'Negroni Clássico', 'slug' => 'negroni', 'sku' => 'NEG-750', 'sizes' => ['375 ml', '750 ml']]);
        $products = Product::all();

        $s = (new MicrovixMatcher())->sugerir(
            $this->mp(['referencia' => 'NEGRONI', 'nome_produto' => 'APTK NEGRONI CLÁSSICO 750ML']),
            $products,
        );

        $this->assertNotNull($s);
        $this->assertSame('Negroni Clássico', $s['product']->name);
        $this->assertSame('750 ml', $s['size']);
        $this->assertSame('referência', $s['criterio']);
        $this->assertSame('média', $s['confianca']);
    }

    public function test_referencia_ambigua_e_desfeita_pelo_nome_completo_ou_descartada(): void
    {
        Product::create(['name' => 'Squadra Amêndoas', 'slug' => 'sq-amendoas', 'sku' => 'SQAM-750', 'sizes' => ['750 ml']]);
        Product::create(['name' => 'Squadra Coco', 'slug' => 'sq-coco', 'sku' => 'SQCO-750', 'sizes' => ['750 ml']]);
        $products = Product::all();
        $matcher = new MicrovixMatcher();

        // O nome do ERP contém o nome completo de UM dos dois → desambigua.
        $s = $matcher->sugerir(
            $this->mp(['referencia' => 'SQUADRA', 'nome_produto' => 'APTK SQUADRA COCO 750ML']),
            $products,
        );
        $this->assertSame('Squadra Coco', $s['product']->name);

        // Nome do ERP não resolve → sem sugestão, decisão humana.
        $this->assertNull($matcher->sugerir(
            $this->mp(['referencia' => 'SQUADRA', 'nome_produto' => 'SQUADRA ESPECIAL 750ML']),
            $products,
        ));
    }

    public function test_sem_correspondencia_ou_sem_volume_determinavel_nao_sugere(): void
    {
        Product::create(['name' => 'Negroni Clássico', 'slug' => 'negroni', 'sku' => 'NEG-750', 'sizes' => ['375 ml', '750 ml']]);
        $products = Product::all();
        $matcher = new MicrovixMatcher();

        // Produto que só existe no ERP.
        $this->assertNull($matcher->sugerir(
            $this->mp(['referencia' => 'GELO2KG', 'nome_produto' => 'GELO CRISTAL ICE4PROS 2KG']),
            $products,
        ));

        // Casa o produto mas o volume é indeterminável (dois volumes, nome sem ML).
        $this->assertNull($matcher->sugerir(
            $this->mp(['referencia' => 'NEGRONI', 'nome_produto' => 'APTK NEGRONI GARRAFA GRANDE']),
            $products,
        ));

        // Sem referencia nem codauxiliar não há critério.
        $this->assertNull($matcher->sugerir(
            $this->mp(['nome_produto' => 'APTK NEGRONI CLÁSSICO 750ML']),
            $products,
        ));
    }
}
