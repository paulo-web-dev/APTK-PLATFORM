<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\AppmaxService;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Leva 17: o valor cobrado na Appmax é subtotal − desconto + frete, igual ao
 * orders.total, em todos os métodos (Pix, cartão à vista e parcelado,
 * boleto). O frete gravado é o mesmo que a cotação do checkout mostrou
 * (/frete/cotar) — o servidor recalcula pela mesma função, do mesmo
 * carrinho e CEP, e nunca aceita valor vindo do front.
 *
 * Desconto: o checkout ainda não aplica cupom (discount sempre 0); a
 * fórmula com desconto é travada no AppmaxService::createOrder.
 */
class ValorCobradoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config([
            'microvix.envia_pedido'         => false,
            'appmax.environment'            => 'sandbox',
            'appmax.merchant_client_id'     => 'cid',
            'appmax.merchant_client_secret' => 'csecret',
            'appmax.max_installments'       => 3,
            'appmax.min_installment_value'  => 30.00,
            'appmax.http_retry_sleep_ms'    => 0,
        ]);

        Http::fake(function (Request $req) {
            $host = (string) parse_url($req->url(), PHP_URL_HOST);
            $chave = $req->method().' '.(str_starts_with($host, 'auth.') ? 'auth' : parse_url($req->url(), PHP_URL_PATH));

            return match ($chave) {
                'POST auth'                     => Http::response(['access_token' => 'bearer-x', 'expires_in' => 3600]),
                'POST /v1/customers'            => Http::response(['data' => ['customer' => ['id' => 10]]], 201),
                'POST /v1/orders'               => Http::response(['data' => ['order' => ['id' => 20]]], 201),
                'POST /v1/payments/credit-card' => Http::response(['data' => ['payment' => ['pay_reference' => 'ref-cc']]], 201),
                'POST /v1/payments/pix'         => Http::response(['data' => ['payment' => [
                    'pay_reference' => 'ref-pix', 'pix_qrcode' => 'iVBORw0KGgo', 'pix_emv' => '000201010212',
                    'pix_expiration_date' => now()->addHour()->format('Y-m-d H:i:s'),
                ]]]),
                'POST /v1/payments/boleto'      => Http::response(['data' => ['payment' => [
                    'pay_reference' => 'ref-bol', 'digitable_line' => '23790.00000 00000.000000 00000.000000 0 00000000000000',
                    'pdf' => 'https://appmax.test/boleto.pdf', 'due_date' => now()->addDays(3)->toDateString(),
                ]]]),
                default => Http::response(['error' => ['message' => "rota não mockada: {$chave}"]], 599),
            };
        });
    }

    /** Dois volumes, quantidades e centavos quebrados: subtotal 2 × 165,90 + 1 × 210,00 = 541,80. */
    protected function carrinho(): void
    {
        $p = Product::create([
            'name' => 'Negroni Clássico', 'slug' => 'negroni-classico', 'sku' => 'NC750', 'price' => 210.00,
            'sizes' => ['375 ml', '750 ml'], 'size_prices' => ['375 ml' => 165.90, '750 ml' => 210.00],
            'size_stock' => ['375 ml' => 10, '750 ml' => 10], 'stock_qty' => 30, 'active' => true,
        ]);
        $this->actingAs(User::create([
            'name' => 'Cliente', 'email' => 'cliente@aptk.test', 'password' => bcrypt('secret123'),
            'role' => 'customer', 'maior_idade_em' => now(),
        ]));
        app(CartService::class)->add($p->id, 2, '375 ml');
        app(CartService::class)->add($p->id, 1, '750 ml');
    }

    protected function dados(array $extra = []): array
    {
        return $extra + [
            'name' => 'Cliente Teste', 'phone' => '11 99999-0000', 'cpf' => '191.000.000-00',
            'zipcode' => '80000-000', 'street' => 'Rua XV', 'number' => '100', 'neighborhood' => 'Centro',
            'city' => 'Curitiba', 'state' => 'PR', 'shipping_method' => 'sedex',
            'appmax_token' => 'tok-cartao-123', 'card_name' => 'Cliente Teste',
        ];
    }

    protected function enviado(string $caminho): ?array
    {
        $req = collect(Http::recorded())->map(fn ($par) => $par[0])
            ->first(fn (Request $r) => $r->method() === 'POST' && str_ends_with((string) parse_url($r->url(), PHP_URL_PATH), $caminho));

        return $req?->data();
    }

    /** Valor que a tela do checkout mostrou para a opção. */
    protected function cotado(string $codigo): float
    {
        $opcoes = $this->postJson(route('frete.cotar'), ['zipcode' => '80000-000'])->assertOk()->json('opcoes');

        return (float) collect($opcoes)->firstWhere('codigo', $codigo)['valor'];
    }

    public static function caminhos(): array
    {
        return [
            'pix'              => ['pix', [], '/v1/payments/pix'],
            'cartão à vista'   => ['cartao', ['installments' => 1], '/v1/payments/credit-card'],
            'cartão parcelado' => ['cartao', ['installments' => 3], '/v1/payments/credit-card'],
            'boleto'           => ['boleto', [], '/v1/payments/boleto'],
            'pix com PAC'      => ['pix', ['shipping_method' => 'pac'], '/v1/payments/pix'],
        ];
    }

    /** @dataProvider caminhos */
    public function test_valor_cobrado_e_frete_batem_com_o_pedido_e_com_a_cotacao(string $metodo, array $extra, string $pagamento): void
    {
        $this->carrinho();
        $opcao = $extra['shipping_method'] ?? 'sedex';
        $frete = $this->cotado($opcao);
        $this->assertGreaterThan(0, $frete);

        $this->post('/checkout', $this->dados($extra + ['payment_method' => $metodo]))->assertRedirect();

        $order = Order::sole();

        // Pedido: frete = o cotado na tela; total = subtotal − desconto + frete.
        $this->assertEquals(541.80, (float) $order->subtotal);
        $this->assertEquals(0.0, (float) $order->discount);
        $this->assertEquals($frete, (float) $order->shipping_cost);
        $this->assertSame($opcao, $order->shipping_method);
        $this->assertEquals(round(541.80 + $frete, 2), (float) $order->total);

        // Appmax: os mesmos valores, em centavos.
        $pedido = $this->enviado('/v1/orders');
        $this->assertSame(54180, $pedido['products_value']);
        $this->assertSame(0, $pedido['discount_value']);
        $this->assertSame((int) round($frete * 100), $pedido['shipping_value']);
        $this->assertSame(
            $pedido['products_value'],
            collect($pedido['products'])->sum(fn ($p) => $p['unit_value'] * $p['quantity']),
        );
        $this->assertSame(
            (int) round((float) $order->total * 100),
            $pedido['products_value'] - $pedido['discount_value'] + $pedido['shipping_value'],
        );

        // Pagamento: cobra o pedido da Appmax (order_id), sem valor próprio
        // que pudesse divergir; parcelado só divide o mesmo total.
        $pg = $this->enviado($pagamento);
        $this->assertSame(20, $pg['order_id']);
        $this->assertEmpty(array_intersect_key($pg, array_flip(['value', 'amount', 'total', 'total_value'])));
        if ($metodo === 'cartao') {
            $this->assertSame($extra['installments'], $pg['payment_data']['credit_card']['installments']);
        }
    }

    public function test_valores_vindos_do_front_sao_ignorados(): void
    {
        $this->carrinho();
        $frete = $this->cotado('sedex');

        $this->post('/checkout', $this->dados([
            'payment_method' => 'pix',
            'shipping_cost'  => '0.00',
            'shipping_value' => '0',
            'discount'       => '500',
            'total'          => '1.00',
            'subtotal'       => '1.00',
        ]))->assertRedirect();

        $order = Order::sole();
        $this->assertEquals(0.0, (float) $order->discount);
        $this->assertEquals($frete, (float) $order->shipping_cost);
        $this->assertEquals(round(541.80 + $frete, 2), (float) $order->total);
        $this->assertSame((int) round($frete * 100), $this->enviado('/v1/orders')['shipping_value']);
    }

    public function test_cep_diferente_do_cotado_cobra_o_frete_do_cep_enviado(): void
    {
        $this->carrinho();
        $curitiba = $this->cotado('sedex');
        $sp = (float) collect($this->postJson(route('frete.cotar'), ['zipcode' => '01310-100'])->json('opcoes'))
            ->firstWhere('codigo', 'sedex')['valor'];
        $this->assertNotEquals($curitiba, $sp);

        $this->post('/checkout', $this->dados(['payment_method' => 'pix', 'zipcode' => '01310-100', 'city' => 'São Paulo', 'state' => 'SP']))
            ->assertRedirect();

        $this->assertEquals($sp, (float) Order::sole()->shipping_cost);
    }

    public function test_com_desconto_a_appmax_recebe_subtotal_menos_desconto_mais_frete(): void
    {
        $order = Order::create([
            'user_id' => User::create(['name' => 'C', 'email' => 'c@aptk.test', 'password' => bcrypt('x12345678')])->id,
            'status' => 'pending', 'payment_status' => 'pending', 'payment_method' => 'pix',
            'subtotal' => 541.80, 'discount' => 54.18, 'shipping_cost' => 86.40, 'total' => 574.02,
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_name' => 'Negroni Clássico', 'product_sku' => 'NC750',
            'size' => '375 ml', 'qty' => 2, 'unit_price' => 165.90, 'total_price' => 331.80]);
        OrderItem::create(['order_id' => $order->id, 'product_name' => 'Negroni Clássico', 'product_sku' => 'NC750',
            'size' => '750 ml', 'qty' => 1, 'unit_price' => 210.00, 'total_price' => 210.00]);

        app(AppmaxService::class)->createOrder($order->load('items'), 10);

        $pedido = $this->enviado('/v1/orders');
        $this->assertSame(54180, $pedido['products_value']);
        $this->assertSame(5418, $pedido['discount_value']);
        $this->assertSame(8640, $pedido['shipping_value']);
        $this->assertSame(57402, $pedido['products_value'] - $pedido['discount_value'] + $pedido['shipping_value']);
    }
}
