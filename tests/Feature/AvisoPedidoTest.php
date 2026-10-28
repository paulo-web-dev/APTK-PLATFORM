<?php

namespace Tests\Feature;

use App\Jobs\EnviarAvisoPagamento;
use App\Jobs\EnviarAvisoPedido;
use App\Mail\PagamentoConfirmado;
use App\Mail\PedidoNovo;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\AvisoPedido;
use App\Services\CartService;
use App\Services\ReconciliacaoPagamento;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * Avisos de pedido por e-mail para a equipe (leva 16, revista na leva 17).
 *
 * 1. Todo pedido do checkout avisa, qualquer método e desfecho, com o status
 *    do pagamento em destaque (pago / aguardando / recusado).
 * 2. Pagamento confirmado depois (webhook, reconciliação) manda um segundo
 *    aviso curto — uma vez só.
 * Falha no SMTP nunca derruba checkout nem webhook. O botão do admin
 * reenvia na hora.
 *
 * Fila sync no phpunit: o job roda na hora do dispatch.
 */
class AvisoPedidoTest extends TestCase
{
    use RefreshDatabase;

    protected const EQUIPE = ['rafael@aptkspirits.com', 'contato@aptkspirits.com', 'luiz.henrique@aptkspirits.com'];

    protected array $rotas = [];

    protected bool $fakeRegistrado = false;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config([
            'pedidos.notificar'             => self::EQUIPE,
            'pedidos.reply_to'              => 'contato@aptkspirits.com',
            'microvix.envia_pedido'         => false,
            'appmax.environment'            => 'sandbox',
            'appmax.merchant_client_id'     => 'cid',
            'appmax.merchant_client_secret' => 'csecret',
            'appmax.webhook_token'          => 'tok-teste',
            'appmax.max_installments'       => 3,
            'appmax.min_installment_value'  => 30.00,
            'appmax.http_retry_sleep_ms'    => 0,
        ]);
    }

    // ---- apoio ------------------------------------------------------------

    protected function produto(): Product
    {
        return Product::create([
            'name' => 'Negroni Clássico', 'slug' => 'negroni-classico', 'sku' => 'NC750', 'price' => 210.00,
            'sizes' => ['375 ml', '750 ml'], 'size_prices' => ['375 ml' => 165.00, '750 ml' => 210.00],
            'size_stock' => ['375 ml' => 5, '750 ml' => 5], 'stock_qty' => 30, 'active' => true,
        ]);
    }

    protected function cliente(): User
    {
        return User::create([
            'name' => 'Cliente', 'email' => 'cliente@aptk.test', 'password' => bcrypt('secret123'),
            'role' => 'customer', 'maior_idade_em' => now(),
        ]);
    }

    protected function dados(array $extra = []): array
    {
        return $extra + [
            'name' => 'Cliente Teste', 'phone' => '11 99999-0000', 'cpf' => '191.000.000-00',
            'zipcode' => '80000-000', 'street' => 'Rua XV', 'number' => '100', 'neighborhood' => 'Centro',
            'city' => 'Curitiba', 'state' => 'PR', 'payment_method' => 'cartao', 'shipping_method' => 'sedex',
            'appmax_token' => 'tok-cartao-123', 'card_name' => 'Cliente Teste', 'installments' => 3,
        ];
    }

    protected function comCarrinho(): void
    {
        $p = $this->produto();
        $this->actingAs($this->cliente());
        app(CartService::class)->add($p->id, 1, '750 ml');
    }

    protected function fakeAppmax(array $over = []): void
    {
        $this->rotas = $over + [
            'POST auth'                     => fn () => Http::response(['access_token' => 'bearer-x', 'expires_in' => 3600]),
            'POST /v1/customers'            => fn () => Http::response(['data' => ['customer' => ['id' => 10]]], 201),
            'POST /v1/orders'               => fn () => Http::response(['data' => ['order' => ['id' => 20]]], 201),
            'POST /v1/payments/credit-card' => fn () => Http::response(['data' => ['payment' => ['pay_reference' => 'ref-cc']]], 201),
            'POST /v1/payments/pix'         => fn () => Http::response(['data' => ['payment' => [
                'pay_reference' => 'ref-pix', 'pix_qrcode' => 'iVBORw0KGgo', 'pix_emv' => '000201010212',
                'pix_expiration_date' => now()->addHour()->format('Y-m-d H:i:s'),
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

    protected function appmaxDiz(string $status): void
    {
        $this->rotas['GET /v1/orders/20'] = fn () => Http::response(['data' => ['order' => ['id' => 20, 'status' => $status]]]);
    }

    protected function pedidoPixPendente(): Order
    {
        $this->fakeAppmax();
        $this->comCarrinho();
        $this->post('/checkout', $this->dados(['payment_method' => 'pix']))->assertRedirect();

        return Order::sole();
    }

    protected function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@aptk.test', 'password' => bcrypt('x12345678'), 'role' => 'admin']);
    }

    protected function num(Order $order): string
    {
        return 'APTK-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT);
    }

    protected function webhookAprovado(): void
    {
        $this->appmaxDiz('aprovado');
        $this->postJson('/webhooks/appmax/tok-teste', ['event' => 'order_approved', 'data' => ['id' => 20]])->assertOk();
    }

    // ---- 1. aviso de pedido criado -----------------------------------------------

    public function test_cartao_aprovado_avisa_como_pago_e_nao_manda_segundo_aviso(): void
    {
        $this->fakeAppmax();
        $this->comCarrinho();

        $this->post('/checkout', $this->dados())->assertRedirect();

        $order = Order::sole();
        Mail::assertSent(PedidoNovo::class, 1);
        Mail::assertSent(PedidoNovo::class, function (PedidoNovo $m) use ($order) {
            return $m->order->is($order)
                && $m->hasTo(self::EQUIPE[0]) && $m->hasTo(self::EQUIPE[1]) && $m->hasTo(self::EQUIPE[2])
                && $m->hasReplyTo('contato@aptkspirits.com')
                && $m->envelope()->subject === 'Novo pedido '.$this->num($order).' · Pago';
        });
        Mail::assertNotSent(PagamentoConfirmado::class);
        $this->assertNotNull($order->notificado_em);
        $this->assertNotNull($order->pagamento_notificado_em); // o aviso já disse "Pago"

        // Webhook de aprovação chegando depois: nada novo.
        $this->webhookAprovado();
        Mail::assertSent(PedidoNovo::class, 1);
        Mail::assertNotSent(PagamentoConfirmado::class);
    }

    public function test_cartao_recusado_avisa_como_recusado(): void
    {
        $this->fakeAppmax([
            'POST /v1/payments/credit-card' => fn () => Http::response(['error' => ['message' => 'declined']], 400),
        ]);
        $this->comCarrinho();

        $this->post('/checkout', $this->dados())->assertRedirect(route('checkout.index'));

        $order = Order::sole();
        $this->assertSame('cancelled', $order->status);
        Mail::assertSent(PedidoNovo::class, fn (PedidoNovo $m) => $m->envelope()->subject === 'Novo pedido '.$this->num($order).' · Pagamento recusado'
            && str_contains($m->render(), 'Pagamento recusado'));
        $this->assertNull($order->pagamento_notificado_em);
    }

    public function test_falha_no_gateway_antes_da_cobranca_tambem_avisa(): void
    {
        $this->fakeAppmax([
            'POST /v1/orders' => fn () => Http::response(['error' => ['message' => 'Service Unavailable']], 503),
        ]);
        $this->comCarrinho();

        $this->post('/checkout', $this->dados(['payment_method' => 'boleto']))->assertRedirect(route('checkout.index'));

        Mail::assertSent(PedidoNovo::class, 1);
    }

    public function test_pix_avisa_aguardando_e_webhook_manda_pagamento_confirmado_uma_vez_so(): void
    {
        $order = $this->pedidoPixPendente();

        Mail::assertSent(PedidoNovo::class, fn (PedidoNovo $m) => $m->envelope()->subject === 'Novo pedido '.$this->num($order).' · Aguardando pagamento'
            && str_contains($m->render(), 'Aguardando pagamento'));
        Mail::assertNotSent(PagamentoConfirmado::class);

        $this->webhookAprovado();
        // Reentrega do evento e outro evento de pagamento: não repete.
        $this->postJson('/webhooks/appmax/tok-teste', ['event' => 'order_approved', 'data' => ['id' => 20]])->assertOk();
        $this->postJson('/webhooks/appmax/tok-teste', ['event' => 'order_paid', 'data' => ['id' => 20]])->assertOk();

        Mail::assertSent(PedidoNovo::class, 1);
        Mail::assertSent(PagamentoConfirmado::class, 1);
        Mail::assertSent(PagamentoConfirmado::class, fn (PagamentoConfirmado $m) => $m->hasTo(self::EQUIPE[0])
            && $m->hasReplyTo('contato@aptkspirits.com')
            && $m->envelope()->subject === 'Pagamento confirmado '.$this->num($order));
        $this->assertNotNull($order->refresh()->pagamento_notificado_em);
    }

    public function test_reconciliacao_que_aprova_manda_pagamento_confirmado(): void
    {
        $order = $this->pedidoPixPendente();
        $this->appmaxDiz('aprovado');

        app(ReconciliacaoPagamento::class)->reconciliar($order->refresh());
        app(ReconciliacaoPagamento::class)->reconciliar($order->refresh()); // de novo: nada

        $this->assertTrue($order->refresh()->isPaid());
        Mail::assertSent(PagamentoConfirmado::class, 1);
    }

    public function test_avisos_vao_pra_fila_notificacoes(): void
    {
        Queue::fake();
        $order = $this->pedidoPixPendente();

        Queue::assertPushedOn('notificacoes', EnviarAvisoPedido::class, fn ($job) => $job->orderId === $order->id);

        $order->forceFill(['notificado_em' => now()])->save();
        $this->webhookAprovado();

        Queue::assertPushedOn('notificacoes', EnviarAvisoPagamento::class, fn ($job) => $job->orderId === $order->id);
    }

    public function test_travas_nao_deixam_reenviar_pelo_fluxo_automatico(): void
    {
        $order = $this->pedidoPixPendente();
        $order->forceFill(['pagamento_notificado_em' => now()->subHour()])->save();

        Queue::fake();
        $this->webhookAprovado();
        Queue::assertNotPushed(EnviarAvisoPagamento::class);

        // E os jobs, se já estivessem na fila, também não reenviam.
        (new EnviarAvisoPedido($order->id))->handle(app(AvisoPedido::class));
        (new EnviarAvisoPagamento($order->id))->handle(app(AvisoPedido::class));
        Mail::assertSent(PedidoNovo::class, 1); // só o da criação
        Mail::assertNotSent(PagamentoConfirmado::class);
    }

    public function test_confirmacao_antes_do_primeiro_aviso_nao_sai_e_o_primeiro_ja_diz_pago(): void
    {
        $order = $this->pedidoPixPendente();
        $order->forceFill(['notificado_em' => null, 'payment_status' => 'paid', 'status' => 'processing', 'paid_at' => now()])->save();

        (new EnviarAvisoPagamento($order->id))->handle(app(AvisoPedido::class));
        Mail::assertNotSent(PagamentoConfirmado::class);

        (new EnviarAvisoPedido($order->id))->handle(app(AvisoPedido::class));
        $this->assertNotNull($order->refresh()->pagamento_notificado_em);
        Mail::assertSent(PedidoNovo::class, fn (PedidoNovo $m) => str_ends_with($m->envelope()->subject, '· Pago'));
    }

    public function test_worker_do_scheduler_processa_a_fila_de_notificacoes(): void
    {
        $comando = collect(app(Schedule::class)->events())
            ->map(fn ($e) => $e->command)
            ->first(fn ($c) => str_contains((string) $c, 'queue:work'));

        $this->assertStringContainsString('--queue=notificacoes,microvix', $comando);
    }

    public function test_falha_no_smtp_nao_derruba_o_checkout_e_fica_no_pedido(): void
    {
        Mail::shouldReceive('to')->andThrow(new TransportException('Expected response code "250" but got code "554".'));
        $this->fakeAppmax();
        $this->comCarrinho();

        $resposta = $this->post('/checkout', $this->dados());

        $order = Order::sole();
        $resposta->assertRedirect(route('checkout.success', $order));
        $this->assertTrue($order->isPaid());
        $this->assertNull($order->notificado_em);
        $this->assertStringContainsString('554', $order->notificacao_erro);
        $this->assertNotNull($order->notificacao_erro_em);
    }

    public function test_falha_no_smtp_nao_derruba_o_webhook_e_fica_no_pedido(): void
    {
        $order = $this->pedidoPixPendente();

        Mail::shouldReceive('to')->andThrow(new TransportException('Expected response code "250" but got code "554".'));
        $this->webhookAprovado();

        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertNull($order->pagamento_notificado_em);
        $this->assertStringContainsString('554', $order->notificacao_erro);
    }

    public function test_troca_manual_de_status_no_admin_nao_manda_pagamento_confirmado(): void
    {
        $order = $this->pedidoPixPendente();

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.update-status', $order), ['status' => 'processing'])
            ->assertRedirect();

        $this->assertTrue($order->refresh()->isPaid());
        Mail::assertSent(PedidoNovo::class, 1); // só o da criação
        Mail::assertNotSent(PagamentoConfirmado::class);
    }

    // ---- botão do admin --------------------------------------------------------

    public function test_botao_do_admin_reenvia_mesmo_ja_avisado(): void
    {
        $order = $this->pedidoPixPendente();
        $order->forceFill(['notificado_em' => now()->subDay()])->save();

        $this->actingAs($this->admin())
            ->post(route('admin.orders.notify', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('status', 'Aviso enviado para '.implode(', ', self::EQUIPE).'.');

        Mail::assertSent(PedidoNovo::class, 2); // criação + reenvio
        $this->assertTrue($order->refresh()->notificado_em->isToday());

        $this->get(route('admin.orders.show', $order))
            ->assertSee('Aviso enviado em '.data_br($order->notificado_em))
            ->assertSee('Enviar aviso via e-mail');
    }

    public function test_admin_mostra_quando_o_pagamento_confirmado_foi_avisado(): void
    {
        $order = $this->pedidoPixPendente();
        $this->webhookAprovado();
        $order->refresh();

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertSee('Pagamento confirmado avisado em '.data_br($order->pagamento_notificado_em));
    }

    public function test_botao_do_admin_mostra_o_erro_do_smtp(): void
    {
        $order = $this->pedidoPixPendente();
        Mail::shouldReceive('to')->andThrow(new TransportException('Failed to authenticate on SMTP server'));

        $this->actingAs($this->admin())
            ->post(route('admin.orders.notify', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('error', 'O aviso não foi enviado: Failed to authenticate on SMTP server');

        $this->get(route('admin.orders.show', $order))->assertSee('Erro no último envio');
    }

    public function test_botao_do_admin_e_so_pra_admin(): void
    {
        $order = $this->pedidoPixPendente(); // logado como cliente

        $this->post(route('admin.orders.notify', $order))->assertForbidden();
        Mail::assertSent(PedidoNovo::class, 1); // só o da criação
    }

    // ---- corpo dos e-mails ---------------------------------------------------------

    public function test_corpo_do_email_tem_dados_do_pedido_e_custom_em_destaque(): void
    {
        $this->travelTo(Carbon::parse('2026-10-27 15:30:00', 'UTC')); // 12:30 em Brasília
        $p = $this->produto();
        $user = $this->cliente();

        $order = Order::create([
            'user_id' => $user->id, 'status' => 'processing', 'payment_status' => 'paid', 'payment_method' => 'cartao',
            'payment_details' => ['type' => 'cartao', 'installments' => 3], 'paid_at' => now(),
            'subtotal' => 1500.00, 'discount' => 50.00, 'shipping_cost' => 74.00, 'total' => 1524.00,
            'shipping_method' => 'sedex', 'shipping_days' => 4,
            'ship_name' => 'Bia Souza', 'ship_phone' => '11 98888-7777', 'ship_zipcode' => '80000-000',
            'ship_street' => 'Rua XV', 'ship_number' => '100', 'ship_complement' => 'ap 12',
            'ship_neighborhood' => 'Centro', 'ship_city' => 'Curitiba', 'ship_state' => 'PR',
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $p->id, 'product_name' => 'Negroni Clássico', 'size' => '750 ml',
            'qty' => 6, 'unit_price' => 210.00, 'total_price' => 1260.00,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $p->id, 'product_name' => 'Negroni Clássico', 'size' => '375 ml',
            'qty' => 1, 'unit_price' => 211.00, 'total_price' => 211.00,
            'personalizacao' => ['bebida' => 'negroni-classico', 'bebida_nome' => 'Negroni Clássico',
                'texto' => 'Bia & Eduardo', 'fonte' => 'PP Rader', 'cor' => 'preto'],
        ]);

        $mail = new PedidoNovo($order->fresh());
        $html = $mail->render();

        foreach ([
            $this->num($order), '27/10/2026 12:30',
            'Cliente', 'cliente@aptk.test', '11 98888-7777',
            'Bia Souza', 'Rua XV, 100 — ap 12', 'Curitiba / PR', 'CEP 80000-000', 'SEDEX', 'até 4 dias úteis',
            '750 ml', '6 × R$ 210,00', 'R$ 1.260,00',
            'Garrafa personalizada', 'Bia &amp; Eduardo', 'PP Rader (Editorial)', 'Preto (#020203)',
            '− R$ 50,00', 'R$ 74,00', 'R$ 1.524,00',
            'Cartão de crédito', '3x', 'Pago', '#3F8F63',
            'Ver pedido no admin', route('admin.orders.show', $order),
        ] as $trecho) {
            $this->assertStringContainsString($trecho, $html, "faltou no HTML: {$trecho}");
        }

        $texto = view('emails.pedido-novo-texto', $mail->dados())->render();
        $this->assertStringContainsString('PAGAMENTO: PAGO', $texto);
        $this->assertStringContainsString('GARRAFA PERSONALIZADA', $texto);
        $this->assertStringContainsString('Texto: "Bia & Eduardo"', $texto);
        $this->assertStringContainsString('TOTAL: R$ 1.524,00', $texto);
    }

    public function test_corpo_do_pagamento_confirmado(): void
    {
        $this->travelTo(Carbon::parse('2026-10-28 13:00:00', 'UTC')); // 10:00 em Brasília
        $order = Order::create([
            'user_id' => $this->cliente()->id, 'status' => 'processing', 'payment_status' => 'paid', 'payment_method' => 'pix',
            'paid_at' => now(), 'subtotal' => 210, 'discount' => 0, 'shipping_cost' => 86.40, 'total' => 296.40,
        ]);

        $mail = new PagamentoConfirmado($order);
        $html = $mail->render();

        foreach (['Pagamento confirmado', $this->num($order), 'Cliente', 'Pix', 'R$ 296,40', '28/10/2026 10:00', route('admin.orders.show', $order)] as $trecho) {
            $this->assertStringContainsString($trecho, $html, "faltou no HTML: {$trecho}");
        }
        $this->assertStringContainsString('R$ 296,40', view('emails.pagamento-confirmado-texto', $mail->dados())->render());
    }

    public function test_sem_reply_to_configurado_o_email_sai_sem(): void
    {
        config(['pedidos.reply_to' => null]);
        $this->fakeAppmax();
        $this->comCarrinho();

        $this->post('/checkout', $this->dados())->assertRedirect();

        Mail::assertSent(PedidoNovo::class, fn (PedidoNovo $m) => $m->envelope()->replyTo === []);
    }

    // ---- comando de teste --------------------------------------------------------

    public function test_comando_testar_email_envia_e_nao_marca_o_pedido(): void
    {
        $order = $this->pedidoPixPendente();
        $antes = $order->notificado_em;

        $this->artisan('pedidos:testar-email', ['pedido' => $this->num($order), '--para' => ['paulo@teste.dev']])
            ->expectsOutputToContain('SMTP aceitou o e-mail.')
            ->assertSuccessful();

        Mail::assertSent(PedidoNovo::class, fn (PedidoNovo $m) => $m->hasTo('paulo@teste.dev') && ! $m->hasTo(self::EQUIPE[0]));
        $this->assertEquals($antes, $order->refresh()->notificado_em);
    }

    public function test_comando_testar_email_mostra_o_erro_completo(): void
    {
        $order = $this->pedidoPixPendente();
        Mail::shouldReceive('to')->andThrow(new TransportException('Connection could not be established with host "smtp.locaweb.com.br:587"'));

        $this->artisan('pedidos:testar-email', ['pedido' => $order->id])
            ->expectsOutputToContain('Connection could not be established with host "smtp.locaweb.com.br:587"')
            ->assertFailed();
    }
}
