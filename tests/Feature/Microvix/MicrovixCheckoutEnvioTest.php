<?php

namespace Tests\Feature\Microvix;

use App\Jobs\EnviarPedidoMicrovix;
use App\Mail\PagamentoConfirmado;
use App\Mail\PedidoNovo;
use App\Models\MicrovixProduct;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Checkout × Linx: nada vai no fechamento do pedido; cartão aprovado na
 * hora agenda o envio (o webhook que chega depois acha o pedido já pago e
 * não agendaria). Pedido pago que não pode ir sozinho avisa no e-mail.
 */
class MicrovixCheckoutEnvioTest extends TestCase
{
    use MontaPedidoParaEnvio;
    use RefreshDatabase;

    protected array $rotas = [];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->configuraMicrovixParaEnvio();
        config([
            'pedidos.notificar'             => ['equipe@aptk.test'],
            'appmax.environment'            => 'sandbox',
            'appmax.merchant_client_id'     => 'cid',
            'appmax.merchant_client_secret' => 'csecret',
            'appmax.webhook_token'          => 'tok-teste',
            'appmax.max_installments'       => 3,
            'appmax.min_installment_value'  => 30.00,
            'appmax.http_retry_sleep_ms'    => 0,
        ]);

        $this->rotas = [
            'POST auth'                     => fn () => Http::response(['access_token' => 'bearer-x', 'expires_in' => 3600]),
            'POST /v1/customers'            => fn () => Http::response(['data' => ['customer' => ['id' => 10]]], 201),
            'POST /v1/orders'               => fn () => Http::response(['data' => ['order' => ['id' => 20]]], 201),
            'POST /v1/payments/credit-card' => fn () => Http::response(['data' => ['payment' => ['pay_reference' => 'ref-cc']]], 201),
            'POST /v1/payments/pix'         => fn () => Http::response(['data' => ['payment' => [
                'pay_reference' => 'ref-pix', 'pix_qrcode' => 'iVBORw0KGgo', 'pix_emv' => '000201010212',
                'pix_expiration_date' => now()->addHour()->format('Y-m-d H:i:s'),
            ]]]),
        ];

        Http::fake(function (Request $req) {
            $host = (string) parse_url($req->url(), PHP_URL_HOST);
            if (str_contains($host, 'microvix')) {
                return Http::response('linx não deveria ser chamada neste teste', 599);
            }
            $chave = $req->method().' '.(str_starts_with($host, 'auth.') ? 'auth' : parse_url($req->url(), PHP_URL_PATH));
            $rota = $this->rotas[$chave] ?? null;

            return $rota ? $rota($req) : Http::response(['error' => ['message' => "rota não mockada: {$chave}"]], 599);
        });
    }

    protected function carrinho(bool $vinculado = true): Product
    {
        $p = Product::create([
            'name' => 'Negroni Clássico', 'slug' => 'negroni-classico', 'sku' => 'NC750', 'price' => 210.00,
            'sizes' => ['750 ml'], 'size_prices' => ['750 ml' => 210.00],
            'size_stock' => ['750 ml' => 5], 'stock_qty' => 30, 'active' => true,
        ]);
        if ($vinculado) {
            MicrovixProduct::create(['codigoproduto' => 900123, 'product_id' => $p->id, 'size' => '750 ml']);
        }

        $this->actingAs(User::create([
            'name' => 'Cliente', 'email' => 'cliente@aptk.test', 'password' => bcrypt('secret123'),
            'role' => 'customer', 'maior_idade_em' => now(),
        ]));
        app(CartService::class)->add($p->id, 1, '750 ml');

        return $p;
    }

    protected function dados(array $extra = []): array
    {
        return $extra + [
            'name' => 'Cliente Teste', 'phone' => '11 99999-0000', 'cpf' => '191.000.000-00',
            'zipcode' => '80000-000', 'street' => 'Rua XV', 'number' => '100', 'neighborhood' => 'Centro',
            'city' => 'Curitiba', 'state' => 'PR', 'payment_method' => 'cartao', 'shipping_method' => 'sedex',
            'appmax_token' => 'tok-cartao-123', 'card_name' => 'Cliente Teste', 'installments' => 2,
        ];
    }

    public function test_cartao_aprovado_na_hora_agenda_o_envio(): void
    {
        Queue::fake();
        $this->carrinho();

        $this->post('/checkout', $this->dados())->assertRedirect();

        $order = Order::sole();
        $this->assertTrue($order->isPaid());
        Queue::assertPushed(EnviarPedidoMicrovix::class, fn ($j) => $j->orderId === $order->id);
        Queue::assertPushed(EnviarPedidoMicrovix::class, 1);
    }

    public function test_pix_aguardando_pagamento_nao_agenda_nada(): void
    {
        Queue::fake();
        $this->carrinho();

        $this->post('/checkout', $this->dados(['payment_method' => 'pix']))->assertRedirect();

        $this->assertFalse(Order::sole()->isPaid());
        Queue::assertNotPushed(EnviarPedidoMicrovix::class);
    }

    public function test_pedido_pago_sem_vinculo_avisa_no_email_para_lancar_manualmente(): void
    {
        // Fila sync: o job roda inline, cai em manual sem chamar a Linx.
        $this->carrinho(vinculado: false);

        $this->post('/checkout', $this->dados())->assertRedirect();

        $order = Order::sole();
        $this->assertSame('manual', $order->microvix_envio_estado);
        Mail::assertSent(PedidoNovo::class, fn (PedidoNovo $m) => str_contains($m->render(), 'Lançar manualmente na Linx: item sem vínculo com a Linx (Negroni Clássico 750 ml)'));
    }

    public function test_pagamento_confirmado_tambem_avisa_quando_e_manual(): void
    {
        $order = $this->pedidoParaEnvio('cartao', ['payment_details' => ['type' => 'cartao', 'installments' => 6]]);

        $html = (new PagamentoConfirmado($order))->render();
        $this->assertStringContainsString('Lançar manualmente na Linx: sem plano de pagamento na Linx para cartão 6x', $html);
    }

    public function test_pedido_que_vai_sozinho_nao_traz_aviso_de_lancamento(): void
    {
        $order = $this->pedidoParaEnvio('pix');

        $this->assertStringNotContainsString('Lançar manualmente', (new PedidoNovo($order))->render());
        $this->assertStringNotContainsString('Lançar manualmente', (new PagamentoConfirmado($order))->render());

        // Anterior à integração também não: não é com a Linx.
        $antigo = $this->pedidoParaEnvio('cartao', ['created_at' => now()->subDays(5), 'payment_details' => ['installments' => 6]]);
        $this->assertStringNotContainsString('Lançar manualmente', (new PedidoNovo($antigo))->render());
    }

    public function test_checkout_recusa_cidade_em_sigla_ou_curta(): void
    {
        Queue::fake();
        $this->carrinho();

        foreach (['VP', 'SBC', 'SP', 'R.J'] as $cidade) {
            $this->post('/checkout', $this->dados(['city' => $cidade]))
                ->assertSessionHasErrors(['city' => \App\Rules\Cidade::MENSAGEM]);
        }
        $this->assertSame(0, Order::count());

        $this->post('/checkout', $this->dados(['city' => 'Ubá']))->assertSessionHasNoErrors();
        $this->assertSame('Ubá', Order::sole()->ship_city);
    }

    /** @dataProvider cidades */
    public function test_regra_de_cidade(string $cidade, bool $valida): void
    {
        $this->assertSame($valida, \App\Rules\Cidade::valida($cidade));
    }

    public static function cidades(): array
    {
        return [
            'sigla de 2'        => ['VP', false],
            'sigla de 3'        => ['SBC', false],
            'curta'             => ['Rí', false],
            'vazia'             => ['  ', false],
            'nome curto ok'     => ['Ubá', true],
            'caixa alta longa'  => ['SAO PAULO', true],
            'composto'          => ["Santa Bárbara d'Oeste", true],
            'normal'            => ['Curitiba', true],
        ];
    }
}
