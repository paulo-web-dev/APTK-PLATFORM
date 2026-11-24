<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CouponService;
use Database\Seeders\CuponsEventosNov2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Cupons de ponta a ponta (CouponService): aplicação no carrinho/checkout,
 * link ?cupom=, revalidação no fechamento (com lock), primeira compra por
 * CPF e e-mail, limites, valor cobrado na Appmax e a garrafa custom no
 * cálculo. Desconto sempre só nos produtos.
 */
class CupomTest extends TestCase
{
    use RefreshDatabase;

    /** Liga a Appmax "fora do ar" (500 em tudo) no fake do setUp. */
    protected bool $appmaxFora = false;

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
            if ($this->appmaxFora) {
                return Http::response(['error' => ['message' => 'fora do ar']], 500);
            }
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

    /* -----------------------------------------------------------------
     | Ajudantes
     ------------------------------------------------------------------ */

    protected function cupom(array $over = []): Coupon
    {
        return Coupon::create($over + [
            'code' => 'MITRE15', 'name' => 'Evento Mitre', 'type' => 'percent', 'value' => 15,
            'min_order' => 0, 'max_per_customer' => 1, 'first_purchase_only' => true, 'active' => true,
            'starts_at' => now()->subDay(), 'expires_at' => now()->addMonth(),
        ]);
    }

    protected function produto(): Product
    {
        return Product::firstOrCreate(['slug' => 'negroni-classico'], [
            'name' => 'Negroni Clássico', 'sku' => 'NC750', 'price' => 210.00,
            'sizes' => ['375 ml', '750 ml'], 'size_prices' => ['375 ml' => 165.90, '750 ml' => 210.00],
            'size_stock' => ['375 ml' => 50, '750 ml' => 50], 'stock_qty' => 100, 'active' => true,
        ]);
    }

    protected function cliente(string $email = 'cliente@aptk.test'): User
    {
        $user = User::create([
            'name' => 'Cliente', 'email' => $email, 'password' => bcrypt('secret123'),
            'role' => 'customer', 'maior_idade_em' => now(),
        ]);
        $this->actingAs($user);

        return $user;
    }

    /** 2 × 165,90 + 1 × 210,00 = 541,80 → 15% = 81,27. */
    protected function carrinho(): void
    {
        $p = $this->produto();
        app(CartService::class)->clear();
        app(CartService::class)->add($p->id, 2, '375 ml');
        app(CartService::class)->add($p->id, 1, '750 ml');
    }

    protected function dados(array $extra = []): array
    {
        return $extra + [
            'name' => 'Cliente Teste', 'phone' => '11 99999-0000', 'cpf' => '191.000.000-00',
            'zipcode' => '80000-000', 'street' => 'Rua XV', 'number' => '100', 'neighborhood' => 'Centro',
            'city' => 'Curitiba', 'state' => 'PR', 'shipping_method' => 'sedex', 'payment_method' => 'pix',
            'appmax_token' => 'tok-cartao-123', 'card_name' => 'Cliente Teste',
        ];
    }

    protected function enviado(string $caminho): ?array
    {
        $req = collect(Http::recorded())->map(fn ($par) => $par[0])
            ->first(fn (Request $r) => $r->method() === 'POST' && str_ends_with((string) parse_url($r->url(), PHP_URL_PATH), $caminho));

        return $req?->data();
    }

    /** Pedido pago de outro cliente (para primeira compra / limite). */
    protected function pedidoAnterior(array $over = []): Order
    {
        $outro = User::create([
            'name' => 'Outro', 'email' => 'outro'.uniqid().'@aptk.test', 'password' => bcrypt('x'), 'role' => 'customer',
        ]);

        return Order::create($over + [
            'user_id' => $outro->id, 'status' => 'processing', 'payment_status' => 'paid', 'paid_at' => now(),
            'subtotal' => 100, 'discount' => 0, 'shipping_cost' => 0, 'total' => 100, 'payment_method' => 'pix',
        ]);
    }

    /* -----------------------------------------------------------------
     | Aplicação e cálculo
     ------------------------------------------------------------------ */

    public static function metodos(): array
    {
        return [
            'pix'              => ['pix', [], '/v1/payments/pix'],
            'cartão parcelado' => ['cartao', ['installments' => 3], '/v1/payments/credit-card'],
            'boleto'           => ['boleto', [], '/v1/payments/boleto'],
        ];
    }

    /** @dataProvider metodos */
    public function test_15_por_cento_so_nos_produtos_e_appmax_cobra_o_total_do_pedido(string $metodo, array $extra, string $pagamento): void
    {
        $coupon = $this->cupom();
        $this->cliente();
        $this->carrinho();

        $this->from(route('cart.index'))->post(route('cart.coupon.apply'), ['cupom' => ' mitre15 '])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('cupom_ok', 'Cupom MITRE15 aplicado: 15% off');

        $this->get(route('cart.index'))->assertSee('Cupom MITRE15')->assertSee('− R$ 81,27', false)->assertSee('R$ 460,53');
        $this->get(route('checkout.index'))->assertSee('− R$ 81,27', false)->assertSee('data-subtotal="460.53"', false);

        $this->post('/checkout', $this->dados($extra + ['payment_method' => $metodo]))->assertRedirect();

        $order = Order::sole();
        $this->assertEquals(541.80, (float) $order->subtotal);
        $this->assertEquals(81.27, (float) $order->discount);
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertSame('MITRE15', $order->coupon_code);
        $this->assertSame('cliente@aptk.test', $order->customer_email);
        $this->assertGreaterThan(0, (float) $order->shipping_cost); // frete cheio, sem desconto
        $this->assertEquals(round(541.80 - 81.27 + (float) $order->shipping_cost, 2), (float) $order->total);

        $appmax = $this->enviado('/v1/orders');
        $this->assertSame(54180, $appmax['products_value']);
        $this->assertSame(8127, $appmax['discount_value']);
        $this->assertSame((int) round((float) $order->shipping_cost * 100), $appmax['shipping_value']);
        $this->assertSame((int) round((float) $order->total * 100),
            $appmax['products_value'] - $appmax['discount_value'] + $appmax['shipping_value']);
        $this->assertSame(20, $this->enviado($pagamento)['order_id']); // cobra o pedido, sem valor próprio

        // Um cupom por pedido: saiu da sessão junto com o carrinho.
        $this->assertNull(session(CouponService::SESSAO));
    }

    public function test_garrafa_personalizada_entra_no_calculo_como_os_demais(): void
    {
        $this->cupom();
        $this->cliente();
        $negroni = $this->produto();
        app(CartService::class)->add($negroni->id, 1, '375 ml', [
            'bebida' => 'negroni-classico', 'texto' => 'Bia & Eduardo', 'fonte' => 'PP Rader', 'cor' => 'preto',
        ]);
        app(CartService::class)->add($negroni->id, 1, '750 ml');

        $subtotal = (float) config('custom.preco') + 210.00;
        $esperado = round($subtotal * 0.15, 2);

        $this->post(route('cart.coupon.apply'), ['cupom' => 'MITRE15']);
        $this->post('/checkout', $this->dados(['payment_method' => 'pix']))->assertRedirect();

        $order = Order::sole();
        $this->assertTrue($order->items->contains(fn ($i) => $i->isCustom()));
        $this->assertEquals($subtotal, (float) $order->subtotal);
        $this->assertEquals($esperado, (float) $order->discount);
    }

    public function test_valor_fixo_nunca_passa_do_subtotal(): void
    {
        $c = $this->cupom(['code' => 'GRANDE', 'type' => 'fixed', 'value' => 999, 'first_purchase_only' => false]);
        $this->assertSame(541.80, app(CouponService::class)->desconto($c, 541.80));
        $this->assertSame(20.00, app(CouponService::class)->desconto($c->fill(['value' => 20]), 541.80));
    }

    /* -----------------------------------------------------------------
     | Link ?cupom=
     ------------------------------------------------------------------ */

    public function test_link_aplica_e_redireciona_sem_o_parametro_preservando_os_demais(): void
    {
        $this->cupom();

        $this->get('/loja?utm_source=mailing&cupom=mitre15&utm_campaign=mitre')
            ->assertRedirect(url('/loja?utm_source=mailing&utm_campaign=mitre'))
            ->assertSessionHas(CouponService::SESSAO, 'MITRE15');

        $this->get('/loja?utm_source=mailing&utm_campaign=mitre')
            ->assertOk()
            ->assertSee('Cupom MITRE15 aplicado: 15% off');

        // Só o cupom: volta pra URL limpa.
        $this->get('/?cupom=MITRE15')->assertRedirect(url('/'));
    }

    public function test_link_com_cupom_recusado_avisa_e_nao_guarda(): void
    {
        $this->cupom(['active' => false]);

        $this->get('/loja?cupom=MITRE15')->assertRedirect(url('/loja'))
            ->assertSessionMissing(CouponService::SESSAO);
        $this->get('/loja')->assertSee('Cupom MITRE15 não aplicado: Este cupom não está ativo.');
    }

    public function test_link_so_em_get(): void
    {
        $this->cupom();
        $this->cliente();
        $this->carrinho();

        // POST com ?cupom= na URL segue o fluxo normal, sem desvio.
        $this->post('/carrinho/atualizar?cupom=MITRE15', [])->assertSessionMissing(CouponService::SESSAO);
    }

    public function test_dominio_de_marca_leva_o_cupom_para_a_aptk(): void
    {
        $app = rtrim(config('app.url'), '/');

        $this->get('http://ice4pros.com.br/?cupom=MITRE15&utm_source=evento')
            ->assertStatus(301)
            ->assertHeader('Location', $app.'/?cupom=MITRE15&utm_source=evento');

        $this->get('http://ice4pros.com.br/loja?cupom=MITRE15')
            ->assertStatus(301)
            ->assertHeader('Location', $app.'/loja?cupom=MITRE15');

        // Sem cupom a raiz continua servindo a LP.
        $this->get('http://ice4pros.com.br/')->assertOk()->assertSee('i4p-header');
    }

    /* -----------------------------------------------------------------
     | Recusas
     ------------------------------------------------------------------ */

    public static function recusas(): array
    {
        return [
            // Datas montadas no teste (o provider roda antes do app, fora do fuso dele).
            'expirado'       => [['expires_at' => '-1 minute'], 'Este cupom expirou.'],
            'ainda não vale' => [['starts_at' => '+1 hour'], 'Este cupom ainda não começou a valer.'],
            'inativo'        => [['active' => false], 'Este cupom não está ativo.'],
            'mínimo'         => [['min_order' => 600], 'Este cupom vale para pedidos a partir de R$ 600,00 em produtos.'],
        ];
    }

    /** @dataProvider recusas */
    public function test_recusa_com_o_motivo(array $over, string $motivo): void
    {
        foreach (['starts_at', 'expires_at'] as $campo) {
            if (isset($over[$campo])) {
                $over[$campo] = now()->modify($over[$campo]);
            }
        }
        $this->cupom($over);
        $this->cliente();
        $this->carrinho();

        $this->from(route('cart.index'))->post(route('cart.coupon.apply'), ['cupom' => 'MITRE15'])
            ->assertSessionHas('cupom_erro', "Cupom MITRE15 não aplicado: {$motivo}")
            ->assertSessionMissing(CouponService::SESSAO);
    }

    public function test_codigo_inexistente(): void
    {
        $this->from(route('cart.index'))->post(route('cart.coupon.apply'), ['cupom' => 'NADA'])
            ->assertSessionHas('cupom_erro', 'Cupom NADA não aplicado: Cupom não encontrado.');
    }

    public function test_limite_total_conta_pedidos_nao_cancelados(): void
    {
        $c = $this->cupom(['max_uses' => 1, 'first_purchase_only' => false, 'max_per_customer' => null]);
        $this->cliente();
        $this->carrinho();

        $cancelado = $this->pedidoAnterior(['coupon_id' => $c->id, 'coupon_code' => 'MITRE15', 'status' => 'cancelled', 'payment_status' => 'failed']);
        $this->post(route('cart.coupon.apply'), ['cupom' => 'MITRE15'])->assertSessionHas('cupom_ok');

        // Um Pix pendente segura o uso.
        $this->pedidoAnterior(['coupon_id' => $c->id, 'coupon_code' => 'MITRE15', 'status' => 'pending', 'payment_status' => 'pending']);
        $this->post(route('cart.coupon.apply'), ['cupom' => 'MITRE15'])
            ->assertSessionHas('cupom_erro', 'Cupom MITRE15 não aplicado: Este cupom atingiu o limite de usos.');
        $this->assertNotNull($cancelado);
    }

    /* -----------------------------------------------------------------
     | Primeira compra / por cliente
     ------------------------------------------------------------------ */

    public function test_segunda_compra_recusada_pelo_cpf_no_fechamento(): void
    {
        $this->cupom();
        $this->pedidoAnterior(['ship_cpf' => '19100000000']);
        $this->cliente();
        $this->carrinho();

        // Antes do checkout só o e-mail é conhecido: passa.
        $this->post(route('cart.coupon.apply'), ['cupom' => 'MITRE15'])->assertSessionHas('cupom_ok');

        // No fechamento o CPF (formatado ou não) acusa a compra anterior.
        $this->post('/checkout', $this->dados(['cpf' => '191.000.000-00']))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('cupom_recusado', 'Cupom MITRE15 não aplicado: Este cupom é só para a primeira compra. Removemos o cupom; confira o total e finalize de novo.');

        $this->assertSame(1, Order::count()); // nada gravado
        $this->assertNull(session(CouponService::SESSAO));
        $this->assertFalse(app(CartService::class)->isEmpty()); // carrinho intacto
    }

    public function test_segunda_compra_recusada_pelo_email_normalizado(): void
    {
        $this->cupom();
        // E-mail do pedido anterior (snapshot), de outra conta.
        $this->pedidoAnterior(['customer_email' => 'cliente@aptk.test']);
        $this->cliente('  Cliente@APTK.test ');
        $this->carrinho();

        $this->from(route('cart.index'))->post(route('cart.coupon.apply'), ['cupom' => 'MITRE15'])
            ->assertSessionHas('cupom_erro', 'Cupom MITRE15 não aplicado: Este cupom é só para a primeira compra.');
    }

    public function test_compra_anterior_cancelada_ou_so_pendente_nao_conta_como_compra(): void
    {
        $this->cupom();
        $this->pedidoAnterior(['ship_cpf' => '19100000000', 'status' => 'cancelled', 'payment_status' => 'failed']);
        $this->pedidoAnterior(['ship_cpf' => '19100000000', 'status' => 'pending', 'payment_status' => 'pending']);
        $this->cliente();
        $this->carrinho();

        $this->post(route('cart.coupon.apply'), ['cupom' => 'MITRE15']);
        $this->post('/checkout', $this->dados())->assertRedirect();

        $this->assertSame('MITRE15', Order::latest('id')->first()->coupon_code);
    }

    /**
     * Concorrência: dois fechamentos do mesmo CPF (contas diferentes). O
     * lockForUpdate na linha do cupom serializa os dois no MySQL; aqui o
     * segundo roda depois do primeiro (o que o lock garante) e tem de ver o
     * Pix pendente do primeiro e ser recusado pelo limite por cliente.
     */
    public function test_dois_pedidos_do_mesmo_cpf_so_um_leva_o_desconto(): void
    {
        $this->cupom();

        $this->cliente('a@aptk.test');
        $this->carrinho();
        $this->post(route('cart.coupon.apply'), ['cupom' => 'MITRE15']);

        // Segunda sessão, já com o cupom na sessão antes do primeiro fechar.
        $sessaoB = ['cupom' => 'MITRE15'];

        $this->post('/checkout', $this->dados())->assertRedirect();
        $primeiro = Order::sole();
        $this->assertSame('pending', $primeiro->status); // Pix aguardando
        $this->assertEquals(81.27, (float) $primeiro->discount);

        $this->flushSession();
        $this->cliente('b@aptk.test');
        $this->withSession($sessaoB);
        $this->carrinho();

        $this->post('/checkout', $this->dados())
            ->assertSessionHas('cupom_recusado', 'Cupom MITRE15 não aplicado: Você já usou este cupom. Removemos o cupom; confira o total e finalize de novo.');
        $this->assertSame(1, Order::count());
    }

    public function test_appmax_falha_devolve_o_cupom_para_a_sessao(): void
    {
        $this->cupom();
        $this->cliente();
        $this->carrinho();
        $this->post(route('cart.coupon.apply'), ['cupom' => 'MITRE15']);

        $this->appmaxFora = true;
        $this->post('/checkout', $this->dados())->assertRedirect(route('checkout.index'));

        $this->assertSame('cancelled', Order::sole()->status);
        $this->assertSame('MITRE15', session(CouponService::SESSAO));
        // O pedido cancelado liberou o uso: o cupom segue valendo.
        $this->get(route('cart.index'))->assertSee('− R$ 81,27', false);
    }

    /* -----------------------------------------------------------------
     | Inativar no meio da sessão
     ------------------------------------------------------------------ */

    public function test_inativar_vale_na_hora_para_quem_ja_tinha_na_sessao(): void
    {
        $c = $this->cupom();
        $this->cliente();
        $this->carrinho();
        $this->post(route('cart.coupon.apply'), ['cupom' => 'MITRE15'])->assertSessionHas('cupom_ok');

        $c->update(['active' => false]);

        $this->get(route('cart.index'))
            ->assertSee('Cupom MITRE15 não aplicado: Este cupom não está ativo.')
            ->assertDontSee('− R$ 81,27', false)
            ->assertSee('R$ 541,80');

        $this->post('/checkout', $this->dados())
            ->assertSessionHas('cupom_recusado');
        $this->assertSame(0, Order::count());
    }

    /* -----------------------------------------------------------------
     | Admin, e-mail e seed
     ------------------------------------------------------------------ */

    public function test_admin_cria_com_data_em_brasilia_e_mostra_relatorio(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@aptk.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $this->actingAs($admin);

        $this->post(route('admin.coupons.store'), [
            'code' => 'mitre 15', 'type' => 'percent', 'value' => 15,
        ])->assertSessionHasErrors('code'); // espaço não

        $this->post(route('admin.coupons.store'), [
            'code' => 'mitre15', 'name' => 'Evento Mitre - mailing nov/2026', 'type' => 'percent', 'value' => 15,
            'max_per_customer' => 1, 'starts_at' => '23/11/2026 10:00', 'expires_at' => '31/12/2026 23:59',
            'first_purchase_only' => '1', 'active' => '1',
        ])->assertRedirect();

        $c = Coupon::sole();
        $this->assertSame('MITRE15', $c->code);
        $this->assertSame('2027-01-01 02:59:00', $c->expires_at->utc()->format('Y-m-d H:i:s')); // 23:59 BRT = 02:59 UTC
        $this->assertSame('31/12/2026 23:59', Coupon::paraCampo($c->expires_at));
        $this->assertTrue($c->first_purchase_only);

        $pago = $this->pedidoAnterior(['coupon_id' => $c->id, 'coupon_code' => 'MITRE15', 'subtotal' => 200, 'discount' => 30, 'total' => 190]);
        $this->actingAs($admin);

        $this->get(route('admin.coupons.show', $c))->assertOk()
            ->assertSee('R$ 190,00')->assertSee('R$ 30,00')
            ->assertSee(route('admin.orders.index', ['cupom' => 'MITRE15']), false);
        $this->get(route('admin.orders.index', ['cupom' => 'mitre15']))->assertOk()->assertSee('APTK-'.str_pad($pago->id, 5, '0', STR_PAD_LEFT));
        $this->get(route('admin.orders.show', $pago))->assertSee('MITRE15')->assertSee('−R$ 30,00', false);

        // Usado não se apaga.
        $this->delete(route('admin.coupons.destroy', $c));
        $this->assertNotNull($c->fresh());
    }

    public function test_email_de_pedido_novo_mostra_o_cupom(): void
    {
        $o = $this->pedidoAnterior(['coupon_code' => 'MITRE15', 'discount' => 24.75]);
        $html = (new \App\Mail\PedidoNovo($o->load('items', 'user')))->render();

        $this->assertStringContainsString('Cupom MITRE15', $html);
        $this->assertStringContainsString('− R$ 24,75', $html);
    }

    public function test_seed_cria_os_dois_cupons_e_e_idempotente(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 23, 15, 0, 0, 'UTC'));
        $this->seed(CuponsEventosNov2026Seeder::class);

        foreach (['MITRE15', 'COMMUNIT15'] as $codigo) {
            $c = Coupon::where('code', $codigo)->sole();
            $this->assertSame('percent', $c->type);
            $this->assertEquals(15, (float) $c->value);
            $this->assertTrue($c->first_purchase_only);
            $this->assertSame(1, $c->max_per_customer);
            $this->assertTrue($c->active);
            $this->assertSame('31/12/2026 23:59', Coupon::paraCampo($c->expires_at));
            $this->assertSame('ativo', $c->situacao());
        }

        Coupon::where('code', 'MITRE15')->update(['value' => 20]);
        $this->seed(CuponsEventosNov2026Seeder::class);
        $this->assertSame(2, Coupon::count());
        $this->assertEquals(20, (float) Coupon::where('code', 'MITRE15')->value('value')); // não sobrescreve

        Carbon::setTestNow();
    }
}
