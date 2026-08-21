<?php

namespace Tests\Feature\Microvix;

use App\Models\MicrovixProduct;
use App\Models\Product;
use Database\Seeders\MicrovixFakeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MicrovixFakeSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Catálogo mínimo cobrindo os formatos reais: com e sem size_prices. */
    protected function criaCatalogo(): void
    {
        Product::create([
            'name'        => 'Negroni Clássico',
            'slug'        => 'negroni-classico',
            'sku'         => 'NEG-750',
            'price'       => 149.90,
            'sizes'       => ['375 ml', '750 ml'],
            'size_prices' => ['375 ml' => 123.44, '750 ml' => 189.90],
        ]);

        Product::create([
            'name'  => 'Penicillin',
            'slug'  => 'penicillin',
            'sku'   => 'PENI-375',
            'price' => 199.90,
            'sizes' => ['375 ml'],
            // size_prices null de propósito: 5 dos 14 produtos reais são assim.
        ]);

        Product::create([
            'name'        => 'Fitzgerald',
            'slug'        => 'fitzgerald',
            'sku'         => 'FITZ-750',
            'price'       => 139.90,
            'sizes'       => ['375 ml', '750 ml'],
            'size_prices' => ['375 ml' => 116.94, '750 ml' => 179.90],
        ]);
    }

    public function test_gera_um_registro_por_produto_e_volume_mais_os_sem_correspondencia(): void
    {
        $this->criaCatalogo();

        (new MicrovixFakeSeeder())->run();

        // 5 combinações produto+volume + 3 só-no-ERP.
        $this->assertSame(8, MicrovixProduct::count());
        $this->assertSame(
            3,
            MicrovixProduct::where('codigoproduto', '>=', MicrovixFakeSeeder::CODIGO_SO_NO_ERP)->count(),
        );

        // Vínculo NUNCA vem preenchido do seeder — é decisão humana na tela.
        $this->assertSame(0, MicrovixProduct::whereNotNull('product_id')->count());

        // Todos na faixa fake, removível sem tocar em dado do ERP real.
        $this->assertSame(0, MicrovixProduct::where('codigoproduto', '<', MicrovixFakeSeeder::CODIGO_BASE)->count());
    }

    public function test_saldos_cobrem_os_casos_limite(): void
    {
        $this->criaCatalogo();

        (new MicrovixFakeSeeder())->run();

        // O primeiro produto com dois volumes fica com um volume em estoque
        // e o outro zerado — o caso mais importante da conciliação.
        $negroni375 = MicrovixProduct::where('nome_produto', 'like', '%NEGRONI%375ML')->firstOrFail();
        $negroni750 = MicrovixProduct::where('nome_produto', 'like', '%NEGRONI%750ML')->firstOrFail();
        $this->assertGreaterThan(0, (float) $negroni375->saldo);
        $this->assertSame(0.0, (float) $negroni750->saldo);

        // Saldo 1 (última unidade) e saldo alto também existem na carga.
        $saldos = MicrovixProduct::pluck('saldo')->map(fn ($s) => (float) $s);
        $this->assertContains(1.0, $saldos);
        $this->assertTrue($saldos->max() >= 100);
    }

    public function test_precos_divergem_do_site_e_ha_registros_inativos(): void
    {
        $this->criaCatalogo();

        (new MicrovixFakeSeeder())->run();

        // Para cada combinação, o preço fake difere do priceForSize() atual —
        // senão a conciliação não teria nada para mostrar.
        $combos = MicrovixProduct::where('codigoproduto', '<', MicrovixFakeSeeder::CODIGO_SO_NO_ERP)->get();
        $precosSite = Product::all()->flatMap(
            fn (Product $p) => collect($p->sizes)->map(fn ($s) => round($p->priceForSize($s), 2)),
        );

        foreach ($combos as $mp) {
            $this->assertNotContains(round((float) $mp->preco, 2), $precosSite, "codigoproduto {$mp->codigoproduto}");
        }

        $this->assertGreaterThan(0, MicrovixProduct::where('ativo', false)->count());
    }

    public function test_rodar_duas_vezes_nao_duplica(): void
    {
        $this->criaCatalogo();

        $seeder = new MicrovixFakeSeeder();
        $seeder->run();
        $antes = MicrovixProduct::count();
        $seeder->run();

        $this->assertSame($antes, MicrovixProduct::count());
    }

    public function test_limpar_remove_so_a_faixa_fake(): void
    {
        $this->criaCatalogo();

        // Registro como se tivesse vindo do ERP real (fora da faixa fake).
        MicrovixProduct::create(['codigoproduto' => 12837, 'nome_produto' => 'REAL DO ERP']);

        (new MicrovixFakeSeeder())->run();
        $this->artisan('microvix:fake', ['--limpar' => true])->assertSuccessful();

        $this->assertSame(1, MicrovixProduct::count());
        $this->assertNotNull(MicrovixProduct::where('codigoproduto', 12837)->first());
    }

    public function test_comando_e_bloqueado_em_producao(): void
    {
        $this->criaCatalogo();
        $this->app['env'] = 'production';

        $this->artisan('microvix:fake')->assertFailed();

        $this->assertSame(0, MicrovixProduct::count());
    }
}
