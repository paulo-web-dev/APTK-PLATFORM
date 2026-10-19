<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Status em português com mapa central (Order::STATUS_LABELS): o mesmo texto
 * nos badges e filtros do admin e na área do cliente.
 */
class StatusPedidoTest extends TestCase
{
    use RefreshDatabase;

    protected User $cliente;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cliente = User::create(['name' => 'Cliente', 'email' => 'c@aptk.test', 'password' => bcrypt('x12345678'), 'role' => 'customer']);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@aptk.test', 'password' => bcrypt('x12345678'), 'role' => 'admin']);
    }

    protected function pedido(string $status, string $pagamento = 'pending'): Order
    {
        return Order::create([
            'user_id' => $this->cliente->id, 'status' => $status, 'payment_status' => $pagamento,
            'payment_method' => 'pix', 'subtotal' => 10, 'total' => 10,
        ]);
    }

    public function test_mapa_central(): void
    {
        $esperado = [
            ['pending', 'pending', 'Pendente'],
            ['processing', 'paid', 'Pago'],
            ['paid', 'paid', 'Pago'],            // legado do admin antigo
            ['shipped', 'paid', 'Enviado'],
            ['delivered', 'paid', 'Entregue'],
            ['cancelled', 'failed', 'Cancelado'],
            ['cancelled', 'expired', 'Cancelado'],
            ['cancelled', 'refunded', 'Estornado'],
            ['cancelled', 'chargeback', 'Estornado'],
        ];

        foreach ($esperado as [$status, $pagamento, $rotulo]) {
            $this->assertSame($rotulo, (new Order(['status' => $status, 'payment_status' => $pagamento]))->statusLabel(), "{$status}/{$pagamento}");
        }
    }

    public function test_admin_mostra_rotulos_e_filtra_pelo_mesmo_criterio(): void
    {
        $pago = $this->pedido('processing', 'paid');
        $legado = $this->pedido('paid', 'paid');
        $estornado = $this->pedido('cancelled', 'refunded');
        $cancelado = $this->pedido('cancelled', 'failed');
        $num = fn (Order $o) => 'APTK-'.str_pad((string) $o->id, 5, '0', STR_PAD_LEFT);

        $this->actingAs($this->admin)->get(route('admin.orders.index'))
            ->assertOk()
            ->assertDontSee('Processing')
            ->assertSeeInOrder(['Pendente', 'Pago', 'Enviado', 'Entregue', 'Cancelado', 'Estornado']); // filtros

        $this->actingAs($this->admin)->get(route('admin.orders.index', ['status' => 'processing']))
            ->assertSee($num($pago))->assertSee($num($legado))
            ->assertDontSee($num($estornado))->assertDontSee($num($cancelado));

        $this->actingAs($this->admin)->get(route('admin.orders.index', ['status' => 'refunded']))
            ->assertSee($num($estornado))->assertDontSee($num($cancelado));

        $this->actingAs($this->admin)->get(route('admin.orders.index', ['status' => 'cancelled']))
            ->assertSee($num($cancelado))->assertDontSee($num($estornado));
    }

    public function test_admin_grava_pago_como_processing(): void
    {
        $order = $this->pedido('pending');

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.update-status', $order), ['status' => 'processing'])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('processing', $order->status);
        $this->assertSame('paid', $order->payment_status);

        // O 'paid' legado não é mais gravado pelo admin.
        $this->actingAs($this->admin)
            ->patch(route('admin.orders.update-status', $order), ['status' => 'paid'])
            ->assertSessionHasErrors('status');
    }

    public function test_area_do_cliente_usa_o_mesmo_texto(): void
    {
        $pago = $this->pedido('processing', 'paid');
        $estornado = $this->pedido('cancelled', 'refunded');

        $this->actingAs($this->cliente)->get(route('orders.index'))
            ->assertOk()
            ->assertSee('Pago')
            ->assertSee('Estornado')
            ->assertDontSee('Processing');

        $this->actingAs($this->cliente)->get(route('orders.show', $estornado))->assertSee('Estornado');
    }
}
