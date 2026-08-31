<?php

namespace Tests\Feature\Microvix;

use App\Exceptions\MicrovixException;
use App\Jobs\EnviarPedidoMicrovix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Job de envio (fase 2) + gatilhos do webhook Appmax e do cancelamento.
 * QUEUE_CONNECTION=sync nos testes: dispatch roda o job inline — o que
 * também prova que falha do job nunca derruba quem despachou.
 */
class MicrovixEnvioJobTest extends TestCase
{
    use MontaPedidoParaEnvio;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuraMicrovixParaEnvio();
        config(['appmax.webhook_token' => 'tok-teste']);
    }

    /* -----------------------------------------------------------------
     | Job: estados, tentativas, retry
     ------------------------------------------------------------------ */

    public function test_reserva_com_sucesso_marca_estado_tentativas_e_data(): void
    {
        Http::fake([
            '*api/integracao*' => Http::response($this->fixtureMicrovix('pedidos-vazio')),
            '*Importador.svc*' => Http::response($this->fixtureMicrovix('importa-sucesso')),
        ]);

        $order = $this->pedidoParaEnvio();
        EnviarPedidoMicrovix::dispatch($order->id, 'reservar');

        $order->refresh();
        $this->assertSame('reservado', $order->microvix_envio_estado);
        $this->assertSame(1, (int) $order->microvix_envio_tentativas);
        $this->assertNotNull($order->microvix_envio_em);
        $this->assertNull($order->microvix_envio_erro);
    }

    public function test_erro_de_negocio_para_em_erro_na_primeira_sem_derrubar_o_chamador(): void
    {
        Http::fake();
        $order = $this->pedidoParaEnvio(metodo: 'pix'); // plano null no mapa

        // Sync: se o job relançasse, estouraria aqui — não pode.
        EnviarPedidoMicrovix::dispatch($order->id, 'reservar');

        Http::assertNothingSent();
        $order->refresh();
        $this->assertSame('erro', $order->microvix_envio_estado);
        $this->assertSame(1, (int) $order->microvix_envio_tentativas);
        $this->assertStringContainsString('plano de pagamento APPMAX', $order->microvix_envio_erro);
    }

    public function test_erro_de_rede_anota_e_relanca_para_o_retry_com_backoff(): void
    {
        Http::fake([
            '*api/integracao*' => fn () => throw new ConnectionException('timeout de rede'),
        ]);

        $order = $this->pedidoParaEnvio();
        $job   = new EnviarPedidoMicrovix($order->id, 'reservar');

        // handle() direto (sem worker): o driver sync não tem retry — o
        // estado intermediário "pendente com erro anotado" só existe entre
        // tentativas do worker de verdade (fila database + backoff).
        try {
            app()->call([$job, 'handle']);
            $this->fail('Erro de rede deveria relançar para o retry da fila.');
        } catch (MicrovixException $e) {
            $this->assertStringContainsString('Falha de rede', $e->getMessage());
        }

        $order->refresh();
        $this->assertSame('pendente', $order->microvix_envio_estado);
        $this->assertStringContainsString('Falha de rede', $order->microvix_envio_erro);
        $this->assertSame(1, (int) $order->microvix_envio_tentativas);

        // Esgotadas as tentativas, o worker chama failed(): erro terminal.
        $job->failed(new MicrovixException('Falha de rede ao consultar B2CConsultaPedidos: timeout de rede'));
        $order->refresh();
        $this->assertSame('erro', $order->microvix_envio_estado);
    }

    public function test_job_atrasado_nao_regride_estado_ja_confirmado(): void
    {
        Http::fake();
        $order = $this->pedidoParaEnvio(atributos: ['microvix_envio_estado' => 'confirmado']);

        EnviarPedidoMicrovix::dispatch($order->id, 'reservar');

        Http::assertNothingSent();
        $order->refresh();
        $this->assertSame('confirmado', $order->microvix_envio_estado);
        $this->assertSame(0, (int) $order->microvix_envio_tentativas);
    }

    public function test_confirmacao_de_pedido_cancelado_no_site_nao_ressuscita_no_erp(): void
    {
        Http::fake();
        $order = $this->pedidoParaEnvio(atributos: ['status' => 'cancelled']);

        EnviarPedidoMicrovix::dispatch($order->id, 'confirmar');

        Http::assertNothingSent();
        $order->refresh();
        $this->assertSame('erro', $order->microvix_envio_estado);
        $this->assertStringContainsString('cancelado no site', $order->microvix_envio_erro);
    }

    /* -----------------------------------------------------------------
     | Webhook Appmax: gatilhos
     ------------------------------------------------------------------ */

    protected function webhook(array $payload)
    {
        return $this->postJson('/webhooks/appmax/tok-teste', $payload);
    }

    public function test_pagamento_aprovado_despacha_confirmacao_depois_do_mark_paid(): void
    {
        Queue::fake();
        $order = $this->pedidoParaEnvio(atributos: ['appmax_order_id' => 555]);

        $this->webhook(['event' => 'order_approved', 'data' => ['id' => 555]])
            ->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status); // markPaid rodou primeiro
        Queue::assertPushed(
            EnviarPedidoMicrovix::class,
            fn ($job) => $job->orderId === $order->id && $job->acao === 'confirmar'
        );

        // Reentrega do mesmo evento: idempotente, não re-enfileira.
        $this->webhook(['event' => 'order_approved', 'data' => ['id' => 555]])->assertOk();
        Queue::assertPushed(EnviarPedidoMicrovix::class, 1);
    }

    public function test_webhook_responde_200_mesmo_quando_o_envio_ao_erp_falha(): void
    {
        // Fila sync: a falha do job estoura DENTRO do dispatch do webhook —
        // o try/catch do controller precisa segurar e responder 200 assim mesmo.
        Http::fake([
            '*api/integracao*' => fn () => throw new ConnectionException('erp fora do ar'),
        ]);

        $order = $this->pedidoParaEnvio(atributos: ['appmax_order_id' => 556]);

        $this->webhook(['event' => 'order_approved', 'data' => ['id' => 556]])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $order->refresh();
        $this->assertSame('paid', $order->payment_status); // pagamento intocado pela falha do ERP
    }

    public function test_pix_expirado_cancela_local_e_libera_a_reserva_no_erp(): void
    {
        $order = $this->pedidoParaEnvio(metodo: 'pix', atributos: ['appmax_order_id' => 557]);

        Http::fake([
            '*api/integracao*' => Http::response($this->fixturePedidoExisteNoErp($order)),
            '*Importador.svc*' => Http::response($this->fixtureMicrovix('importa-sucesso')),
        ]);

        $this->webhook(['event' => 'order_pix_expired', 'data' => ['id' => 557]])->assertOk();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('cancelado', $order->microvix_envio_estado);
        $this->assertFalse($order->microvix_reserved);

        $corpos = $this->corposEnviados();
        $this->assertStringContainsString('B2CCancelaPedido', end($corpos));
    }
}
