<?php

namespace Tests\Feature\Microvix;

use App\Jobs\EnviarPedidoMicrovix;
use App\Models\MicrovixLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AppmaxService;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Leva 08 — Microvix fora de operação: com MICROVIX_ENVIA_PEDIDO=false (o
 * padrão, inclusive sem a variável) e fonte "site", pedido, pagamento e
 * cancelamento seguem o fluxo normal e NADA vai para a fila do ERP — sem
 * log, sem estado de envio, sem erro.
 */
class MicrovixDesligadoTest extends TestCase
{
    use RefreshDatabase;

    /** Situação que a consulta à Appmax devolve (o webhook confirma antes de agir). */
    protected string $situacaoAppmax = 'pago';

    protected function setUp(): void
    {
        parent::setUp();

        $this->partialMock(AppmaxService::class, fn ($m) => $m
            ->shouldReceive('consultarSituacao')
            ->andReturnUsing(fn () => $this->situacaoAppmax));

        // Como no .env de produção hoje (ou variáveis ausentes).
        config([
            'microvix.envia_pedido'     => false,
            'microvix.fonte_da_verdade' => 'site',
            'appmax.webhook_token'      => 'tok-teste',
        ]);
    }

    protected function fechaPedido(): Order
    {
        $p = Product::firstOrCreate(['slug' => 'negroni-classico'], [
            'name'        => 'Negroni Clássico',
            'price'       => 210.00,
            'sizes'       => ['750 ml'],
            'size_prices' => ['750 ml' => 210.00],
            'stock_qty'   => 30,
            'active'      => true,
        ]);
        app(CartService::class)->add($p->id, 1, '750 ml');

        $user = User::firstOrCreate(['email' => 'cliente@aptk.test'], [
            'name' => 'Cliente', 'password' => bcrypt('secret123'), 'role' => 'customer',
        ]);

        return app(OrderService::class)->placeFromCart($user, [
            'name' => 'Cliente Teste', 'phone' => '11 99999-0000', 'cpf' => '111.444.777-35',
            'zipcode' => '01310-100', 'street' => 'Avenida Paulista', 'number' => '1000',
            'neighborhood' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP',
            'payment_method' => 'pix', 'shipping_method' => 'pac',
        ]);
    }

    public function test_pedido_pago_e_cancelado_nao_enfileiram_nada_pro_erp(): void
    {
        Queue::fake();

        $pago = $this->fechaPedido();
        $pago->update(['appmax_order_id' => 555]);
        $this->postJson('/webhooks/appmax/tok-teste', ['event' => 'order_paid', 'data' => ['id' => 555]])->assertOk();

        $cancelado = $this->fechaPedido();
        app(OrderService::class)->cancelAndRestoreStock($cancelado, 'expired');

        Queue::assertNotPushed(EnviarPedidoMicrovix::class);
        $this->assertSame(0, MicrovixLog::count());
        $this->assertSame(0, Order::whereNotNull('microvix_envio_estado')->count()); // nem erro, nem estado nenhum
        $this->assertSame('paid', $pago->fresh()->payment_status);
        $this->assertSame('cancelled', $cancelado->fresh()->status);
    }
}
