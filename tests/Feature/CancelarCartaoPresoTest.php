<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * pedidos:cancelar-cartao-preso — a limpeza da janela residual do checkout
 * (ESTADO-ATUAL §3): pedido pending de CARTÃO parado é processo de pagamento
 * morto entre o commit local e a Appmax; o estoque fica preso. Pix e boleto
 * NUNCA entram — esses expiram pelo webhook da Appmax.
 */
class CancelarCartaoPresoTest extends TestCase
{
    use RefreshDatabase;

    protected function produto(): Product
    {
        return Product::create([
            'name'        => 'Negroni Clássico',
            'slug'        => 'negroni-classico',
            'sku'         => 'NEG-750',
            'price'       => 149.90,
            'sizes'       => ['375 ml', '750 ml'],
            'size_prices' => ['375 ml' => 123.44, '750 ml' => 189.90],
            'size_stock'  => ['375 ml' => 3, '750 ml' => 5],
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

    protected function pedido(Product $p, string $metodo, int $idadeMinutos, string $email = 'cliente@aptk.test', int $qty = 2): Order
    {
        app(CartService::class)->add($p->id, $qty, '375 ml');

        $order = app(OrderService::class)->placeFromCart($this->user($email), [
            'name'           => 'Cliente Teste',
            'phone'          => '11 99999-0000',
            'cpf'            => '111.444.777-35',
            'zipcode'        => '01310-100',
            'street'         => 'Avenida Paulista',
            'number'         => '1000',
            'neighborhood'   => 'Bela Vista',
            'city'           => 'São Paulo',
            'state'          => 'SP',
            'payment_method' => $metodo,
        ]);

        DB::table('orders')->where('id', $order->id)
            ->update(['created_at' => now()->subMinutes($idadeMinutos)]);

        return $order->refresh();
    }

    public function test_cancela_cartao_pendente_velho_e_devolve_o_estoque(): void
    {
        $p = $this->produto();
        $order = $this->pedido($p, 'cartao', 90);
        $this->assertSame(1, $p->refresh()->size_stock['375 ml']); // 3 - 2, preso

        $this->artisan('pedidos:cancelar-cartao-preso')
            ->expectsOutputToContain('1 pedido(s) cancelado(s)')
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('failed', $order->payment_status);
        $this->assertStringContainsString('Cancelado automaticamente', $order->notes);

        $p->refresh();
        $this->assertSame(3, $p->size_stock['375 ml']); // devolvido
        $this->assertSame(30, $p->stock_qty);
    }

    public function test_cartao_pendente_recente_fica_intocado(): void
    {
        $p = $this->produto();
        $order = $this->pedido($p, 'cartao', 10); // dentro da janela de 60 min

        $this->artisan('pedidos:cancelar-cartao-preso')->assertSuccessful();

        $this->assertSame('pending', $order->refresh()->status);
        $this->assertSame(1, $p->refresh()->size_stock['375 ml']); // segue reservado
    }

    public function test_pix_e_boleto_pendentes_nunca_entram(): void
    {
        // Esses se resolvem pelo webhook de expiração da Appmax — cancelar
        // aqui mataria um Pix que o cliente ainda vai pagar.
        $p = $this->produto();
        $pix = $this->pedido($p, 'pix', 500, 'pix@aptk.test', 1);
        $boleto = $this->pedido($p, 'boleto', 500, 'boleto@aptk.test', 1);

        $this->artisan('pedidos:cancelar-cartao-preso')->assertSuccessful();

        $this->assertSame('pending', $pix->refresh()->status);
        $this->assertSame('pending', $boleto->refresh()->status);
    }

    public function test_cartao_pago_fica_intocado_mesmo_velho(): void
    {
        // Caso "cobrou e o webhook já marcou pago": não é preso, é venda.
        $p = $this->produto();
        $order = $this->pedido($p, 'cartao', 90);
        $order->update(['status' => 'processing', 'payment_status' => 'paid', 'paid_at' => now()]);

        $this->artisan('pedidos:cancelar-cartao-preso')->assertSuccessful();

        $this->assertSame('processing', $order->refresh()->status);
        $this->assertSame(1, $p->refresh()->size_stock['375 ml']); // vendido, não devolve
    }

    public function test_dry_run_lista_mas_nao_grava(): void
    {
        $p = $this->produto();
        $order = $this->pedido($p, 'cartao', 90);

        $this->artisan('pedidos:cancelar-cartao-preso --dry-run')
            ->expectsOutputToContain('Dry-run')
            ->assertSuccessful();

        $this->assertSame('pending', $order->refresh()->status);
        $this->assertSame(1, $p->refresh()->size_stock['375 ml']);
    }

    public function test_opcao_minutos_encurta_a_janela(): void
    {
        $p = $this->produto();
        $order = $this->pedido($p, 'cartao', 10);

        $this->artisan('pedidos:cancelar-cartao-preso --minutos=5')->assertSuccessful();

        $this->assertSame('cancelled', $order->refresh()->status);
        $this->assertSame(3, $p->refresh()->size_stock['375 ml']);
    }
}
