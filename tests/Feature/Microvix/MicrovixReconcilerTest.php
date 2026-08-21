<?php

namespace Tests\Feature\Microvix;

use App\Exceptions\MicrovixException;
use App\Models\MicrovixProduct;
use App\Models\MicrovixReconcileLog;
use App\Models\Product;
use App\Services\Microvix\MicrovixReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MicrovixReconcilerTest extends TestCase
{
    use RefreshDatabase;

    protected Product $negroni;

    protected Product $penicillin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->negroni = Product::create([
            'name'        => 'Negroni Clássico',
            'slug'        => 'negroni-classico',
            'description' => 'Alquimia de histórias em garrafa.',
            'sku'         => 'NEG-750',
            'price'       => 149.90,
            'sizes'       => ['375 ml', '750 ml'],
            'size_prices' => ['375 ml' => 123.44, '750 ml' => 189.90],
            'stock_qty'   => 30,
            'active'      => true,
        ]);

        // O caso dos 5 reais: sizes sem size_prices — exibe o price base.
        $this->penicillin = Product::create([
            'name'   => 'Penicillin',
            'slug'   => 'penicillin',
            'sku'    => 'PENI-375',
            'price'  => 199.90,
            'sizes'  => ['375 ml'],
            'active' => true,
        ]);

        MicrovixProduct::create([
            'codigoproduto' => 900001,
            'product_id'    => $this->negroni->id,
            'size'          => '375 ml',
            'saldo'         => 3,
            'preco'         => 131.21,
        ]);

        MicrovixProduct::create([
            'codigoproduto' => 900013,
            'product_id'    => $this->penicillin->id,
            'size'          => '375 ml',
            'saldo'         => 18,
            'preco'         => 207.67,
        ]);

        // Sem vínculo: tem que ser ignorado, nunca criar produto.
        MicrovixProduct::create([
            'codigoproduto' => 900101,
            'nome_produto'  => 'GELO CRISTAL ICE4PROS 2KG',
            'saldo'         => 500,
            'preco'         => 24.90,
        ]);
    }

    public function test_plano_lista_deltas_de_preco_com_valor_atual_e_do_erp(): void
    {
        $plano = (new MicrovixReconciler())->plano();

        $precos = array_values(array_filter($plano['mudancas'], fn ($m) => $m['campo'] === 'preco'));
        $this->assertCount(2, $precos);

        $negroni = collect($precos)->firstWhere('codigoproduto', 900001);
        $this->assertSame(123.44, $negroni['de']);
        $this->assertSame(131.21, $negroni['para']);
        $this->assertSame(123.44, $negroni['exibido_hoje']);
        $this->assertFalse($negroni['sem_size_prices']);

        // Penicillin não tem size_prices: de=null, exibido hoje é o price
        // base — e a flag marca que o preço exibido muda no ato da gravação.
        $peni = collect($precos)->firstWhere('codigoproduto', 900013);
        $this->assertNull($peni['de']);
        $this->assertSame(199.90, $peni['exibido_hoje']);
        $this->assertSame(207.67, $peni['para']);
        $this->assertTrue($peni['sem_size_prices']);
    }

    public function test_com_fonte_site_o_comando_sem_flag_roda_dry_run_forcado_e_nao_grava(): void
    {
        config(['microvix.fonte_da_verdade' => 'site']);

        $this->artisan('microvix:conciliar')
            ->expectsOutputToContain('dry-run FORÇADO')
            ->expectsOutputToContain('nada foi gravado')
            ->assertSuccessful();

        $this->assertSame(['375 ml' => 123.44, '750 ml' => 189.90], $this->negroni->refresh()->size_prices);
        $this->assertNull($this->negroni->size_stock);
        $this->assertSame(0, MicrovixReconcileLog::count());
    }

    public function test_executar_com_fonte_site_lanca_excecao_no_proprio_service(): void
    {
        config(['microvix.fonte_da_verdade' => 'site']);

        $this->expectException(MicrovixException::class);

        (new MicrovixReconciler())->executar();
    }

    public function test_com_fonte_erp_grava_saldo_preco_e_loga_de_para(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);

        $this->artisan('microvix:conciliar')->assertSuccessful();

        $negroni = $this->negroni->refresh();
        $this->assertSame(3, $negroni->size_stock['375 ml']);
        $this->assertSame(131.21, (float) $negroni->size_prices['375 ml']);
        // O volume sem espelho mantém o que estava.
        $this->assertSame(189.90, (float) $negroni->size_prices['750 ml']);

        // Editorial intocado.
        $this->assertSame('Negroni Clássico', $negroni->name);
        $this->assertSame('Alquimia de histórias em garrafa.', $negroni->description);
        $this->assertTrue($negroni->active);

        // Penicillin ganhou size_prices e o preço exibido agora é o do ERP.
        $peni = $this->penicillin->refresh();
        $this->assertSame(207.67, $peni->priceForSize('375 ml'));

        // Log com valor anterior e novo — o caminho de volta.
        $logPreco = MicrovixReconcileLog::where('campo', 'preco')->where('product_id', $negroni->id)->firstOrFail();
        $this->assertSame(123.44, (float) $logPreco->de);
        $this->assertSame(131.21, (float) $logPreco->para);

        $logPeni = MicrovixReconcileLog::where('campo', 'preco')->where('product_id', $peni->id)->firstOrFail();
        $this->assertNull($logPeni->de);

        $logSaldo = MicrovixReconcileLog::where('campo', 'saldo')->where('product_id', $negroni->id)->firstOrFail();
        $this->assertNull($logSaldo->de);
        $this->assertSame(3.0, (float) $logSaldo->para);
    }

    public function test_erp_sem_vinculo_e_ignorado_e_nao_vira_produto(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);

        (new MicrovixReconciler())->executar();

        $this->assertSame(2, Product::count());
        $this->assertSame(0, MicrovixReconcileLog::where('codigoproduto', 900101)->count());
    }

    public function test_todos_os_volumes_zerados_deixa_sem_estoque_mas_nao_inativa(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);
        MicrovixProduct::where('codigoproduto', 900013)->update(['saldo' => 0]);

        (new MicrovixReconciler())->executar();

        $peni = $this->penicillin->refresh();
        $this->assertSame(0, $peni->size_stock['375 ml']);
        $this->assertTrue($peni->active); // active é decisão do time, não do ERP
    }

    public function test_volume_sem_espelho_vira_aviso_e_mantem_valor(): void
    {
        $plano = (new MicrovixReconciler())->plano();

        $aviso = collect($plano['avisos'])->first(fn ($a) => str_contains($a, '750 ml'));
        $this->assertNotNull($aviso);
        $this->assertStringContainsString('Negroni Clássico', $aviso);
        $this->assertStringContainsString('mantém o valor atual', $aviso);
    }

    public function test_conciliar_duas_vezes_nao_gera_log_duplicado(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);
        $reconciler = new MicrovixReconciler();

        $reconciler->executar();
        $antes = MicrovixReconcileLog::count();
        $reconciler->executar(); // segunda rodada: nada mudou, nada a logar

        $this->assertSame($antes, MicrovixReconcileLog::count());
    }
}
