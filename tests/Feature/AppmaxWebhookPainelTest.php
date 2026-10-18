<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Webhooks do PAINEL (loja própria, doc guides/webhooks-painel): event em
 * PascalCase, data.id = pedido Appmax, sem assinatura. O controller
 * confirma o status em GET /v1/orders/{id} antes de mudar qualquer coisa.
 */
class AppmaxWebhookPainelTest extends TestCase
{
    use RefreshDatabase;

    protected string $statusNaApi = 'aprovado';

    protected int $consultas = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config([
            'appmax.webhook_token'       => 'tok-teste',
            'appmax.client_id'           => 'cid',
            'appmax.client_secret'       => 'csecret',
            'appmax.api_url'             => 'https://api.appmax.com.br',
            'appmax.auth_url'            => 'https://auth.appmax.com.br/oauth2/token',
            'appmax.http_retry_sleep_ms' => 0,
        ]);

        Http::fake(function (Request $req) {
            if (str_starts_with((string) parse_url($req->url(), PHP_URL_HOST), 'auth.')) {
                return Http::response(['access_token' => 'bearer-x', 'expires_in' => 3600]);
            }
            $this->consultas++;

            return $this->statusNaApi === 'fora'
                ? throw new ConnectionException('fora do ar')
                : Http::response(['data' => ['order' => ['id' => 99042, 'status' => $this->statusNaApi]]]);
        });
    }

    protected function pedido(string $metodo = 'cartao'): Order
    {
        $p = Product::create([
            'name' => 'Negroni Clássico', 'slug' => 'negroni-classico', 'sku' => 'NC750', 'price' => 210.00,
            'sizes' => ['750 ml'], 'size_prices' => ['750 ml' => 210.00], 'size_stock' => ['750 ml' => 5],
            'stock_qty' => 30, 'active' => true,
        ]);
        $user = User::create(['name' => 'C', 'email' => 'c@aptk.test', 'password' => bcrypt('x12345678'), 'role' => 'customer']);
        app(CartService::class)->add($p->id, 1, '750 ml');

        $order = app(OrderService::class)->placeFromCart($user, [
            'name' => 'Cliente', 'phone' => '11999990000', 'cpf' => '191.000.000-00', 'zipcode' => '01310-100',
            'street' => 'Av. Paulista', 'number' => '1000', 'neighborhood' => 'Bela Vista', 'city' => 'São Paulo',
            'state' => 'SP', 'payment_method' => $metodo, 'shipping_method' => 'pac',
        ]);
        $order->update(['appmax_order_id' => 99042]);

        return $order->refresh();
    }

    /** Entrega como a doc mostra: environment + event + data (resumido). */
    protected function entrega(string $event, array $extra = [])
    {
        return $this->withHeaders(['User-Agent' => 'apphook'])->postJson('/webhooks/appmax/tok-teste', [
            'environment' => 'production',
            'event'       => $event,
            'data'        => $extra + [
                'id'             => 99042,
                'customer_id'    => 17,
                'status'         => 'aprovado',
                'payment_type'   => 'CreditCard',
                'card_brand'     => 'Visa',
                'installments'   => 12,
                'total_products' => 242.03,
                'freight_value'  => 39.99,
                'freight_type'   => 'PAC',
                'discount'       => 0,
                'interest'       => 57.97,
                'total'          => 339.99,
                'paid_at'        => '2026-10-01 15:02:52',
                'created_at'     => '2026-10-01 15:02:36',
                'origin'         => 'API',
                'customer'       => ['id' => 17, 'firstname' => 'Maria', 'lastname' => 'Silva', 'email' => 'maria@exemplo.com', 'document_number' => '00000000000'],
                'issuer_message' => null,
            ],
        ]);
    }

    public function test_order_paid_do_painel_confirmado_pela_api_aprova(): void
    {
        $order = $this->pedido();

        $this->entrega('OrderPaid')->assertOk();

        $this->assertTrue($order->refresh()->isPaid());
        $this->assertSame('processing', $order->status);
        $this->assertSame(1, $this->consultas);
    }

    public function test_pix_pago_aprova(): void
    {
        $pix = $this->pedido('pix');
        $this->entrega('OrderPaidByPix', ['payment_type' => 'Pix'])->assertOk();
        $this->assertTrue($pix->refresh()->isPaid());
    }

    public function test_evento_de_pago_que_a_api_nao_confirma_nao_muda_nada(): void
    {
        // Evento forjado/atrasado: o status vem da API, não do corpo.
        $order = $this->pedido();
        $this->statusNaApi = 'pendente';

        $this->entrega('OrderApproved')->assertOk();

        $this->assertSame('pending', $order->refresh()->payment_status);
    }

    public function test_autorizado_antifraude_nao_marca_pago(): void
    {
        $order = $this->pedido();
        $this->statusNaApi = 'autorizado';

        $this->entrega('OrderAuthorized')->assertOk();
        $this->assertFalse($order->refresh()->isPaid());

        // Depois do antifraude a API diz aprovado e chega o OrderApproved.
        $this->statusNaApi = 'aprovado';
        $this->entrega('OrderApproved')->assertOk();
        $this->assertTrue($order->refresh()->isPaid());
    }

    public function test_api_fora_do_ar_responde_503_para_a_appmax_retentar(): void
    {
        $order = $this->pedido();
        $this->statusNaApi = 'fora';

        $this->entrega('OrderPaid')->assertStatus(503);

        $this->assertSame('pending', $order->refresh()->payment_status);
    }

    public function test_pagamento_nao_autorizado_e_recusa_por_risco_cancelam_e_devolvem_estoque(): void
    {
        foreach (['PaymentNotAuthorized', 'PaymentNotAuthorizedWithDelay', 'OrderRefusedByRisk'] as $i => $event) {
            Order::query()->delete();
            Product::query()->delete();
            User::query()->delete();
            $order = $this->pedido();
            $this->statusNaApi = $event === 'OrderRefusedByRisk' ? 'recusado_por_risco' : 'cancelado';

            $this->entrega($event, ['status' => 'cancelado', 'issuer_message' => 'Saldo insuficiente'])->assertOk();

            $this->assertSame('cancelled', $order->refresh()->status, $event);
            $this->assertSame(5, Product::sole()->size_stock['750 ml'], $event);
        }
    }

    public function test_pix_expirado_e_boleto_vencido_cancelam(): void
    {
        $order = $this->pedido('pix');
        $this->statusNaApi = 'cancelado';

        $this->entrega('OrderPixExpired')->assertOk();

        $this->assertSame('cancelled', $order->refresh()->status);
        $this->assertSame('expired', $order->payment_status);
    }

    public function test_boleto_vencido_cancela(): void
    {
        $order = $this->pedido('boleto');
        $this->statusNaApi = 'pendente'; // boleto vencido nunca pago fica "pendente" na API

        $this->entrega('OrderBilletOverdue')->assertOk();

        $this->assertSame('expired', $order->refresh()->payment_status);
    }

    public function test_estorno_e_chargeback(): void
    {
        $order = $this->pedido();
        $this->entrega('OrderPaid')->assertOk();

        $this->statusNaApi = 'estornado';
        $this->entrega('OrderRefund')->assertOk();
        $this->assertSame('refunded', $order->refresh()->payment_status);
        $this->assertSame('cancelled', $order->status);

        $this->statusNaApi = 'chargeback_em_tratativa';
        $this->entrega('OrderChargeBackInTreatment')->assertOk();
        $this->assertSame('chargeback', $order->refresh()->payment_status);
    }

    public function test_estorno_que_a_api_nao_confirma_nao_cancela_pedido_pago(): void
    {
        $order = $this->pedido();
        $this->entrega('OrderPaid')->assertOk();

        $this->entrega('OrderRefund')->assertOk(); // API ainda diz aprovado

        $this->assertTrue($order->refresh()->isPaid());
    }

    public function test_estorno_parcial_so_registra_incidente(): void
    {
        $order = $this->pedido();
        $this->entrega('OrderPaid')->assertOk();

        $this->entrega('OrderPartialRefund', ['refund_amount' => 10.5])->assertOk();
        $this->entrega('OrderPartialRefund', ['refund_amount' => 10.5])->assertOk(); // reentrega

        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertCount(1, $order->incidentes());
        $this->assertStringContainsString('R$ 10,50', $order->incidentes()[0]['texto']);
    }

    public function test_token_errado_e_evento_desconhecido(): void
    {
        $this->pedido();

        $this->postJson('/webhooks/appmax/errado', ['event' => 'OrderPaid', 'data' => ['id' => 99042]])->assertForbidden();
        $this->entrega('CustomerCreated')->assertOk()->assertJson(['ignored' => 'evento sem tratamento']);
        $this->assertSame(0, $this->consultas);
    }

    public function test_formato_appstore_continua_aceito(): void
    {
        $order = $this->pedido();

        $this->postJson('/webhooks/appmax/tok-teste', ['event' => 'order_approved', 'data' => ['id' => 99042]])->assertOk();

        $this->assertTrue($order->refresh()->isPaid());
    }
}
