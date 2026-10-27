<?php

namespace Tests\Feature;

use App\Mail\PedidoNovo;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Rules\Cpf;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * CPF do cliente para cadastro na Linx: obrigatório e com dígito
 * verificador em todos os métodos, gravado no pedido mesmo com o pagamento
 * recusado, salvo no cadastro pra próxima compra e visível no admin e no
 * aviso por e-mail.
 */
class CpfClienteTest extends TestCase
{
    use RefreshDatabase;

    protected array $rotas = [];

    protected bool $fakeRegistrado = false;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config([
            'pedidos.notificar'             => ['equipe@aptkspirits.com'],
            'microvix.envia_pedido'         => false,
            'appmax.environment'            => 'sandbox',
            'appmax.merchant_client_id'     => 'cid',
            'appmax.merchant_client_secret' => 'csecret',
            'appmax.max_installments'       => 3,
            'appmax.min_installment_value'  => 30.00,
            'appmax.http_retry_sleep_ms'    => 0,
        ]);
    }

    protected function cliente(array $extra = []): User
    {
        return User::create($extra + [
            'name' => 'Cliente', 'email' => 'cliente@aptk.test', 'password' => bcrypt('secret123'),
            'role' => 'customer', 'maior_idade_em' => now(),
        ]);
    }

    protected function comCarrinho(?User $user = null): User
    {
        $p = Product::create([
            'name' => 'Negroni Clássico', 'slug' => 'negroni-classico', 'sku' => 'NC750', 'price' => 210.00,
            'sizes' => ['750 ml'], 'size_prices' => ['750 ml' => 210.00], 'size_stock' => ['750 ml' => 5],
            'stock_qty' => 30, 'active' => true,
        ]);
        $user ??= $this->cliente();
        $this->actingAs($user);
        app(CartService::class)->add($p->id, 1, '750 ml');

        return $user;
    }

    protected function dados(array $extra = []): array
    {
        return $extra + [
            'name' => 'Cliente Teste', 'phone' => '11 99999-0000', 'cpf' => '123.456.789-09',
            'zipcode' => '80000-000', 'street' => 'Rua XV', 'number' => '100', 'neighborhood' => 'Centro',
            'city' => 'Curitiba', 'state' => 'PR', 'payment_method' => 'cartao', 'shipping_method' => 'sedex',
            'appmax_token' => 'tok-cartao-123', 'card_name' => 'Cliente Teste', 'installments' => 1,
        ];
    }

    protected function fakeAppmax(array $over = []): void
    {
        $this->rotas = $over + [
            'POST auth'                     => fn () => Http::response(['access_token' => 'bearer-x', 'expires_in' => 3600]),
            'POST /v1/customers'            => fn () => Http::response(['data' => ['customer' => ['id' => 10]]], 201),
            'POST /v1/orders'               => fn () => Http::response(['data' => ['order' => ['id' => 20]]], 201),
            'POST /v1/payments/credit-card' => fn () => Http::response(['data' => ['payment' => ['pay_reference' => 'ref-cc']]], 201),
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

    protected function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@aptk.test', 'password' => bcrypt('x12345678'), 'role' => 'admin']);
    }

    protected function pedido(User $user, array $extra = []): Order
    {
        return Order::create($extra + [
            'user_id' => $user->id, 'status' => 'processing', 'payment_status' => 'paid', 'payment_method' => 'pix',
            'subtotal' => 210, 'discount' => 0, 'shipping_cost' => 0, 'total' => 210,
            'ship_name' => 'Cliente', 'ship_cpf' => '12345678909', 'ship_phone' => '11 98888-7777',
        ]);
    }

    // ---- regra ----------------------------------------------------------------

    public function test_regra_confere_digito_verificador(): void
    {
        foreach (['123.456.789-09', '12345678909', '111.444.777-35', '191.000.000-00'] as $ok) {
            $this->assertTrue(Cpf::valido($ok), $ok);
        }
        foreach (['123.456.789-00', '111.111.111-11', '000.000.000-00', '1234567890', '', null] as $ruim) {
            $this->assertFalse(Cpf::valido($ruim), (string) $ruim);
        }
        $this->assertSame('123.456.789-09', cpf_formatado('12345678909'));
        $this->assertNull(cpf_formatado(null));
    }

    // ---- checkout --------------------------------------------------------------

    public static function metodos(): array
    {
        return [['pix'], ['cartao'], ['boleto']];
    }

    /** @dataProvider metodos */
    public function test_cpf_obrigatorio_e_valido_em_todos_os_metodos(string $metodo): void
    {
        $this->fakeAppmax();
        $this->comCarrinho();

        $this->post('/checkout', $this->dados(['payment_method' => $metodo, 'cpf' => '']))
            ->assertSessionHasErrors('cpf');
        $this->post('/checkout', $this->dados(['payment_method' => $metodo, 'cpf' => '123.456.789-00']))
            ->assertSessionHasErrors(['cpf' => Cpf::MENSAGEM]);
        $this->post('/checkout', $this->dados(['payment_method' => $metodo, 'cpf' => '111.111.111-11']))
            ->assertSessionHasErrors(['cpf' => Cpf::MENSAGEM]);

        $this->assertSame(0, Order::count());
        Http::assertNothingSent();
    }

    public function test_cpf_fica_no_pedido_mesmo_com_pagamento_recusado(): void
    {
        $this->fakeAppmax([
            'POST /v1/payments/credit-card' => fn () => Http::response(['error' => ['message' => 'declined']], 400),
        ]);
        $user = $this->comCarrinho();

        $this->post('/checkout', $this->dados())->assertRedirect(route('checkout.index'));

        $order = Order::sole();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('12345678909', $order->ship_cpf);
        $this->assertSame('12345678909', $user->refresh()->cpf);
    }

    public function test_cpf_vai_pro_cadastro_so_se_estiver_vazio(): void
    {
        $this->fakeAppmax();
        $user = $this->comCarrinho($this->cliente(['cpf' => '11144477735']));

        $this->post('/checkout', $this->dados())->assertRedirect();

        $this->assertSame('12345678909', Order::sole()->ship_cpf); // o pedido leva o digitado
        $this->assertSame('11144477735', $user->refresh()->cpf);   // cadastro não é sobrescrito
    }

    public function test_checkout_vem_com_o_cpf_do_cadastro(): void
    {
        $this->comCarrinho($this->cliente(['cpf' => '12345678909']));

        $this->get('/checkout')->assertOk()->assertSee('value="123.456.789-09"', false);
    }

    public function test_login_no_checkout_devolve_o_cpf_salvo(): void
    {
        $this->cliente(['cpf' => '12345678909']);

        $this->postJson(route('checkout.identificacao.entrar'), ['email' => 'cliente@aptk.test', 'password' => 'secret123'])
            ->assertOk()
            ->assertJson(['cpf' => '123.456.789-09']);
    }

    // ---- admin -----------------------------------------------------------------

    public function test_detalhe_do_pedido_mostra_cpf_telefone_e_email_com_copiar(): void
    {
        $order = $this->pedido($this->cliente());

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSeeInOrder(['CPF', '123.456.789-09', 'Copiar', 'Telefone', '11 98888-7777', 'Copiar', 'E-mail', 'cliente@aptk.test', 'Copiar']);
    }

    public function test_pedido_antigo_sem_cpf_no_snapshot_usa_o_do_cadastro(): void
    {
        $order = $this->pedido($this->cliente(['cpf' => '11144477735']), ['ship_cpf' => null]);

        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))->assertSee('111.444.777-35');
    }

    public function test_listagem_mostra_cpf_e_busca_por_cpf(): void
    {
        $a = $this->pedido($this->cliente());
        $outro = User::create(['name' => 'Outra Pessoa', 'email' => 'outra@aptk.test', 'password' => bcrypt('x12345678'), 'role' => 'customer']);
        $b = $this->pedido($outro, ['ship_cpf' => '11144477735']);
        $numA = 'APTK-'.str_pad((string) $a->id, 5, '0', STR_PAD_LEFT);
        $numB = 'APTK-'.str_pad((string) $b->id, 5, '0', STR_PAD_LEFT);

        $this->actingAs($this->admin());

        $this->get(route('admin.orders.index'))->assertSee('123.456.789-09')->assertSee('111.444.777-35');

        foreach (['123.456.789-09', '12345678909'] as $busca) {
            $this->get(route('admin.orders.index', ['q' => $busca]))->assertSee($numA)->assertDontSee($numB);
        }
        $this->get(route('admin.orders.index', ['q' => 'outra@aptk']))->assertSee($numB)->assertDontSee($numA);
        $this->get(route('admin.orders.index', ['q' => $numB]))->assertSee($numB)->assertDontSee($numA);
    }

    // ---- e-mail ----------------------------------------------------------------

    public function test_aviso_por_email_traz_o_cpf(): void
    {
        $order = $this->pedido($this->cliente());

        $mail = new PedidoNovo($order);

        $this->assertStringContainsString('CPF 123.456.789-09', $mail->render());
        $this->assertStringContainsString('CPF 123.456.789-09', view('emails.pedido-novo-texto', $mail->dados())->render());
    }
}
