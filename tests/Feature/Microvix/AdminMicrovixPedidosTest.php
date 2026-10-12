<?php

namespace Tests\Feature\Microvix;

use App\Jobs\EnviarPedidoMicrovix;
use App\Models\MicrovixLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Painel de pedidos → ERP (fase 2, etapa 4): contadores, lista de erro com
 * reenvio, trilha de XML com selo simulado × real e aviso global no admin.
 */
class AdminMicrovixPedidosTest extends TestCase
{
    use MontaPedidoParaEnvio;
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Admin',
            'email'    => 'admin@aptk.test',
            'password' => bcrypt('secret123'),
            'role'     => 'admin',
        ]);

        $this->configuraMicrovixParaEnvio(enviaLigado: false); // default real do projeto
    }

    public function test_acesso_restrito_a_admin(): void
    {
        $order = $this->pedidoParaEnvio();

        $this->get("/admin/microvix/pedidos/{$order->id}")->assertRedirect('/login');

        $customer = User::create([
            'name'     => 'Cliente Comum',
            'email'    => 'comum@aptk.test',
            'password' => bcrypt('secret123'),
            'role'     => 'customer',
        ]);

        $this->actingAs($customer)->get("/admin/microvix/pedidos/{$order->id}")->assertForbidden();
        $this->actingAs($customer)->post("/admin/microvix/pedidos/{$order->id}/reenviar")->assertForbidden();
    }

    public function test_painel_mostra_contadores_e_lista_de_pedidos_em_erro(): void
    {
        $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'pendente']);
        $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'confirmado', 'microvix_synced_at' => now()]);
        $comErro = $this->pedidoParaEnvio(atributos: [
            'microvix_envio_estado'     => 'erro',
            'microvix_envio_erro'       => 'não existe plano de pagamento APPMAX para pix',
            'microvix_envio_tentativas' => 1,
            'microvix_envio_em'         => now(),
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/microvix')
            ->assertOk()
            ->assertSee('Pedidos → ERP')
            ->assertSee('Parados em erro')
            ->assertSee('Confirmados hoje')
            // trava desligada fica estampada, não escondida
            ->assertSee('trava desligada — tudo simulado')
            ->assertSee("#{$comErro->id}")
            ->assertSee('não existe plano de pagamento APPMAX para pix')
            ->assertSee('Reenviar');
    }

    public function test_aviso_global_de_erro_aparece_fora_da_tela_do_microvix(): void
    {
        $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'erro', 'microvix_envio_erro' => 'x']);
        $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'erro', 'microvix_envio_erro' => 'y']);

        // Leva 08: integração desligada = não há erro real de envio. Sem
        // banner, sem badge e sem o item Microvix no menu (a rota segue
        // aberta por URL direta).
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('parados em erro na integração Microvix')
            ->assertDontSee('<span class="nav-badge">', false)
            ->assertDontSee('Microvix (ERP)');
        $this->actingAs($this->admin)->get('/admin/microvix')->assertOk();

        config(['microvix.envia_pedido' => true]);

        // No dashboard geral do admin, não só no painel Microvix.
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('2 pedidos parados em erro na integração Microvix')
            ->assertSee('Resolver agora')
            ->assertSee('<span class="nav-badge">2</span>', false); // badge na sidebar

        // Sem erro, nada de alarme falso.
        \App\Models\Order::query()->update(['microvix_envio_estado' => 'confirmado']);
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('parados em erro na integração Microvix')
            ->assertDontSee('<span class="nav-badge">', false);
    }

    public function test_reenviar_zera_tentativas_e_despacha_a_acao_certa_pro_estado_do_pagamento(): void
    {
        Queue::fake();

        $aguardando = $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'erro', 'microvix_envio_tentativas' => 4, 'microvix_envio_erro' => 'x']);
        $pago       = $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'erro', 'payment_status' => 'paid', 'paid_at' => now()]);
        $cancelado  = $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'erro', 'status' => 'cancelled']);

        foreach ([$aguardando, $pago, $cancelado] as $order) {
            $this->actingAs($this->admin)
                ->post("/admin/microvix/pedidos/{$order->id}/reenviar")
                ->assertRedirect();
        }

        $aguardando->refresh();
        $this->assertSame('pendente', $aguardando->microvix_envio_estado);
        $this->assertSame(0, (int) $aguardando->microvix_envio_tentativas);
        $this->assertNull($aguardando->microvix_envio_erro);

        Queue::assertPushed(EnviarPedidoMicrovix::class, fn ($j) => $j->orderId === $aguardando->id && $j->acao === 'reservar');
        Queue::assertPushed(EnviarPedidoMicrovix::class, fn ($j) => $j->orderId === $pago->id && $j->acao === 'confirmar');
        Queue::assertPushed(EnviarPedidoMicrovix::class, fn ($j) => $j->orderId === $cancelado->id && $j->acao === 'cancelar');
    }

    public function test_reenvio_manual_respeita_a_trava_e_avisa_que_foi_simulado(): void
    {
        // Fila sync: o reenvio roda o job inline — com a trava desligada o
        // sender simula e NADA sai pro webservice.
        Http::fake();
        $order = $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'erro', 'microvix_envio_erro' => 'x']);

        $this->actingAs($this->admin)
            ->post("/admin/microvix/pedidos/{$order->id}/reenviar")
            ->assertRedirect()
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'SIMULADO'));

        Http::assertNothingSent();
        $this->assertSame(5, MicrovixLog::where('order_id', $order->id)->where('simulated', true)->count());
        $this->assertSame('pendente', $order->fresh()->microvix_envio_estado);
    }

    public function test_trilha_do_pedido_mostra_xml_com_selo_simulado_e_real(): void
    {
        $order = $this->pedidoParaEnvio();

        MicrovixLog::create([
            'order_id'  => $order->id,
            'direction' => 'in',
            'method'    => 'B2CCadastraPedido',
            'request'   => '<envelope-simulado com="somente_reserva"/>',
            'success'   => true,
            'simulated' => true,
        ]);
        MicrovixLog::create([
            'order_id'  => $order->id,
            'direction' => 'in',
            'method'    => 'B2CCadastraPedidoItens',
            'request'   => '<envelope-real/>',
            'response'  => '<resposta-do-erp/>',
            'success'   => true,
            'simulated' => false,
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/microvix/pedidos/{$order->id}")
            ->assertOk()
            ->assertSee('simulado — não enviado')
            ->assertSee('enviado ao ERP')
            ->assertSee('XML que TERIA sido enviado')
            ->assertSee('envelope-simulado', false)
            ->assertSee('resposta-do-erp', false)
            ->assertSee('Reenviar ao ERP');
    }
}
