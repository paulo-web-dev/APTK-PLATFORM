<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Banco e app em UTC; exibição em horário de Brasília via data_br().
 */
class DataBrTest extends TestCase
{
    use RefreshDatabase;

    public function test_converte_utc_para_brasilia_no_formato_da_loja(): void
    {
        $this->assertSame('UTC', config('app.timezone'));

        $this->assertSame('18/10/2026 12:30', data_br(Carbon::parse('2026-10-18 15:30:00', 'UTC')));
        $this->assertSame('17/10/2026 21:00', data_br('2026-10-18 00:00:00')); // string do banco = UTC
        $this->assertSame('17/10/2026', data_br('2026-10-18 02:59:59', false)); // vira o dia
        $this->assertSame('18/10/2026 12:30:05', data_br('2026-10-18 15:30:05', 'd/m/Y H:i:s'));
        $this->assertSame('—', data_br(null));
    }

    public function test_data_ja_em_brasilia_nao_desloca(): void
    {
        $this->assertSame('18/10/2026 15:00', data_br(Carbon::parse('2026-10-18 15:00:00', 'America/Sao_Paulo')));
    }

    public function test_meus_pedidos_mostra_o_horario_de_brasilia(): void
    {
        $user = User::create(['name' => 'C', 'email' => 'c@aptk.test', 'password' => bcrypt('x12345678'), 'role' => 'customer']);
        $order = Order::create([
            'user_id' => $user->id, 'status' => 'pending', 'payment_status' => 'pending',
            'payment_method' => 'pix', 'subtotal' => 10, 'total' => 10,
        ]);
        $order->forceFill(['created_at' => Carbon::parse('2026-10-18 01:15:00', 'UTC')])->save();

        $this->actingAs($user)->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('17/10/2026 22:15');
    }
}
