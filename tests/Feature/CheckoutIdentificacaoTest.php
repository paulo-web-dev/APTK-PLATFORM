<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Leva 09 — compra por impulso: carrinho e checkout abertos a visitante; a
 * conta é criada (ou o login é feito) dentro do checkout, por AJAX, sem
 * perder o carrinho e sem passar pela área "minha conta".
 */
class CheckoutIdentificacaoTest extends TestCase
{
    use RefreshDatabase;

    protected Product $produto;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config([
            'appmax.client_id'     => 'cid',
            'appmax.client_secret' => 'csecret',
            'appmax.api_url'       => 'https://api.appmax.com.br',
            'appmax.auth_url'      => 'https://auth.appmax.com.br/oauth2/token',
        ]);

        Http::fake(function (Request $req) {
            $host = (string) parse_url($req->url(), PHP_URL_HOST);
            $path = (string) parse_url($req->url(), PHP_URL_PATH);

            return match (true) {
                str_starts_with($host, 'auth.')     => Http::response(['access_token' => 'bearer-x', 'expires_in' => 3600]),
                $path === '/v1/customers'           => Http::response(['data' => ['customer' => ['id' => 10]]], 201),
                $path === '/v1/orders'              => Http::response(['data' => ['order' => ['id' => 20]]], 201),
                $path === '/v1/payments/pix'        => Http::response(['data' => ['payment' => [
                    'pix_qrcode' => 'iVBOR', 'pix_emv' => '0002', 'pix_expiration_date' => '2026-10-18 15:00:00',
                ]]]),
                default => Http::response(['data' => ['order' => ['status' => 'pendente']]]),
            };
        });

        $this->produto = Product::create([
            'name' => 'Negroni Clássico', 'slug' => 'negroni-classico', 'sku' => 'NC750', 'price' => 210,
            'sizes' => ['750 ml'], 'size_prices' => ['750 ml' => 210], 'stock_qty' => 30, 'active' => true,
        ]);
    }

    protected function noCarrinho(int $qty = 2): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->produto->id, 'qty' => $qty, 'size' => '750 ml']);
        $this->assertSame($qty, app(CartService::class)->count());
    }

    protected function usuario(bool $declarou = true, string $email = 'ana@aptk.test'): User
    {
        return User::create([
            'name' => 'Ana Souza', 'email' => $email, 'password' => bcrypt('senha-forte-123'),
            'role' => 'customer', 'maior_idade_em' => $declarou ? now() : null,
        ]);
    }

    protected function entrega(array $extra = []): array
    {
        return $extra + [
            'name' => 'Ana Souza', 'phone' => '11999990000', 'cpf' => '191.000.000-00', 'zipcode' => '01310-100',
            'street' => 'Av. Paulista', 'number' => '1000', 'neighborhood' => 'Bela Vista', 'city' => 'São Paulo',
            'state' => 'SP', 'payment_method' => 'pix', 'shipping_method' => 'pac',
        ];
    }

    // ---- fluxo do visitante -----------------------------------------------

    public function test_visitante_ve_carrinho_e_checkout_com_identificacao(): void
    {
        $this->noCarrinho();

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Já possui conta?')
            ->assertSee(route('login', ['voltar' => 'carrinho']), false);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Identificação')
            ->assertSee('Precisamos da sua conta para você acompanhar o pedido e a entrega.')
            ->assertSee('id="identificacao"', false);

        // Formulário do pedido e botão vêm renderizados, mas ocultos.
        $html = $this->get(route('checkout.index'))->getContent();
        $this->assertMatchesRegularExpression('/id="dadosPedido"\s+hidden/', $html);
        $this->assertMatchesRegularExpression('/id="confirmarPedido"[^>]*\shidden/', $html);

        // Fechar o pedido continua exigindo conta.
        $this->post(route('checkout.store'), $this->entrega())->assertRedirect(route('login'));
        $this->assertSame(0, Order::count());
    }

    public function test_visitante_cria_conta_no_checkout_e_finaliza_sem_perder_o_carrinho(): void
    {
        $this->noCarrinho(2);

        $this->postJson(route('checkout.identificacao.email'), ['email' => 'novo@aptk.test'])
            ->assertOk()->assertExactJson(['conta' => false]);

        $this->postJson(route('checkout.identificacao.cadastrar'), [
            'name' => 'Bruno Lima', 'email' => 'Novo@aptk.test', 'password' => 'senha-forte-123', 'maior_idade' => true,
        ])
            ->assertOk()
            ->assertJson(['ok' => true, 'nome' => 'Bruno Lima', 'email' => 'novo@aptk.test', 'precisa_maior_idade' => false])
            ->assertJsonStructure(['csrf']);

        $this->assertAuthenticated();
        $user = User::where('email', 'novo@aptk.test')->sole();
        $this->assertNotNull($user->maior_idade_em);
        $this->assertSame('customer', $user->role);
        $this->assertSame(2, app(CartService::class)->count()); // carrinho intacto

        $this->get(route('checkout.index'))->assertOk()->assertDontSee('id="identificacao"', false)->assertSee('novo@aptk.test');

        $this->post(route('checkout.store'), $this->entrega())->assertRedirect();

        $order = Order::sole();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame(2, (int) $order->items()->sole()->qty);
    }

    public function test_cadastro_sem_a_declaracao_de_18_anos_e_recusado(): void
    {
        $this->noCarrinho();

        $this->postJson(route('checkout.identificacao.cadastrar'), [
            'name' => 'Bruno', 'email' => 'novo@aptk.test', 'password' => 'senha-forte-123',
        ])->assertStatus(422)->assertJsonValidationErrors(['maior_idade' => 'Para comprar, confirme que você tem 18 anos ou mais.']);

        $this->postJson(route('checkout.identificacao.cadastrar'), [
            'name' => 'Bruno', 'email' => 'novo@aptk.test', 'password' => 'senha-forte-123', 'maior_idade' => false,
        ])->assertStatus(422)->assertJsonValidationErrors('maior_idade');

        // O /register normal também exige.
        $this->post('/register', [
            'name' => 'Bruno', 'email' => 'novo@aptk.test', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('maior_idade');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_cadastro_com_email_que_ja_tem_conta_e_recusado(): void
    {
        $this->usuario();

        $this->postJson(route('checkout.identificacao.cadastrar'), [
            'name' => 'Outra', 'email' => 'ana@aptk.test', 'password' => 'senha-forte-123', 'maior_idade' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    // ---- usuário existente -------------------------------------------------

    public function test_usuario_existente_entra_no_checkout_e_finaliza(): void
    {
        $ana = $this->usuario();
        $this->noCarrinho();

        $this->postJson(route('checkout.identificacao.email'), ['email' => 'ana@aptk.test'])
            ->assertOk()->assertExactJson(['conta' => true]);

        $this->postJson(route('checkout.identificacao.entrar'), ['email' => 'ana@aptk.test', 'password' => 'senha-errada'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertGuest();

        $this->postJson(route('checkout.identificacao.entrar'), ['email' => 'ana@aptk.test', 'password' => 'senha-forte-123'])
            ->assertOk()
            ->assertJson(['ok' => true, 'nome' => 'Ana Souza', 'email' => 'ana@aptk.test', 'precisa_maior_idade' => false]);

        $this->assertAuthenticatedAs($ana);
        $this->assertSame(2, app(CartService::class)->count());

        $this->post(route('checkout.store'), $this->entrega())->assertRedirect();
        $this->assertSame($ana->id, Order::sole()->user_id);
    }

    public function test_conta_antiga_sem_declaracao_marca_a_checkbox_no_checkout_e_grava_a_data(): void
    {
        $antiga = $this->usuario(declarou: false);
        $this->noCarrinho();

        $this->postJson(route('checkout.identificacao.entrar'), ['email' => 'ana@aptk.test', 'password' => 'senha-forte-123'])
            ->assertOk()->assertJson(['precisa_maior_idade' => true]);

        $this->get(route('checkout.index'))->assertOk()->assertSee('name="maior_idade"', false);

        // Sem a checkbox: não fecha.
        $this->post(route('checkout.store'), $this->entrega())
            ->assertSessionHasErrors(['maior_idade' => 'Para finalizar, confirme que você tem 18 anos ou mais.']);
        $this->assertSame(0, Order::count());
        $this->assertNull($antiga->refresh()->maior_idade_em);

        $this->post(route('checkout.store'), $this->entrega(['maior_idade' => '1']))->assertRedirect();
        $this->assertSame(1, Order::count());
        $this->assertNotNull($antiga->refresh()->maior_idade_em);

        // Já declarou: a checkbox não aparece mais.
        $this->noCarrinho(1);
        $this->get(route('checkout.index'))->assertOk()->assertDontSee('name="maior_idade"', false);
    }

    // ---- /login e /register normais ---------------------------------------

    public function test_login_com_carrinho_cheio_vai_pro_carrinho_e_preserva_o_carrinho(): void
    {
        $this->usuario();
        $this->noCarrinho(3);
        $sessaoAntes = session()->getId();

        $this->post('/login', ['email' => 'ana@aptk.test', 'password' => 'senha-forte-123'])
            ->assertRedirect(route('cart.index'));

        $this->assertNotSame($sessaoAntes, session()->getId()); // regenerate() trocou o id...
        $this->assertSame(3, app(CartService::class)->count()); // ...e o carrinho ficou
    }

    public function test_login_que_veio_do_checkout_volta_pro_checkout(): void
    {
        $this->usuario();
        $this->noCarrinho();

        $this->get(route('login', ['voltar' => 'checkout']))->assertOk();
        $this->post('/login', ['email' => 'ana@aptk.test', 'password' => 'senha-forte-123'])
            ->assertRedirect(route('checkout.index'));
    }

    public function test_login_sem_carrinho_vai_pro_intended_ou_home_nunca_minha_conta(): void
    {
        $this->usuario();

        $this->post('/login', ['email' => 'ana@aptk.test', 'password' => 'senha-forte-123'])
            ->assertRedirect(route('home'));
        $this->post('/logout');

        $this->get(route('orders.index'))->assertRedirect(route('login')); // grava o intended
        $this->post('/login', ['email' => 'ana@aptk.test', 'password' => 'senha-forte-123'])
            ->assertRedirect(route('orders.index'));
    }

    public function test_voltar_so_aceita_destinos_da_lista(): void
    {
        $this->usuario();

        $this->get('/login?voltar=https://evil.example')->assertOk();
        $this->post('/login', ['email' => 'ana@aptk.test', 'password' => 'senha-forte-123'])
            ->assertRedirect(route('home'));
    }

    public function test_cadastro_pelo_register_com_carrinho_volta_pro_carrinho(): void
    {
        $this->noCarrinho();

        $this->post('/register', [
            'name' => 'Bruno', 'email' => 'bruno@aptk.test', 'password' => 'password',
            'password_confirmation' => 'password', 'maior_idade' => '1',
        ])->assertRedirect(route('cart.index'));

        $this->assertSame(2, app(CartService::class)->count());
    }

    // ---- proteções ---------------------------------------------------------

    public function test_verificacao_de_email_tem_mesma_resposta_e_latencia_minima(): void
    {
        $this->usuario();

        foreach (['ana@aptk.test' => true, 'ninguem@aptk.test' => false] as $email => $conta) {
            $t = microtime(true);
            $this->postJson(route('checkout.identificacao.email'), ['email' => $email])
                ->assertOk()->assertExactJson(['conta' => $conta]);
            $this->assertGreaterThanOrEqual(0.39, microtime(true) - $t, $email);
        }
    }

    public function test_rate_limit_na_verificacao_de_email_e_no_login(): void
    {
        $this->usuario();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('checkout.identificacao.email'), ['email' => "x{$i}@aptk.test"])->assertOk();
        }
        $this->postJson(route('checkout.identificacao.email'), ['email' => 'x11@aptk.test'])->assertStatus(429);

        // Login: o mesmo limite do Breeze (5 erros por e-mail+IP).
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('checkout.identificacao.entrar'), ['email' => 'ana@aptk.test', 'password' => 'errada'])
                ->assertStatus(422);
        }
        $this->postJson(route('checkout.identificacao.entrar'), ['email' => 'ana@aptk.test', 'password' => 'senha-forte-123'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', fn ($msg) => str_contains($msg, 'segundos') || str_contains($msg, 'seconds'));
        $this->assertGuest();
    }

    public function test_checkout_vazio_volta_pro_carrinho_mesmo_pra_visitante(): void
    {
        $this->get(route('checkout.index'))->assertRedirect(route('cart.index'));
    }
}
