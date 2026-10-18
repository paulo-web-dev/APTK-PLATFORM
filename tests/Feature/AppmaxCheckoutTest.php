<?php

namespace Tests\Feature;

use App\Exceptions\AppmaxException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AppmaxService;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Checkout × Appmax depois da auditoria: cartão só com token, cobrança
 * indefinida não cancela, reconciliação pela consulta do pedido, webhook
 * que reativa pedido cancelado, parcelas sem juros, Pix com vencimento e
 * mensagens amigáveis (texto cru só no admin).
 *
 * A Appmax é simulada no nível HTTP (Http::fake) — o AppmaxService real roda.
 */
class AppmaxCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake(); // Microvix fora do caminho
        config([
            'appmax.environment'            => 'sandbox',
            'appmax.merchant_client_id'     => 'cid',
            'appmax.merchant_client_secret' => 'csecret',
            'appmax.webhook_token'          => 'tok-teste',
            'appmax.max_installments'       => 3,
            'appmax.min_installment_value'  => 30.00,
        ]);
    }

    protected function produto(array $sizeStock = ['375 ml' => 5, '750 ml' => 5]): Product
    {
        return Product::create([
            'name'        => 'Negroni Clássico',
            'slug'        => 'negroni-classico',
            'sku'         => 'NC750',
            'price'       => 210.00,
            'sizes'       => ['375 ml', '750 ml'],
            'size_prices' => ['375 ml' => 165.00, '750 ml' => 210.00],
            'size_stock'  => $sizeStock,
            'stock_qty'   => 30,
            'active'      => true,
        ]);
    }

    protected function cliente(): User
    {
        return User::create([
            'name'     => 'Cliente',
            'email'    => 'cliente@aptk.test',
            'password' => bcrypt('secret123'),
            'role'     => 'customer',
        ]);
    }

    /** Curitiba, 750 ml: SEDEX 86,40 (ver FreteTest) → total 296,40. */
    protected function dados(array $extra = []): array
    {
        return $extra + [
            'name'            => 'Cliente Teste',
            'phone'           => '11 99999-0000',
            'cpf'             => '191.000.000-00',
            'zipcode'         => '80000-000',
            'street'          => 'Rua XV',
            'number'          => '100',
            'neighborhood'    => 'Centro',
            'city'            => 'Curitiba',
            'state'           => 'PR',
            'payment_method'  => 'cartao',
            'shipping_method' => 'sedex',
            'appmax_token'    => 'tok-cartao-123',
            'card_name'       => 'Cliente Teste',
            'installments'    => 3,
        ];
    }

    protected function comCarrinho(): Product
    {
        $p = $this->produto();
        $this->actingAs($this->cliente());
        app(CartService::class)->add($p->id, 1, '750 ml');

        return $p;
    }

    /** Rotas vigentes do fake (Http::fake empilha: registra uma vez só). */
    protected array $rotas = [];

    protected bool $fakeRegistrado = false;

    /**
     * Appmax de mentira, roteada por "MÉTODO caminho". Rotas sobrescritas
     * recebem a Request; sem rota = 599 (falha o teste de forma visível).
     * Chamar de novo troca as rotas.
     */
    protected function fakeAppmax(array $over = []): void
    {
        $this->rotas = $over + [
            'POST auth'                     => fn () => Http::response(['access_token' => 'bearer-x', 'expires_in' => 3600]),
            'POST /v1/customers'            => fn () => Http::response(['data' => ['customer' => ['id' => 10]]], 201),
            'POST /v1/orders'               => fn () => Http::response(['data' => ['order' => ['id' => 20]]], 201),
            'POST /v1/payments/credit-card' => fn () => Http::response(['data' => ['payment' => ['pay_reference' => 'ref-cc']]], 201),
            'POST /v1/payments/pix'         => fn () => Http::response(['data' => ['payment' => [
                'pay_reference'       => 'ref-pix',
                'pix_qrcode'          => 'iVBORw0KGgo',
                'pix_emv'             => '000201010212',
                'pix_expiration_date' => '2026-10-18 15:00:00',
            ]]]),
            'GET /v1/orders/20'             => fn () => Http::response(['data' => ['order' => ['id' => 20, 'status' => 'pendente']]]),
        ];

        if ($this->fakeRegistrado) {
            return;
        }
        $this->fakeRegistrado = true;

        Http::fake(function (Request $req) {
            $host = (string) parse_url($req->url(), PHP_URL_HOST);
            $chave = $req->method().' '.(str_starts_with($host, 'auth.') ? 'auth' : parse_url($req->url(), PHP_URL_PATH));
            $rota = $this->rotas[$chave] ?? null;

            return $rota ? $rota($req) : Http::response(['error' => ['message' => "rota não mockada: {$chave}"]], 599);
        });
    }

    protected function enviado(string $metodo, string $caminho): ?array
    {
        $req = collect(Http::recorded())
            ->map(fn ($par) => $par[0])
            ->first(fn (Request $r) => $r->method() === $metodo && str_ends_with((string) parse_url($r->url(), PHP_URL_PATH), $caminho));

        return $req?->data();
    }

    // ---- 1. cartão só com token -----------------------------------------

    public function test_request_com_numero_ou_cvv_e_recusado_e_logado_sem_os_valores(): void
    {
        $this->fakeAppmax();
        $p = $this->comCarrinho();
        Log::spy();

        foreach ([['card_number' => '4000 0000 0000 0010'], ['card_cvv' => '123'], ['card-number' => '4000000000000010'], ['cvv' => '123']] as $cru) {
            $this->post('/checkout', $this->dados($cru))
                ->assertRedirect(route('checkout.index'))
                ->assertSessionHas('payment_error', 'Não foi possível validar o cartão. Confira os dados e tente de novo, ou escolha Pix ou boleto.');
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(5, $p->refresh()->size_stock['750 ml']);
        Http::assertNothingSent();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($msg, $ctx) => str_contains($msg, 'dado cru de cartão')
                && ! str_contains(json_encode($ctx), '4000')
                && ! str_contains(json_encode($ctx), '123'))
            ->times(4);
    }

    public function test_dado_cru_e_recusado_ate_no_pix(): void
    {
        $this->fakeAppmax();
        $this->comCarrinho();

        $this->post('/checkout', $this->dados(['payment_method' => 'pix', 'card_number' => '4000000000000010']))
            ->assertSessionHas('payment_error');

        $this->assertSame(0, Order::count());
    }

    public function test_cartao_sem_token_e_recusado(): void
    {
        $this->fakeAppmax();
        $this->comCarrinho();

        $this->post('/checkout', $this->dados(['appmax_token' => '']))
            ->assertSessionHasErrors(['appmax_token' => 'Não foi possível validar o cartão. Confira os dados e tente de novo, ou escolha Pix ou boleto.']);

        $this->assertSame(0, Order::count());
        Http::assertNothingSent();
    }

    public function test_cartao_aprovado_manda_so_o_token_e_valores_em_centavos(): void
    {
        $this->fakeAppmax();
        $this->comCarrinho();

        $this->post('/checkout', $this->dados())->assertRedirect();

        $order = Order::sole();
        $this->assertTrue($order->isPaid());
        $this->assertSame('processing', $order->status);

        $cc = $this->enviado('POST', '/v1/payments/credit-card')['payment_data']['credit_card'];
        $this->assertSame('tok-cartao-123', $cc['token']);
        $this->assertSame(3, $cc['installments']);
        $this->assertArrayNotHasKey('number', $cc);
        $this->assertArrayNotHasKey('cvv', $cc);

        // Valor na Appmax = itens − desconto + frete, em centavos inteiros.
        $pedido = $this->enviado('POST', '/v1/orders');
        $this->assertSame(21000, $pedido['products_value']);
        $this->assertSame(8640, $pedido['shipping_value']);
        $this->assertSame(0, $pedido['discount_value']);
        $this->assertSame(21000, $pedido['products'][0]['unit_value']);
        $this->assertSame('physical', $pedido['products'][0]['type']);
        $this->assertSame(
            (int) round((float) $order->total * 100),
            $pedido['products_value'] - $pedido['discount_value'] + $pedido['shipping_value'],
        );
    }

    // ---- 3. recusa clara x cobrança indefinida ---------------------------

    public function test_recusa_clara_cancela_devolve_estoque_e_mostra_mensagem_amigavel(): void
    {
        $this->fakeAppmax([
            'POST /v1/payments/credit-card' => fn () => Http::response(['error' => ['message' => 'Transaction declined by issuer (code 05)']], 400),
        ]);
        $p = $this->comCarrinho();

        $this->post('/checkout', $this->dados())
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('payment_error', AppmaxService::MSG_CARTAO_RECUSADO);

        $order = Order::sole();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame(5, $p->refresh()->size_stock['750 ml']);
        // Texto cru: no pedido (admin), nunca na sessão do cliente.
        $this->assertStringContainsString('declined by issuer', $order->payment_details['erro_gateway']);
        $this->assertStringNotContainsString('declined', session('payment_error'));
    }

    public function test_timeout_na_cobranca_deixa_pending_em_verificacao(): void
    {
        $this->fakeAppmax([
            'POST /v1/payments/credit-card' => fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
            'GET /v1/orders/20'             => fn () => Http::response(['message' => 'Server Error'], 503),
        ]);
        $p = $this->comCarrinho();

        $resp = $this->post('/checkout', $this->dados());

        $order = Order::sole();
        $resp->assertRedirect(route('checkout.success', $order));
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertTrue($order->pagamentoEmVerificacao());
        $this->assertSame(4, $p->refresh()->size_stock['750 ml']); // reserva mantida
        $this->assertSame(0, app(CartService::class)->count());

        $this->get(route('checkout.success', $order))
            ->assertOk()
            ->assertSee('Estamos confirmando seu pagamento, você recebe a confirmação em instantes.');
    }

    public function test_5xx_na_cobranca_com_consulta_aprovada_ja_aprova(): void
    {
        $this->fakeAppmax([
            'POST /v1/payments/credit-card' => fn () => Http::response(['message' => 'Bad Gateway'], 502),
            'GET /v1/orders/20'             => fn () => Http::response(['data' => ['order' => ['id' => 20, 'status' => 'aprovado']]]),
        ]);
        $this->comCarrinho();

        $this->post('/checkout', $this->dados());

        $order = Order::sole();
        $this->assertTrue($order->isPaid());
        $this->assertFalse($order->pagamentoEmVerificacao());
    }

    public function test_timeout_com_consulta_recusada_cancela(): void
    {
        $this->fakeAppmax([
            'POST /v1/payments/credit-card' => fn () => throw new ConnectionException('timeout'),
            'GET /v1/orders/20'             => fn () => Http::response(['data' => ['order' => ['id' => 20, 'status' => 'cancelado']]]),
        ]);
        $p = $this->comCarrinho();

        $this->post('/checkout', $this->dados())
            ->assertSessionHas('payment_error', AppmaxService::MSG_CARTAO_RECUSADO);

        $this->assertSame('cancelled', Order::sole()->status);
        $this->assertSame(5, $p->refresh()->size_stock['750 ml']);
    }

    // ---- 3. cancelar-cartao-preso consulta antes -------------------------

    protected function pedidoPresoNaAppmax(int $idadeMinutos = 90): Order
    {
        $this->fakeAppmax([
            'POST /v1/payments/credit-card' => fn () => throw new ConnectionException('timeout'),
            'GET /v1/orders/20'             => fn () => Http::response(['message' => 'Server Error'], 503),
        ]);
        $this->comCarrinho();
        $this->post('/checkout', $this->dados());

        $order = Order::sole();
        DB::table('orders')->where('id', $order->id)->update(['created_at' => now()->subMinutes($idadeMinutos)]);

        return $order->refresh();
    }

    public function test_cartao_preso_aprovado_na_appmax_e_aprovado_em_vez_de_cancelado(): void
    {
        $order = $this->pedidoPresoNaAppmax();
        $this->fakeAppmax(['GET /v1/orders/20' => fn () => Http::response(['data' => ['order' => ['id' => 20, 'status' => 'aprovado']]])]);

        $this->artisan('pedidos:cancelar-cartao-preso')->assertSuccessful();

        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertSame('processing', $order->status);
        $this->assertStringContainsString('Aprovado automaticamente', $order->notes);
        $this->assertSame(4, Product::sole()->size_stock['750 ml']); // vendido, não devolve
    }

    public function test_cartao_preso_recusado_na_appmax_e_cancelado(): void
    {
        $order = $this->pedidoPresoNaAppmax();
        $this->fakeAppmax(['GET /v1/orders/20' => fn () => Http::response(['data' => ['order' => ['id' => 20, 'status' => 'cancelado']]])]);

        $this->artisan('pedidos:cancelar-cartao-preso')->assertSuccessful();

        $this->assertSame('cancelled', $order->refresh()->status);
        $this->assertSame(5, Product::sole()->size_stock['750 ml']);
    }

    public function test_cartao_preso_com_consulta_fora_do_ar_fica_intocado(): void
    {
        $order = $this->pedidoPresoNaAppmax();
        $this->fakeAppmax(['GET /v1/orders/20' => fn () => throw new ConnectionException('fora do ar')]);

        $this->artisan('pedidos:cancelar-cartao-preso')->assertSuccessful();

        $this->assertSame('pending', $order->refresh()->status);
        $this->assertSame(4, Product::sole()->size_stock['750 ml']);
    }

    public function test_em_verificacao_recente_ja_reconcilia_mas_so_com_resposta_conclusiva(): void
    {
        $order = $this->pedidoPresoNaAppmax(5); // dentro da janela de 60 min

        // "pendente" dentro da janela: não decide.
        $this->fakeAppmax();
        $this->artisan('pedidos:cancelar-cartao-preso')->assertSuccessful();
        $this->assertSame('pending', $order->refresh()->status);

        // Aprovado: resolve já.
        $this->fakeAppmax(['GET /v1/orders/20' => fn () => Http::response(['data' => ['order' => ['id' => 20, 'status' => 'integrado']]])]);
        $this->artisan('pedidos:cancelar-cartao-preso')->assertSuccessful();
        $this->assertTrue($order->refresh()->isPaid());
    }

    // ---- 3. webhook aprovando pedido cancelado ---------------------------

    public function test_webhook_aprovando_pedido_cancelado_reativa_retoma_estoque_e_registra_incidente(): void
    {
        $this->fakeAppmax([
            'POST /v1/payments/credit-card' => fn () => Http::response(['error' => ['message' => 'declined']], 400),
        ]);
        $p = $this->comCarrinho();
        $this->post('/checkout', $this->dados());
        $order = Order::sole();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame(5, $p->refresh()->size_stock['750 ml']);

        Log::spy();
        $this->postJson('/webhooks/appmax/tok-teste', ['event' => 'order_approved', 'data' => ['id' => 20]])->assertOk();

        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertSame('processing', $order->status);
        $this->assertSame(4, $p->refresh()->size_stock['750 ml']); // reserva retomada
        $this->assertSame(29, $p->stock_qty);
        $this->assertCount(1, $order->incidentes());
        $this->assertStringContainsString('já cancelado', $order->incidentes()[0]['texto']);
        Log::shouldHaveReceived('warning')->withArgs(fn ($msg) => str_contains($msg, 'INCIDENTE'))->once();

        // Reentrega do evento: idempotente.
        $this->postJson('/webhooks/appmax/tok-teste', ['event' => 'order_paid', 'data' => ['id' => 20]])->assertOk();
        $this->assertSame(4, $p->refresh()->size_stock['750 ml']);
        $this->assertCount(1, $order->refresh()->incidentes());
    }

    public function test_reativacao_sem_estoque_avisa_no_incidente(): void
    {
        $this->fakeAppmax([
            'POST /v1/payments/credit-card' => fn () => Http::response(['error' => ['message' => 'declined']], 400),
        ]);
        $p = $this->comCarrinho();
        $this->post('/checkout', $this->dados());
        $p->update(['size_stock' => ['375 ml' => 5, '750 ml' => 0]]); // outro cliente levou

        $this->postJson('/webhooks/appmax/tok-teste', ['event' => 'order_approved', 'data' => ['id' => 20]])->assertOk();

        $order = Order::sole();
        $this->assertTrue($order->isPaid());
        $this->assertSame(0, $p->refresh()->size_stock['750 ml']);
        $this->assertStringContainsString('estoque insuficiente', $order->incidentes()[0]['texto']);
    }

    public function test_webhook_nao_ressuscita_pedido_estornado(): void
    {
        $this->fakeAppmax();
        $this->comCarrinho();
        $this->post('/checkout', $this->dados());
        $order = Order::sole();
        $order->update(['status' => 'cancelled', 'payment_status' => 'refunded']);

        $this->postJson('/webhooks/appmax/tok-teste', ['event' => 'order_approved', 'data' => ['id' => 20]])->assertOk();

        $this->assertSame('refunded', $order->refresh()->payment_status);
        $this->assertSame('cancelled', $order->status);
    }

    // ---- 4. parcelamento sem juros ---------------------------------------

    public function test_max_parcelas_respeita_teto_e_parcela_minima(): void
    {
        $this->assertSame(3, AppmaxService::maxParcelas(296.40));
        $this->assertSame(3, AppmaxService::maxParcelas(90.00));  // 3 × 30,00 exato
        $this->assertSame(2, AppmaxService::maxParcelas(89.99));
        $this->assertSame(1, AppmaxService::maxParcelas(45.00));
        $this->assertSame(1, AppmaxService::maxParcelas(10.00));  // nunca menos que 1
    }

    public function test_backend_recusa_parcelas_acima_do_teto_ou_da_parcela_minima(): void
    {
        $this->fakeAppmax();
        $this->comCarrinho();

        $this->post('/checkout', $this->dados(['installments' => 4]))->assertSessionHasErrors('installments');
        $this->assertSame(0, Order::count());

        // Teto 3x, mas parcela mínima de 150 → só 1x para 296,40.
        config(['appmax.min_installment_value' => 150.00]);
        $this->post('/checkout', $this->dados(['installments' => 2]))
            ->assertSessionHasErrors(['installments' => 'Para este valor, parcelamos em até 1x sem juros.']);
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_mostra_parcelas_sem_juros_e_script_do_ambiente(): void
    {
        $this->comCarrinho();

        $this->get('/checkout')
            ->assertOk()
            ->assertSee('3x de R$ 70,00 sem juros') // 210,00 sem frete ainda
            ->assertDontSee('Juros do parcelamento conforme condições no fechamento')
            ->assertSee('https://scripts.sandboxappmax.com.br/appmax.min.js')
            ->assertDontSee('name="card_number"', false)
            ->assertDontSee('name="card_cvv"', false);
    }

    // ---- 6. Pix ---------------------------------------------------------

    public function test_pix_envia_vencimento_em_brasilia_e_tela_usa_a_expiracao_da_resposta(): void
    {
        $this->travel(0)->seconds();
        $this->fakeAppmax();
        $this->comCarrinho();
        config(['appmax.pix_expiration_minutes' => 45]);

        $this->post('/checkout', $this->dados(['payment_method' => 'pix', 'cpf' => '408.273.651-44']));

        $pix = $this->enviado('POST', '/v1/payments/pix')['payment_data']['pix'];
        $this->assertSame('40827365144', $pix['document_number']);
        $this->assertSame(now('America/Sao_Paulo')->addMinutes(45)->format('Y-m-d H:i:s'), $pix['expiration_date']);

        $order = Order::sole();
        $this->get(route('checkout.success', $order))
            ->assertOk()
            ->assertSee('src="data:image/png;base64,iVBORw0KGgo"', false)
            ->assertSee('data-expira="2026-10-18T15:00:00-03:00"', false) // horário de Brasília
            ->assertSee('Chave válida até 18/10/2026 15:00');
    }

    public function test_qr_do_pix_em_url_e_usado_direto(): void
    {
        $this->fakeAppmax(['POST /v1/payments/pix' => fn () => Http::response(['data' => ['payment' => [
            'pix_qrcode' => 'https://cdn.appmax.test/qr.png', 'pix_emv' => '0002', 'pix_expiration_date' => '2026-10-18 15:00:00',
        ]]])]);
        $this->comCarrinho();

        $this->post('/checkout', $this->dados(['payment_method' => 'pix']));

        $this->get(route('checkout.success', Order::sole()))
            ->assertSee('src="https://cdn.appmax.test/qr.png"', false);
    }

    // ---- 7. erros -------------------------------------------------------

    public function test_422_nos_dois_formatos_vira_mensagem_amigavel_e_detalhe_por_campo(): void
    {
        foreach ([
            ['errors' => ['message' => ['document_number' => ['CPF inválido']]]],
            ['errors' => ['document_number' => ['CPF inválido']]],
        ] as $corpo) {
            $this->fakeAppmax(['POST /v1/customers' => fn () => Http::response($corpo, 422)]);

            try {
                app(AppmaxService::class)->createCustomer([
                    'name' => 'A B', 'email' => 'a@b.c', 'phone' => '11999990000', 'cpf' => '1',
                    'zipcode' => '80000000', 'street' => 'R', 'number' => '1', 'neighborhood' => 'C',
                    'city' => 'Curitiba', 'state' => 'PR',
                ], '127.0.0.1');
                $this->fail('deveria lançar');
            } catch (AppmaxException $e) {
                $this->assertSame(AppmaxService::MSG_DADOS_INVALIDOS, $e->getMessage());
                $this->assertSame(AppmaxException::DADOS_INVALIDOS, $e->tipo);
                $this->assertStringContainsString('document_number: CPF inválido', $e->detalhe);
            }
        }
    }

    public function test_falha_antes_da_cobranca_mostra_indisponivel_sem_texto_cru(): void
    {
        $this->fakeAppmax(['POST /v1/orders' => fn () => Http::response(['message' => 'SQLSTATE[23000] stack trace'], 500)]);
        $p = $this->comCarrinho();

        $this->post('/checkout', $this->dados())
            ->assertSessionHas('payment_error', AppmaxService::MSG_INDISPONIVEL);

        // Sem cobrança feita: cancelar é seguro.
        $order = Order::sole();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame(5, $p->refresh()->size_stock['750 ml']);
        $this->assertStringContainsString('SQLSTATE', $order->payment_details['erro_gateway']);
    }
}
