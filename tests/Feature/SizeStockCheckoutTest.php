<?php

namespace Tests\Feature;

use App\Exceptions\OutOfStockException;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Estoque por volume no fechamento do pedido — o caminho do dinheiro.
 * A revalidação roda DENTRO da transação do placeFromCart, com lock de
 * linha: é o que impede duas compras simultâneas do último item.
 */
class SizeStockCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function produto(array $sizeStock = ['375 ml' => 3, '750 ml' => 0]): Product
    {
        return Product::create([
            'name'        => 'Negroni Clássico',
            'slug'        => 'negroni-classico',
            'sku'         => 'NEG-750',
            'price'       => 149.90,
            'sizes'       => ['375 ml', '750 ml'],
            'size_prices' => ['375 ml' => 123.44, '750 ml' => 189.90],
            'size_stock'  => $sizeStock,
            'stock_qty'   => 30,
            'active'      => true,
        ]);
    }

    protected function user(string $email = 'cliente@aptk.test'): User
    {
        return User::create([
            'name'     => 'Cliente',
            'email'    => $email,
            'password' => bcrypt('secret123'),
            'role'     => 'customer',
        ]);
    }

    protected function dadosCheckout(): array
    {
        return [
            'name'           => 'Cliente Teste',
            'phone'          => '11 99999-0000',
            'cpf'            => '111.444.777-35',
            'zipcode'        => '01310-100',
            'street'         => 'Avenida Paulista',
            'number'         => '1000',
            'neighborhood'   => 'Bela Vista',
            'city'           => 'São Paulo',
            'state'          => 'SP',
            'payment_method' => 'pix',
        ];
    }

    public function test_pedido_dentro_do_saldo_baixa_o_estoque_do_volume(): void
    {
        $p = $this->produto();
        app(CartService::class)->add($p->id, 2, '375 ml');

        $order = app(OrderService::class)->placeFromCart($this->user(), $this->dadosCheckout());

        $p->refresh();
        $this->assertSame(1, $p->size_stock['375 ml']);  // 3 - 2
        $this->assertSame(0, $p->size_stock['750 ml']);  // intocado
        $this->assertSame(28, $p->stock_qty);            // agregado segue baixando como sempre
        $this->assertSame(2, (int) $order->items()->first()->qty);
    }

    public function test_saldo_insuficiente_diz_qual_item_e_quanto_sobrou(): void
    {
        $p = $this->produto();
        app(CartService::class)->add($p->id, 5, '375 ml');

        try {
            app(OrderService::class)->placeFromCart($this->user(), $this->dadosCheckout());
            $this->fail('Deveria ter lançado OutOfStockException.');
        } catch (OutOfStockException $e) {
            // Nada de "estoque insuficiente" genérico: o cartão já está na mão.
            $this->assertStringContainsString('Negroni Clássico (375 ml)', $e->getMessage());
            $this->assertStringContainsString('3 unidade', $e->getMessage());
            $this->assertStringContainsString('5', $e->getMessage());
        }

        // Nada foi gravado nem baixado.
        $this->assertSame(3, $p->refresh()->size_stock['375 ml']);
        $this->assertSame(0, \App\Models\Order::count());
    }

    public function test_corrida_dois_pedidos_pelo_ultimo_item_so_um_passa(): void
    {
        // Simula a corrida: o pedido B é montado com o carrinho ACHANDO que
        // há estoque (leu antes do pedido A fechar) e só descobre na
        // revalidação dentro da transação — que relê o saldo com
        // lockForUpdate. No MySQL o lock serializa até requisições
        // simultâneas de verdade; aqui exercitamos a releitura.
        $p = $this->produto(['375 ml' => 1, '750 ml' => 0]);
        $orders = app(OrderService::class);

        $cart = app(CartService::class);
        $cart->add($p->id, 1, '375 ml');
        $itensB = $cart->items(); // carrinho de B, montado ANTES de A fechar
        $this->assertCount(1, $itensB);

        // A fecha primeiro e leva a última unidade.
        $orders->placeFromCart($this->user('a@aptk.test'), $this->dadosCheckout());
        $this->assertSame(0, $p->refresh()->size_stock['375 ml']);

        // B tenta fechar o mesmo carrinho: a revalidação dentro da
        // transação vê 0 e recusa com mensagem clara.
        $cart->add($p->id, 1, '375 ml'); // recompõe a sessão de B
        try {
            $orders->placeFromCart($this->user('b@aptk.test'), $this->dadosCheckout());
            $this->fail('O segundo pedido deveria ter sido recusado.');
        } catch (OutOfStockException $e) {
            $this->assertStringContainsString('Negroni Clássico (375 ml)', $e->getMessage());
            $this->assertStringContainsString('esgotou', $e->getMessage());
        }

        $this->assertSame(1, \App\Models\Order::count()); // só o de A
    }

    public function test_checkout_http_volta_ao_carrinho_com_a_mensagem(): void
    {
        Http::fake(); // Appmax nunca deve ser chamada nesse caminho

        $p = $this->produto();
        $user = $this->user();

        $this->actingAs($user)
            ->post('/carrinho/adicionar', ['product_id' => $p->id, 'qty' => 2, 'size' => '375 ml']);

        // Estoque caiu entre o carrinho e o pagamento (outro cliente comprou).
        $p->update(['size_stock' => ['375 ml' => 1, '750 ml' => 0]]);

        $this->actingAs($user)
            ->post('/checkout', $this->dadosCheckout())
            ->assertRedirect('/carrinho');

        $this->assertStringContainsString('Negroni Clássico (375 ml)', session('stock_error'));
        $this->assertStringContainsString('1 unidade', session('stock_error'));
        Http::assertNothingSent();
    }

    public function test_cancelamento_devolve_o_saldo_do_volume(): void
    {
        $p = $this->produto();
        app(CartService::class)->add($p->id, 2, '375 ml');

        $orders = app(OrderService::class);
        $order = $orders->placeFromCart($this->user(), $this->dadosCheckout());
        $this->assertSame(1, $p->refresh()->size_stock['375 ml']);

        $orders->cancelAndRestoreStock($order);

        $p->refresh();
        $this->assertSame(3, $p->size_stock['375 ml']); // devolvido
        $this->assertSame(30, $p->stock_qty);
    }

    public function test_produto_nao_conciliado_fecha_pedido_como_hoje_sem_limite(): void
    {
        $p = Product::create([
            'name'      => 'Fitzgerald',
            'slug'      => 'fitzgerald',
            'sku'       => 'FITZ-750',
            'price'     => 139.90,
            'sizes'     => ['375 ml', '750 ml'],
            'stock_qty' => 30,
            'active'    => true,
        ]);

        app(CartService::class)->add($p->id, 50, '750 ml');

        // 50 > 30, e mesmo assim passa: é o comportamento de hoje, e produto
        // não conciliado NÃO pode começar a esgotar por causa desta etapa.
        $order = app(OrderService::class)->placeFromCart($this->user(), $this->dadosCheckout());

        $this->assertSame(50, (int) $order->items()->first()->qty);
        $this->assertSame(0, $p->refresh()->stock_qty); // agregado trava no zero, como antes
    }
}
