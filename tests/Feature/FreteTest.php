<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AppmaxService;
use App\Services\CartService;
use App\Services\FreteService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

/**
 * Frete estimado (leva 08): peso pelos ml do volume + embalagem, região pelo
 * CEP, tabela de config/frete.php + 20%, e o servidor recalculando no
 * fechamento do pedido.
 */
class FreteTest extends TestCase
{
    use RefreshDatabase;

    protected function produto(): Product
    {
        return Product::create([
            'name'        => 'Negroni Clássico',
            'slug'        => 'negroni-classico',
            'sku'         => 'NC750',
            'price'       => 210.00,
            'sizes'       => ['375 ml', '750 ml'],
            'size_prices' => ['375 ml' => 165.00, '750 ml' => 210.00],
            'stock_qty'   => 30,
            'active'      => true,
        ]);
    }

    /** Linhas no formato do CartService::items(). */
    protected function linhas(array $linhas): Collection
    {
        return collect($linhas)->map(fn ($l) => (object) ['product' => null, 'size' => $l[0], 'qty' => $l[1]]);
    }

    protected function frete(): FreteService
    {
        return app(FreteService::class);
    }

    // ---- peso ----------------------------------------------------------

    public function test_peso_um_750ml_da_2kg(): void
    {
        // 0,75 + 1 de embalagem = 1,75 → 2
        $this->assertSame(2, $this->frete()->pesoKg($this->linhas([['750 ml', 1]])));
    }

    public function test_peso_dois_375ml_da_3kg(): void
    {
        // (0,375 + 1) × 2 = 2,75 → 3
        $this->assertSame(3, $this->frete()->pesoKg($this->linhas([['375 ml', 2]])));
    }

    public function test_embalagem_por_pedido_soma_uma_vez(): void
    {
        config(['frete.embalagem_por' => 'pedido']);

        // 0,375 × 2 + 1 = 1,75 → 2
        $this->assertSame(2, $this->frete()->pesoKg($this->linhas([['375 ml', 2]])));
    }

    public function test_volume_sem_ml_legivel_usa_o_peso_padrao(): void
    {
        config(['frete.peso_padrao_kg' => 2.5]);

        $this->assertNull($this->frete()->ml('Kit presente'));
        $this->assertNull($this->frete()->ml(null));
        $this->assertSame(1500, $this->frete()->ml('1,5 L'));
        $this->assertSame(375, $this->frete()->ml('375ml'));
        // 2,5 + 1 de embalagem = 3,5 → 4
        $this->assertSame(4, $this->frete()->pesoKg($this->linhas([['Kit presente', 1]])));
    }

    // ---- região --------------------------------------------------------

    public function test_regiao_pelo_cep_de_cada_zona(): void
    {
        $casos = [
            '01310-100' => 'capital',     // Av. Paulista
            '06010-000' => 'capital',     // Osasco (Grande SP entra na faixa)
            '13010-000' => 'sp_interior', // Campinas
            '20040-020' => 'zona2',       // Rio
            '29010-000' => 'zona2',       // Vitória
            '30130-000' => 'zona2',       // BH
            '80000-000' => 'zona2',       // Curitiba
            '88010-000' => 'zona2',       // Florianópolis
            '90010-000' => 'zona3',       // Porto Alegre
            '70040-000' => 'zona3',       // Brasília
            '74000-000' => 'zona3',       // Goiânia
            '79000-000' => 'zona3',       // Campo Grande
            '78000-000' => 'zona3',       // Cuiabá
            '50000-000' => 'nordeste',    // Recife
            '40000-000' => 'nordeste',    // Salvador
            '65000-000' => 'nordeste',    // São Luís
            '66000-000' => 'norte',       // Belém
            '69000-000' => 'norte',       // Manaus
            '69900-000' => 'norte',       // Rio Branco
            '76800-000' => 'norte',       // Porto Velho
            '77000-000' => 'norte',       // Palmas
        ];

        foreach ($casos as $cep => $regiao) {
            $this->assertSame($regiao, $this->frete()->regiao($cep), $cep);
        }
    }

    public function test_cep_fora_das_faixas_e_erro_de_validacao(): void
    {
        $this->expectException(ValidationException::class);
        $this->frete()->regiao('00500-000');
    }

    // ---- valor ---------------------------------------------------------

    public function test_valor_aplica_adicional_por_kg_e_margem_de_20(): void
    {
        // 1 × 750 ml = 2 kg, capital. PAC (22 + 3) × 1,2 = 30,00; SEDEX (28 + 4) × 1,2 = 38,40.
        $opcoes = $this->frete()->cotar($this->linhas([['750 ml', 1]]), '01310-100');

        $this->assertSame([
            ['codigo' => 'pac', 'nome' => 'PAC', 'valor' => 30.0, 'prazo_dias' => 5],
            ['codigo' => 'sedex', 'nome' => 'SEDEX', 'valor' => 38.4, 'prazo_dias' => 3],
        ], $opcoes);
    }

    public function test_valor_arredonda_pra_cima_no_centavo(): void
    {
        config(['frete.tabela.capital.pac' => [22.01, 3, 3]]);

        // 100 ml + 1 de embalagem = 1,1 → 2 kg: (22,01 + 3) × 1,2 = 30,012 → 30,02
        $opcao = $this->frete()->opcao($this->linhas([['100 ml', 1]]), '01310-100', 'pac');
        $this->assertSame(30.02, $opcao['valor']);
    }

    // ---- endpoint ------------------------------------------------------

    public function test_cotar_devolve_pac_e_sedex_do_carrinho(): void
    {
        $p = $this->produto();
        app(CartService::class)->add($p->id, 2, '375 ml');

        // 3 kg, Recife (nordeste). PAC (60 + 14×2) × 1,2 = 105,60 · 12+2 dias;
        // SEDEX (110 + 25×2) × 1,2 = 192,00 · 6+2 dias.
        $this->postJson('/frete/cotar', ['zipcode' => '50000000'])
            ->assertOk()
            ->assertJson(['opcoes' => [
                ['nome' => 'PAC', 'valor' => 105.6, 'prazo_dias' => 14],
                ['nome' => 'SEDEX', 'valor' => 192, 'prazo_dias' => 8],
            ]]);
    }

    public function test_cotar_com_carrinho_vazio_e_erro(): void
    {
        $this->postJson('/frete/cotar', ['zipcode' => '01310-100'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cart');
    }

    public function test_cotar_cep_invalido_ou_fora_da_faixa_devolve_422(): void
    {
        $p = $this->produto();
        app(CartService::class)->add($p->id, 1, '750 ml');

        $this->postJson('/frete/cotar', ['zipcode' => 'abc'])->assertStatus(422)->assertJsonValidationErrors('zipcode');
        $this->postJson('/frete/cotar', [])->assertStatus(422);
        $this->postJson('/frete/cotar', ['zipcode' => '00500-000'])->assertStatus(422)->assertJsonValidationErrors('zipcode');
    }

    // ---- fechamento do pedido -----------------------------------------

    protected function dadosCheckout(array $extra = []): array
    {
        return $extra + [
            'name'            => 'Cliente Teste',
            'phone'           => '11 99999-0000',
            'cpf'             => '111.444.777-35',
            'zipcode'         => '80000-000',
            'street'          => 'Rua XV',
            'number'          => '100',
            'neighborhood'    => 'Centro',
            'city'            => 'Curitiba',
            'state'           => 'PR',
            'payment_method'  => 'pix',
            'shipping_method' => 'sedex',
        ];
    }

    protected function cliente(): User
    {
        return User::create([
            'name'     => 'Cliente',
            'email'    => 'cliente@aptk.test',
            'password' => bcrypt('secret123'),
            'role'     => 'customer',
            'maior_idade_em' => now(), // já declarou 18+ (leva 09)
        ]);
    }

    public function test_total_do_pedido_inclui_o_frete(): void
    {
        Queue::fake();
        $p = $this->produto();
        app(CartService::class)->add($p->id, 1, '750 ml');

        $order = app(OrderService::class)->placeFromCart($this->cliente(), $this->dadosCheckout());

        // Curitiba (zona2), 2 kg: SEDEX (60 + 12) × 1,2 = 86,40 · 3+2 dias
        $this->assertSame('210.00', $order->subtotal);
        $this->assertSame('86.40', $order->shipping_cost);
        $this->assertSame('296.40', $order->total);
        $this->assertSame('sedex', $order->shipping_method);
        $this->assertSame(5, $order->shipping_days);
        $this->assertSame('SEDEX · até 5 dias úteis', $order->shippingLabel());
    }

    public function test_checkout_recalcula_o_frete_e_ignora_valor_adulterado(): void
    {
        Queue::fake();
        $p = $this->produto();

        $enviado = null;
        $appmax = Mockery::mock(AppmaxService::class);
        $appmax->shouldReceive('createCustomer')->andReturn(10);
        $appmax->shouldReceive('createOrder')->andReturnUsing(function (Order $o) use (&$enviado) {
            $enviado = (float) $o->shipping_cost;

            return 20;
        });
        $appmax->shouldReceive('payWithPix')->andReturn([
            'pay_reference' => 'ref', 'qrcode' => 'qr', 'emv' => 'emv', 'expiration' => null,
        ]);
        $this->app->instance(AppmaxService::class, $appmax);

        $this->actingAs($this->cliente());
        app(CartService::class)->add($p->id, 1, '750 ml');

        // Valores forjados no POST: nenhum deles pode entrar.
        $this->post('/checkout', $this->dadosCheckout([
            'shipping_cost' => '0.01',
            'valor'         => '0.01',
            'prazo_dias'    => '1',
            'total'         => '210.01',
        ]))->assertRedirect();

        $order = Order::sole();
        $this->assertSame('86.40', $order->shipping_cost);
        $this->assertSame('296.40', $order->total);
        $this->assertSame(5, $order->shipping_days);
        $this->assertSame(86.4, $enviado); // o que foi pra Appmax
    }

    public function test_checkout_sem_opcao_de_frete_nao_fecha(): void
    {
        $p = $this->produto();
        $this->actingAs($this->cliente());
        app(CartService::class)->add($p->id, 1, '750 ml');

        $dados = $this->dadosCheckout();
        unset($dados['shipping_method']);

        $this->post('/checkout', $dados)->assertSessionHasErrors('shipping_method');
        $this->post('/checkout', $this->dadosCheckout(['shipping_method' => 'motoboy']))->assertSessionHasErrors('shipping_method');
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_com_cep_fora_da_faixa_nao_fecha(): void
    {
        $p = $this->produto();
        $this->actingAs($this->cliente());
        app(CartService::class)->add($p->id, 1, '750 ml');

        $this->post('/checkout', $this->dadosCheckout(['zipcode' => '00500-000']))->assertSessionHasErrors('zipcode');
        $this->assertSame(0, Order::count());
    }
}
