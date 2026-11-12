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
        $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'enviado', 'microvix_pedido_id' => 1, 'microvix_synced_at' => now()]);
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
            ->assertSee('Enviados hoje')
            ->assertSee('Manuais (lançar na Linx)')
            // trava desligada fica estampada, não escondida
            ->assertSee('trava desligada — tudo simulado')
            ->assertSee("#{$comErro->id}")
            ->assertSee('não existe plano de pagamento APPMAX para pix')
            ->assertSee('Reenviar para a Linx');
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
        \App\Models\Order::query()->update(['microvix_envio_estado' => 'enviado']);
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('parados em erro na integração Microvix')
            ->assertDontSee('<span class="nav-badge">', false);
    }

    public function test_reenviar_so_para_erro_ou_manual_pago_do_periodo_e_nunca_enviado(): void
    {
        Queue::fake();

        $erro   = $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'erro', 'microvix_envio_tentativas' => 4, 'microvix_envio_erro' => 'x']);
        $manual = $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'manual', 'microvix_envio_erro' => 'Lançar manualmente na Linx: y']);
        $recusados = [
            'já na Linx'         => $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'erro', 'microvix_pedido_id' => 999]),
            'anterior ao corte'  => $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'erro', 'created_at' => now()->subDays(5)]),
            'não pago'           => $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'erro', 'payment_status' => 'pending', 'status' => 'pending', 'paid_at' => null]),
            'já enviado (estado)' => $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'enviado', 'microvix_pedido_id' => 998]),
        ];

        foreach ([$erro, $manual] as $order) {
            $this->actingAs($this->admin)->post("/admin/microvix/pedidos/{$order->id}/reenviar")->assertSessionHas('status');
        }
        foreach ($recusados as $caso => $order) {
            $this->actingAs($this->admin)->post("/admin/microvix/pedidos/{$order->id}/reenviar")->assertSessionHasErrors('reenviar');
        }

        $erro->refresh();
        $this->assertSame('pendente', $erro->microvix_envio_estado);
        $this->assertSame(0, (int) $erro->microvix_envio_tentativas);
        $this->assertNull($erro->microvix_envio_erro);

        Queue::assertPushed(EnviarPedidoMicrovix::class, 2);
        Queue::assertPushed(EnviarPedidoMicrovix::class, fn ($j) => $j->orderId === $erro->id);
        Queue::assertPushed(EnviarPedidoMicrovix::class, fn ($j) => $j->orderId === $manual->id);
        $this->assertSame(999, (int) $recusados['já na Linx']->fresh()->microvix_pedido_id);
    }

    public function test_marcar_como_lancado_na_linx(): void
    {
        $manual = $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'manual', 'microvix_envio_erro' => 'Lançar manualmente na Linx: custom']);
        $enviado = $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'enviado', 'microvix_pedido_id' => 5]);

        $this->actingAs($this->admin)->post("/admin/microvix/pedidos/{$manual->id}/lancado")->assertSessionHas('status');
        $this->actingAs($this->admin)->post("/admin/microvix/pedidos/{$enviado->id}/lancado")->assertSessionHasErrors('reenviar');

        $this->assertSame('lancado_manual', $manual->fresh()->microvix_envio_estado);
        $this->assertSame('enviado', $enviado->fresh()->microvix_envio_estado);

        // Lançado à mão não volta pra fila.
        $this->actingAs($this->admin)->post("/admin/microvix/pedidos/{$manual->id}/reenviar")->assertSessionHasErrors('reenviar');
    }

    public function test_detalhe_do_pedido_mostra_status_na_linx_numero_motivo_e_botoes(): void
    {
        config(['microvix.envia_pedido' => true]);

        $manual = $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'manual', 'microvix_envio_erro' => 'Lançar manualmente na Linx: garrafa personalizada (Negroni 375 ml)']);
        $this->actingAs($this->admin)->get("/admin/pedidos/{$manual->id}")
            ->assertOk()
            ->assertSee('Linx Microvix')
            ->assertSee('Manual')
            ->assertSee('garrafa personalizada (Negroni 375 ml)')
            ->assertSee('Reenviar para a Linx')
            ->assertSee('Marcar como lançado na Linx');

        $enviado = $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'enviado', 'microvix_pedido_id' => 4321]);
        $this->actingAs($this->admin)->get("/admin/pedidos/{$enviado->id}")
            ->assertOk()
            ->assertSee('Enviado')
            ->assertSee('4321')
            ->assertDontSee('Reenviar para a Linx');

        $antigo = $this->pedidoParaEnvio(atributos: ['created_at' => now()->subDays(5)]);
        $this->actingAs($this->admin)->get("/admin/pedidos/{$antigo->id}")
            ->assertOk()
            ->assertSee('Não aplicável (anterior à integração)')
            ->assertDontSee('Reenviar para a Linx');

        // Integração desligada: o card some.
        config(['microvix.envia_pedido' => false]);
        $this->actingAs($this->admin)->get("/admin/pedidos/{$manual->id}")->assertOk()->assertDontSee('Linx Microvix');
    }

    public function test_lista_de_pedidos_filtra_pela_situacao_na_linx(): void
    {
        config(['microvix.envia_pedido' => true]);

        $enviado = $this->pedidoParaEnvio(atributos: ['ship_name' => 'Cliente Enviado', 'microvix_envio_estado' => 'enviado', 'microvix_pedido_id' => 11]);
        $manual  = $this->pedidoParaEnvio(atributos: ['ship_name' => 'Cliente Manual', 'microvix_envio_estado' => 'manual']);
        $erro    = $this->pedidoParaEnvio(atributos: ['ship_name' => 'Cliente Erro', 'microvix_envio_estado' => 'erro']);
        $antigo  = $this->pedidoParaEnvio(atributos: ['ship_name' => 'Cliente Antigo', 'created_at' => now()->subDays(5)]);

        $num = fn ($o) => 'APTK-'.str_pad((string) $o->id, 5, '0', STR_PAD_LEFT);

        $this->actingAs($this->admin)->get('/admin/pedidos?microvix=manual')
            ->assertOk()->assertSee($num($manual))->assertDontSee($num($enviado))->assertDontSee($num($erro));
        $this->actingAs($this->admin)->get('/admin/pedidos?microvix=enviado')
            ->assertOk()->assertSee($num($enviado))->assertDontSee($num($manual));
        $this->actingAs($this->admin)->get('/admin/pedidos?microvix=erro')
            ->assertOk()->assertSee($num($erro))->assertDontSee($num($manual));
        $this->actingAs($this->admin)->get('/admin/pedidos?microvix=nao_aplicavel')
            ->assertOk()->assertSee($num($antigo))->assertDontSee($num($erro));
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
        $order = $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'erro', 'microvix_envio_erro' => 'x']);

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
            ->assertSee('Reenviar para a Linx');
    }
}
