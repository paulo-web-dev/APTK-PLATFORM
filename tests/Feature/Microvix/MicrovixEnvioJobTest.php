<?php

namespace Tests\Feature\Microvix;

use App\Exceptions\MicrovixException;
use App\Jobs\EnviarPedidoMicrovix;
use App\Models\Order;
use App\Services\AppmaxService;
use App\Services\Microvix\MicrovixEnvio;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Job de envio (fase 2) e quem o dispara: só pagamento aprovado. Pedido
 * pendente, expirado ou cancelado antes do pagamento nunca vai à Linx.
 * QUEUE_CONNECTION=sync nos testes: dispatch roda o job inline.
 */
class MicrovixEnvioJobTest extends TestCase
{
    use MontaPedidoParaEnvio;
    use RefreshDatabase;

    /** Situação que a consulta à Appmax devolve (o webhook confirma antes de agir). */
    protected string $situacaoAppmax = 'pago';

    protected function setUp(): void
    {
        parent::setUp();

        $this->partialMock(AppmaxService::class, fn ($m) => $m
            ->shouldReceive('consultarSituacao')
            ->andReturnUsing(fn () => $this->situacaoAppmax));
        $this->configuraMicrovixParaEnvio();
        config(['appmax.webhook_token' => 'tok-teste']);
    }

    protected function fakeFeliz(): void
    {
        Http::fake([
            '*api/integracao*' => Http::response($this->fixtureMicrovix('pedidos-vazio')),
            '*Importador.svc*' => Http::response($this->fixtureMicrovix('importa-sucesso')),
        ]);
    }

    protected function webhook(array $payload)
    {
        return $this->postJson('/webhooks/appmax/tok-teste', $payload);
    }

    protected function pendente(array $atributos = []): Order
    {
        return $this->pedidoParaEnvio('pix', $atributos + ['status' => 'pending', 'payment_status' => 'pending', 'paid_at' => null]);
    }

    /* -----------------------------------------------------------------
     | Job
     ------------------------------------------------------------------ */

    public function test_envio_com_sucesso_marca_enviado_tentativas_e_data(): void
    {
        $this->fakeFeliz();
        $order = $this->pedidoParaEnvio('pix');

        EnviarPedidoMicrovix::dispatch($order->id);

        $order->refresh();
        $this->assertSame('enviado', $order->microvix_envio_estado);
        $this->assertSame(1, (int) $order->microvix_envio_tentativas);
        $this->assertNotNull($order->microvix_envio_em);
        $this->assertSame($order->id, (int) $order->microvix_pedido_id);
    }

    public function test_erro_de_negocio_para_em_erro_na_primeira_sem_retry(): void
    {
        Http::fake([
            '*api/integracao*' => Http::response($this->fixtureMicrovix('pedidos-vazio')),
            '*Importador.svc*' => Http::response($this->fixtureMicrovix('importa-falha')),
        ]);
        $order = $this->pedidoParaEnvio('pix');

        EnviarPedidoMicrovix::dispatch($order->id);

        $order->refresh();
        $this->assertSame('erro', $order->microvix_envio_estado);
        $this->assertSame(1, (int) $order->microvix_envio_tentativas);
        $this->assertStringContainsString('Plano de pagamento informado nao existe', $order->microvix_envio_erro);
        $this->assertNull($order->microvix_pedido_id);
    }

    public function test_erro_de_pool_seguido_de_sucesso_envia_na_retentativa(): void
    {
        $pool = str_replace('Plano de pagamento informado nao existe no portal', 'Pool de conexoes esgotado', $this->fixtureMicrovix('importa-falha'));
        $respostas = [$pool, $this->fixtureMicrovix('importa-sucesso')];
        Http::fake([
            '*api/integracao*' => Http::response($this->fixtureMicrovix('pedidos-vazio')),
            '*Importador.svc*' => function () use (&$respostas) {
                // 1ª tentativa: o primeiro cadastro volta "pool"; depois, sucesso sempre.
                return Http::response(count($respostas) > 1 ? array_shift($respostas) : $respostas[0]);
            },
        ]);
        $order = $this->pedidoParaEnvio('pix');
        $job = new EnviarPedidoMicrovix($order->id);

        // handle() direto: o driver sync não tem retry; o worker real faz o backoff.
        try {
            app()->call([$job, 'handle']);
            $this->fail('Pool deveria relançar para o retry da fila.');
        } catch (MicrovixException $e) {
            $this->assertStringContainsString('erro temporário da Linx', $e->getMessage());
        }
        $this->assertSame('pendente', $order->fresh()->microvix_envio_estado);
        $this->assertSame([60, 300, 900], $job->backoff());

        // Retentativa (o worker chamaria de novo após o backoff).
        app()->call([new EnviarPedidoMicrovix($order->id), 'handle']);

        $order->refresh();
        $this->assertSame('enviado', $order->microvix_envio_estado);
        $this->assertSame(2, (int) $order->microvix_envio_tentativas);
        $this->assertNull($order->microvix_envio_erro);
    }

    public function test_erro_de_rede_esgotado_vira_erro_terminal(): void
    {
        Http::fake(['*api/integracao*' => fn () => throw new ConnectionException('timeout de rede')]);
        $order = $this->pedidoParaEnvio('pix');
        $job = new EnviarPedidoMicrovix($order->id);

        try {
            app()->call([$job, 'handle']);
        } catch (MicrovixException) {
        }
        $job->failed(new MicrovixException('Falha de rede ao consultar B2CConsultaPedidos: timeout de rede'));

        $this->assertSame('erro', $order->fresh()->microvix_envio_estado);
    }

    public function test_job_de_pedido_ja_enviado_nao_chama_nada_e_nao_mexe_no_estado(): void
    {
        Http::fake();
        $order = $this->pedidoParaEnvio('pix', ['microvix_pedido_id' => 777, 'microvix_envio_estado' => 'enviado', 'microvix_envio_tentativas' => 1]);

        EnviarPedidoMicrovix::dispatch($order->id);

        Http::assertNothingSent();
        $order->refresh();
        $this->assertSame('enviado', $order->microvix_envio_estado);
        $this->assertSame(1, (int) $order->microvix_envio_tentativas);
    }

    /* -----------------------------------------------------------------
     | Gatilhos: só pagamento aprovado
     ------------------------------------------------------------------ */

    public function test_checkout_nao_reserva_mais_nada_na_linx(): void
    {
        Queue::fake();
        $order = $this->pendente();

        // placeFromCart não enfileira; cancelamento antes do pagamento também não.
        app(OrderService::class)->cancelAndRestoreStock($order, 'expired');

        Queue::assertNotPushed(EnviarPedidoMicrovix::class);
    }

    public function test_pagamento_aprovado_pelo_webhook_enfileira_um_envio_com_after_commit(): void
    {
        Queue::fake();
        $order = $this->pendente(['appmax_order_id' => 555]);

        $this->webhook(['event' => 'order_approved', 'data' => ['id' => 555]])->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
        Queue::assertPushed(EnviarPedidoMicrovix::class, fn ($job) => $job->orderId === $order->id
            && $job->afterCommit === true
            && $job->queue === 'microvix');

        // Reentrega do mesmo evento: idempotente, não re-enfileira.
        $this->webhook(['event' => 'order_approved', 'data' => ['id' => 555]])->assertOk();
        Queue::assertPushed(EnviarPedidoMicrovix::class, 1);
    }

    public function test_webhook_responde_200_mesmo_quando_a_linx_falha(): void
    {
        Http::fake(['*api/integracao*' => fn () => throw new ConnectionException('erp fora do ar')]);
        $order = $this->pendente(['appmax_order_id' => 556]);

        $this->webhook(['event' => 'order_approved', 'data' => ['id' => 556]])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_pix_expirado_cancela_local_e_nada_vai_para_a_linx(): void
    {
        Http::fake();
        $order = $this->pendente(['appmax_order_id' => 557]);

        $this->situacaoAppmax = 'recusado';
        $this->webhook(['event' => 'order_pix_expired', 'data' => ['id' => 557]])->assertOk();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertNull($order->microvix_envio_estado);
        Http::assertNothingSent();
    }

    public function test_pedido_anterior_ao_corte_pago_agora_nao_e_enviado(): void
    {
        Queue::fake();
        $order = $this->pendente(['appmax_order_id' => 558, 'created_at' => now()->subDays(5)]);

        $this->webhook(['event' => 'order_approved', 'data' => ['id' => 558]])->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
        Queue::assertNotPushed(EnviarPedidoMicrovix::class);

        // Mesmo despachado à mão, o job não envia nem marca nada.
        Http::fake();
        app()->call([new EnviarPedidoMicrovix($order->id), 'handle']);
        Http::assertNothingSent();
        $this->assertNull($order->fresh()->microvix_envio_estado);
    }

    public function test_sem_data_de_corte_nada_e_enfileirado(): void
    {
        Queue::fake();
        config(['microvix.envio_a_partir_de' => '']);

        MicrovixEnvio::agendar($this->pedidoParaEnvio('pix'));

        Queue::assertNotPushed(EnviarPedidoMicrovix::class);
    }

    public function test_trava_desligada_nada_e_enfileirado(): void
    {
        Queue::fake();
        config(['microvix.envia_pedido' => false]);

        MicrovixEnvio::agendar($this->pedidoParaEnvio('pix'));

        Queue::assertNotPushed(EnviarPedidoMicrovix::class);
    }
}
