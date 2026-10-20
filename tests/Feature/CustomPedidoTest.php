<?php

namespace Tests\Feature;

use App\Exceptions\OutOfStockException;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\FreteService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Custom Simples (leva 11): compra direta pelo configurador. Garrafa 375 ml
 * a preço fixo (config custom.preco), personalização {bebida, texto, fonte,
 * cor} validada no add-to-cart e de novo no checkout, gravada no item como
 * o cliente enviou. Consome o estoque da 375 da bebida.
 */
class CustomPedidoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config([
            'appmax.environment'            => 'sandbox',
            'appmax.merchant_client_id'     => 'cid',
            'appmax.merchant_client_secret' => 'csecret',
            'appmax.webhook_token'          => 'tok-teste',
            'appmax.max_installments'       => 3,
            'appmax.min_installment_value'  => 30.00,
            'appmax.http_retry_sleep_ms'    => 0,
        ]);
    }

    /** Negroni inativo e sem preço no 375: elegível mesmo assim (está na config). */
    protected function negroni(array $sizeStock = ['375 ml' => 5, '750 ml' => 5], bool $ativo = false): Product
    {
        return Product::create([
            'name'        => 'Negroni Clássico',
            'slug'        => 'negroni-classico',
            'sku'         => 'NC375',
            'price'       => 210.00,
            'sizes'       => ['375 ml', '750 ml'],
            'size_prices' => ['750 ml' => 210.00],
            'size_stock'  => $sizeStock,
            'stock_qty'   => 30,
            'active'      => $ativo,
        ]);
    }

    protected function cliente(): User
    {
        return User::create([
            'name'     => 'Cliente',
            'email'    => 'cliente@aptk.test',
            'password' => bcrypt('secret123'),
            'role'     => 'customer',
            'maior_idade_em' => now(),
        ]);
    }

    protected function p(array $over = []): array
    {
        return $over + ['bebida' => 'negroni-classico', 'texto' => 'Bia & Eduardo', 'fonte' => 'PP Rader', 'cor' => 'preto'];
    }

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
            'installments'    => 1,
        ];
    }

    protected function fakeAppmax(array $over = []): void
    {
        $rotas = $over + [
            'POST auth'                     => fn () => Http::response(['access_token' => 'bearer-x', 'expires_in' => 3600]),
            'POST /v1/customers'            => fn () => Http::response(['data' => ['customer' => ['id' => 10]]], 201),
            'POST /v1/orders'               => fn () => Http::response(['data' => ['order' => ['id' => 20]]], 201),
            'POST /v1/payments/credit-card' => fn () => Http::response(['data' => ['payment' => ['pay_reference' => 'ref-cc']]], 201),
            'GET /v1/orders/20'             => fn () => Http::response(['data' => ['order' => ['id' => 20, 'status' => 'pendente']]]),
        ];

        Http::fake(function (Request $req) use ($rotas) {
            $host = (string) parse_url($req->url(), PHP_URL_HOST);
            $chave = $req->method().' '.(str_starts_with($host, 'auth.') ? 'auth' : parse_url($req->url(), PHP_URL_PATH));

            return isset($rotas[$chave]) ? $rotas[$chave]($req) : Http::response(['error' => ['message' => "rota não mockada: {$chave}"]], 599);
        });
    }

    protected function enviado(string $metodo, string $caminho): ?array
    {
        $req = collect(Http::recorded())
            ->map(fn ($par) => $par[0])
            ->first(fn (Request $r) => $r->method() === $metodo && str_ends_with((string) parse_url($r->url(), PHP_URL_PATH), $caminho));

        return $req?->data();
    }

    // ---- Página -------------------------------------------------------------

    public function test_pagina_mostra_o_configurador_sem_formulario_de_lead_e_com_full_custom(): void
    {
        $this->negroni();

        $html = $this->get('/custom')->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('custom.cart').'"', $html);
        $this->assertStringContainsString('value="negroni-classico"', $html);
        $this->assertStringContainsString('img/custom/negroni-classico.jpeg', $html);
        $this->assertStringContainsString('Adicionar ao carrinho', $html);
        $this->assertStringContainsString('As garrafas customizadas não acompanham caixa de presente.', $html);
        $this->assertStringContainsString('R$ 211,00', $html);
        foreach (['value="PP Rader"', 'value="Amithen"'] as $opcao) {
            $this->assertStringContainsString($opcao, $html);
        }
        $this->assertStringNotContainsString('name="cor"', $html); // texto sempre preto, sem seletor
        $this->assertStringContainsString('color:var(--color-ink)', $html);
        $this->assertStringNotContainsString('Solicitar minha personalização', $html);
        $this->assertStringNotContainsString('custom/pedido', $html);
        $this->assertStringContainsString('wa.me/', $html); // Full Custom segue
        // Bebida sem produto no catálogo não aparece.
        $this->assertStringNotContainsString('value="boulevardier"', $html);
    }

    public function test_bebidas_na_ordem_da_config(): void
    {
        $this->assertSame(
            ['negroni-classico', 'boulevardier', 'caipirinha-arazul', 'dry-martini', 'caju-amigo',
             'fitzgerald', 'moscow-mule', 'cosmopolitan', 'sur-lorange'],
            array_keys(config('custom.bebidas')),
        );
        foreach (config('custom.bebidas') as $b) {
            $this->assertFileExists(public_path($b['imagem']));
        }
    }

    // ---- Adicionar ao carrinho ---------------------------------------------

    public function test_adiciona_ao_carrinho_a_preco_fixo_e_nao_cria_lead(): void
    {
        $this->negroni();

        $this->post('/custom/carrinho', $this->p(['qty' => 2, 'preco' => '1.00']))
            ->assertRedirect(route('cart.index'));

        $item = app(CartService::class)->items()->sole();
        $this->assertSame('375 ml', $item->size);
        $this->assertSame(2, $item->qty);
        $this->assertSame(211.0, (float) $item->unit_price); // o "preco" do form é ignorado
        $this->assertSame($this->p(), $item->personalizacao);
        $this->assertSame(0, Lead::count());

        $this->get('/carrinho')->assertOk()
            ->assertSee('Bia &amp; Eduardo', false)
            ->assertSee('Editorial')
            ->assertSee('img/custom/negroni-classico.jpeg');
    }

    public function test_textos_diferentes_viram_linhas_separadas_e_o_mesmo_texto_soma(): void
    {
        $this->negroni();

        $this->post('/custom/carrinho', $this->p());
        $this->post('/custom/carrinho', $this->p(['texto' => 'Feliz 40, Ana']));
        $this->post('/custom/carrinho', $this->p());

        $itens = app(CartService::class)->items();
        $this->assertCount(2, $itens);
        $this->assertSame([2, 1], $itens->pluck('qty')->all());
        $this->assertNotSame($itens[0]->key, $itens[1]->key);
        $this->assertLessThanOrEqual(60, strlen($itens[0]->key)); // cabe no update/remove
    }

    public function test_linha_normal_continua_igual_e_convive_com_a_custom(): void
    {
        $p = $this->negroni();
        $cart = app(CartService::class);

        $cart->add($p->id, 1, '750 ml');
        $this->post('/custom/carrinho', $this->p());

        $this->assertNotNull($cart->line($p->id.':750 ml'));
        $this->assertSame(['product_id' => $p->id, 'size' => '750 ml', 'qty' => 1], $cart->line($p->id.':750 ml'));
        $this->assertEqualsCanonicalizing([210.0, 211.0], $cart->items()->pluck('unit_price')->map(fn ($v) => (float) $v)->all());
    }

    /** @dataProvider personalizacoesInvalidas */
    public function test_servidor_recusa_personalizacao_invalida(array $over, string $campo): void
    {
        $this->negroni();
        $this->get('/custom');

        $this->post('/custom/carrinho', $this->p($over))->assertSessionHasErrors($campo);

        $this->assertTrue(app(CartService::class)->isEmpty());
    }

    public static function personalizacoesInvalidas(): array
    {
        return [
            'bebida fora da lista'  => [['bebida' => 'negroni-piselli'], 'bebida'],
            'bebida sem produto'    => [['bebida' => 'boulevardier'], 'bebida'],
            'fonte fora da lista'   => [['fonte' => 'Comic Sans'], 'fonte'],
            'texto vazio'           => [['texto' => ''], 'texto'],
            'texto com 31'          => [['texto' => str_repeat('a', 31)], 'texto'],
            'texto com html'        => [['texto' => '<b>Oi</b>'], 'texto'],
            'texto com enter'       => [['texto' => "Bia\nEdu"], 'texto'],
            'texto com emoji'       => [['texto' => 'Saúde 🍸'], 'texto'],
        ];
    }

    public function test_cor_e_sempre_preto_mesmo_se_o_formulario_mandar_outra(): void
    {
        $this->negroni();

        $this->post('/custom/carrinho', $this->p(['cor' => 'cobre']))->assertRedirect(route('cart.index'));

        $this->assertSame('preto', app(CartService::class)->items()->sole()->personalizacao['cor']);
    }

    public function test_linha_antiga_com_outra_cor_e_recusada_no_checkout(): void
    {
        $p = $this->negroni();
        app(CartService::class)->add($p->id, 1, '375 ml', $this->p(['cor' => 'cobre']));

        try {
            app(OrderService::class)->placeFromCart($this->cliente(), $this->dados(['shipping_method' => null]));
            $this->fail('Deveria ter recusado a garrafa custom.');
        } catch (OutOfStockException $e) {
            $this->assertStringContainsString('O texto do rótulo é sempre preto.', $e->getMessage());
        }

        $this->assertSame(0, Order::count());
    }

    // ---- Fonte Blacksword: entra sozinha quando o arquivo existir ---------

    public function test_blacksword_le_de_public_fonts(): void
    {
        $this->assertSame(['fonts/blacksword.woff2', 'fonts/blacksword.ttf'], config('custom.fontes.Blacksword.arquivos'));
        $this->assertSame('Clássico', config('custom.fontes.Blacksword.nome'));
        $css = file_get_contents(public_path('css/aptk-tokens.css'));
        $this->assertStringContainsString("url('../fonts/blacksword.woff2')", $css);
        $this->assertStringContainsString("url('../fonts/blacksword.ttf')", $css);
    }

    public function test_sem_o_arquivo_a_blacksword_nao_aparece_nem_e_aceita(): void
    {
        config(['custom.fontes.Blacksword.arquivos' => ['fonts/nao-existe.woff2', 'fonts/nao-existe.ttf']]);
        $this->negroni();

        $html = $this->get('/custom')->assertOk()->getContent();
        $this->assertStringNotContainsString('value="Blacksword"', $html);
        $this->assertSame(['PP Rader', 'Amithen'], array_keys(app(\App\Services\CustomService::class)->fontes()));

        $this->post('/custom/carrinho', $this->p(['fonte' => 'Blacksword']))->assertSessionHasErrors('fonte');
        $this->assertTrue(app(CartService::class)->isEmpty());
    }

    public function test_com_o_arquivo_a_blacksword_aparece_e_e_aceita(): void
    {
        // Qualquer arquivo existente serve: o teste não depende da fonte real estar no projeto.
        config(['custom.fontes.Blacksword.arquivos' => ['fonts/nao-existe.woff2', 'fonts/Amithen.woff2']]);
        $this->negroni();

        $html = $this->get('/custom')->assertOk()->getContent();
        $this->assertStringContainsString('value="Blacksword"', $html);
        $this->assertStringContainsString('cst-f-blacksword', $html);

        $this->post('/custom/carrinho', $this->p(['fonte' => 'Blacksword']))->assertRedirect(route('cart.index'));
        $this->assertSame('Blacksword', app(CartService::class)->items()->sole()->personalizacao['fonte']);
    }

    public function test_pagina_abre_no_configurador_e_a_apresentacao_vem_depois(): void
    {
        $this->negroni();

        $html = $this->get('/custom')->assertOk()->getContent();

        $this->assertStringContainsString('id="cstMini"', $html);
        $this->assertLessThan(strpos($html, 'Sua história. Nossa alquimia.'), strpos($html, 'id="cstForm"'));
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('Personalize seu drink', $html);
        $this->assertStringNotContainsString('Personalize seu APTK', $html);
        $this->assertStringNotContainsString('Personalize seu APTK', $this->get('/')->getContent());
    }

    public function test_texto_com_30_caracteres_e_acento_passa(): void
    {
        $this->negroni();
        $texto = 'Feliz aniversário, João Paulo!'; // 30 caracteres

        $this->assertSame(30, mb_strlen($texto));
        $this->post('/custom/carrinho', $this->p(['texto' => $texto, 'fonte' => 'Amithen']))
            ->assertRedirect(route('cart.index'));
    }

    public function test_custom_consome_o_estoque_da_375_junto_com_a_linha_normal(): void
    {
        $p = $this->negroni(['375 ml' => 2, '750 ml' => 5]);
        app(CartService::class)->add($p->id, 1, '375 ml');

        $this->post('/custom/carrinho', $this->p(['qty' => 2]))
            ->assertSessionHas('stock_error', 'Só temos 2 unidade(s) de "Negroni Clássico (375 ml)" e você já tem 1 no carrinho.');

        $this->post('/custom/carrinho', $this->p())->assertRedirect(route('cart.index'));
        $this->assertSame(2, app(CartService::class)->qtyDoVolume($p->id, '375 ml'));
    }

    // ---- Checkout -----------------------------------------------------------

    public function test_checkout_grava_a_personalizacao_como_enviada_e_baixa_a_375(): void
    {
        $p = $this->negroni();
        $this->actingAs($u = $this->cliente());
        $this->post('/custom/carrinho', $this->p(['qty' => 2]));
        app(CartService::class)->add($p->id, 1, '375 ml'); // a 375 normal sem preço vale 0, não importa aqui

        $order = app(OrderService::class)->placeFromCart($u, $this->dados(['shipping_method' => null]));

        $item = $order->items()->whereNotNull('personalizacao')->sole();
        $this->assertSame($this->p(), $item->personalizacao);
        $this->assertSame('211.00', $item->unit_price);
        $this->assertSame('422.00', $item->total_price);
        $this->assertSame('375 ml', $item->size);
        $this->assertSame(2, $p->refresh()->size_stock['375 ml']); // 5 − 2 custom − 1 normal
    }

    public function test_checkout_soma_as_linhas_do_mesmo_volume_no_estoque(): void
    {
        $p = $this->negroni(['375 ml' => 2, '750 ml' => 5]);
        $cart = app(CartService::class);
        $cart->add($p->id, 1, '375 ml', $this->p());
        $cart->add($p->id, 1, '375 ml', $this->p(['texto' => 'Outro texto']));
        $cart->add($p->id, 1, '375 ml');

        $this->expectException(OutOfStockException::class);
        $this->expectExceptionMessage('tem só 2 unidade(s) em estoque e o carrinho pede 3');

        app(OrderService::class)->placeFromCart($this->cliente(), $this->dados(['shipping_method' => null]));
    }

    public function test_checkout_revalida_e_recusa_se_a_config_mudou(): void
    {
        $p = $this->negroni();
        app(CartService::class)->add($p->id, 1, '375 ml', $this->p());
        config(['custom.fontes' => ['Amithen' => config('custom.fontes.Amithen')]]); // PP Rader saiu

        try {
            app(OrderService::class)->placeFromCart($this->cliente(), $this->dados(['shipping_method' => null]));
            $this->fail('Deveria ter recusado a garrafa custom.');
        } catch (OutOfStockException $e) {
            $this->assertStringContainsString('garrafa personalizada de "Negroni Clássico"', $e->getMessage());
            $this->assertStringContainsString('estilos disponíveis', $e->getMessage());
        }

        $this->assertSame(0, Order::count());
    }

    public function test_preco_recalculado_da_config_no_checkout(): void
    {
        $p = $this->negroni();
        app(CartService::class)->add($p->id, 1, '375 ml', $this->p());
        config(['custom.preco' => 250.00]);

        $order = app(OrderService::class)->placeFromCart($this->cliente(), $this->dados(['shipping_method' => null]));

        $this->assertSame('250.00', $order->items->sole()->unit_price);
    }

    public function test_frete_da_custom_e_igual_ao_de_uma_375(): void
    {
        $p = $this->negroni();
        $cart = app(CartService::class);
        $frete = app(FreteService::class);

        $cart->add($p->id, 2, '375 ml', $this->p());
        $custom = $frete->opcao($cart->items(), '80000-000', 'sedex');
        $kgCustom = $frete->pesoKg($cart->items());
        $cart->clear();

        $cart->add($p->id, 2, '375 ml');
        $this->assertSame($kgCustom, $frete->pesoKg($cart->items()));
        $this->assertEquals($frete->opcao($cart->items(), '80000-000', 'sedex'), $custom);
    }

    public function test_appmax_recebe_nome_custom_e_sku_sem_repetir(): void
    {
        $this->fakeAppmax();
        $this->negroni();
        $this->actingAs($this->cliente());
        $this->post('/custom/carrinho', $this->p());
        $this->post('/custom/carrinho', $this->p(['texto' => 'Feliz 40, Ana']));

        $this->post('/checkout', $this->dados())->assertRedirect();

        $produtos = $this->enviado('POST', '/v1/orders')['products'];
        $this->assertSame(['NC375-375-CUSTOM', 'NC375-375-CUSTOM-2'], array_column($produtos, 'sku'));
        $this->assertSame('Negroni Clássico 375 ml (Custom)', $produtos[0]['name']);
        $this->assertSame(21100, $produtos[0]['unit_value']);
    }

    public function test_cartao_recusado_devolve_a_garrafa_custom_ao_carrinho_com_a_personalizacao(): void
    {
        $this->fakeAppmax([
            'POST /v1/payments/credit-card' => fn () => Http::response(['error' => ['message' => 'Transaction declined by issuer (code 05)']], 400),
        ]);
        $p = $this->negroni();
        $this->actingAs($this->cliente());
        $this->post('/custom/carrinho', $this->p());

        $this->post('/checkout', $this->dados())->assertRedirect(route('checkout.index'));

        $item = app(CartService::class)->items()->sole();
        $this->assertSame($this->p(), $item->personalizacao);
        $this->assertSame(211.0, (float) $item->unit_price);
        $this->assertSame(5, $p->refresh()->size_stock['375 ml']); // estoque devolvido
    }

    // ---- Exibição -----------------------------------------------------------

    protected function pedidoCustom(): Order
    {
        $p = $this->negroni();
        app(CartService::class)->add($p->id, 1, '375 ml', $this->p(['texto' => 'Feliz 40, Ana']));
        app(CartService::class)->add($p->id, 1, '750 ml');

        return app(OrderService::class)->placeFromCart(User::firstWhere('email', 'cliente@aptk.test') ?? $this->cliente(), $this->dados(['shipping_method' => null]));
    }

    public function test_admin_mostra_selo_na_listagem_e_bloco_de_personalizacao(): void
    {
        $order = $this->pedidoCustom();
        $normal = Order::create(['user_id' => $order->user_id, 'status' => 'pending', 'subtotal' => 10, 'total' => 10, 'payment_method' => 'pix', 'payment_status' => 'pending']);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@aptk.test', 'password' => bcrypt('x'), 'role' => 'admin']);

        $lista = $this->actingAs($admin)->get('/admin/pedidos')->assertOk()->getContent();
        $this->assertSame(1, substr_count($lista, '<span class="cst-selo">Custom</span>'));

        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Personalização · Negroni Clássico')
            ->assertSee('“Feliz 40, Ana”', false)
            ->assertSee('PP Rader')
            ->assertSee('Preto')
            ->assertSee('#020203')
            ->assertSee('img/custom/negroni-classico.jpeg');

        $this->get(route('admin.orders.show', $normal))->assertOk()->assertDontSee('Personalização ·');
    }

    public function test_meus_pedidos_e_sucesso_mostram_a_personalizacao(): void
    {
        $order = $this->pedidoCustom();
        $this->actingAs($order->user);

        $this->get(route('orders.show', $order))->assertOk()
            ->assertSee('Feliz 40, Ana')
            ->assertSee('Editorial')
            ->assertSee('img/custom/negroni-classico.jpeg');

        $this->get(route('checkout.success', $order))->assertOk()->assertSee('Feliz 40, Ana');
    }
}
