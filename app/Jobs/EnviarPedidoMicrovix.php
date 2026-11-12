<?php

namespace App\Jobs;

use App\Exceptions\MicrovixException;
use App\Exceptions\MicrovixNegocioException;
use App\Models\Order;
use App\Services\Microvix\MicrovixEnvio;
use App\Services\Microvix\MicrovixPedidoSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Fase 2 — envia à Linx um pedido PAGO (somente_reserva=0). Enfileirado só
 * por MicrovixEnvio::agendar (pagamento aprovado) e pelo reenvio do admin.
 * Fila database "microvix", worker pelo scheduler.
 *
 * Falha aqui NUNCA afeta o pedido do cliente: não cancela venda, não mexe
 * em estoque local, não muda pagamento — só as colunas microvix_envio_*.
 */
class EnviarPedidoMicrovix implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** 1 chamada + 3 retries (só erro temporário/rede); depois para em erro. */
    public int $tries = 4;

    /**
     * A Linx de produção leva 20–45 s por chamada e um envio faz até 5
     * (mais os retries da consulta, 5/15/30 s): 10 min por tentativa.
     * Tem que ficar abaixo do retry_after da conexão database
     * (config/queue.php), senão outro worker pega o mesmo job no meio.
     */
    public int $timeout = 600;

    public function __construct(public int $orderId)
    {
        $this->onQueue('microvix');
    }

    /** MICROVIX_ENVIA_PEDIDO: a chave de desligar tudo. */
    public static function integracaoLigada(): bool
    {
        return MicrovixEnvio::ligado();
    }

    /** Backoff do retry: 1 min, 5 min, 15 min. */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function middleware(): array
    {
        // Concorrência 1 na fila INTEIRA (chave compartilhada, não por
        // pedido): a guarda de segundo do sender (obs. 5 da doc) só é à
        // prova de corrida com um envio por vez.
        // expireAfter acima do $timeout: o lock não pode vencer com o envio rodando.
        return [(new WithoutOverlapping('microvix-pedido'))->releaseAfter(15)->expireAfter(900)];
    }

    public function handle(MicrovixPedidoSender $sender): void
    {
        $order = Order::with(['items', 'user'])->find($this->orderId);

        // Já enviado, fora do período, não pago ou cancelado: nada a fazer,
        // e nada é marcado (job atrasado/duplicado não regride estado).
        if ($order === null || ! MicrovixEnvio::elegivel($order)) {
            return;
        }

        $order->forceFill([
            'microvix_envio_estado'     => 'enviando',
            'microvix_envio_tentativas' => $order->microvix_envio_tentativas + 1,
            'microvix_envio_em'         => now(),
        ])->save();

        try {
            $estado = $sender->enviar($order);

            if ($estado === 'pendente') {
                // Trava desligada: o sender só simulou (XML em microvix_logs).
                $order->forceFill(['microvix_envio_estado' => 'pendente'])->save();
            }
        } catch (MicrovixNegocioException $e) {
            // Erro de negócio: retry não resolve — para em erro na PRIMEIRA
            // tentativa (resposta crua em microvix_logs) e espera o reenvio.
            $this->marcaErro($order, $e->getMessage());
            $this->fail($e);
        } catch (MicrovixException $e) {
            // Rede/HTTP/pool: anota e deixa o backoff retentar. Seguro mesmo
            // com timeout no cadastro: toda tentativa recomeça pelo
            // B2CConsultaPedidos e id_pedido/id_pedido_item são chave primária.
            $this->marcaErro($order, $e->getMessage(), definitivo: $this->attempts() >= $this->tries);
            throw $e;
        }
    }

    /** Últimas tentativas esgotadas (ou fail explícito): erro parado, sem loop eterno. */
    public function failed(Throwable $e): void
    {
        Order::whereKey($this->orderId)->whereNull('microvix_pedido_id')->update([
            'microvix_envio_estado' => 'erro',
            'microvix_envio_erro'   => mb_substr($e->getMessage(), 0, 2000),
            'microvix_envio_em'     => now(),
        ]);
    }

    protected function marcaErro(Order $order, string $mensagem, bool $definitivo = true): void
    {
        $order->forceFill([
            // Enquanto ainda há retry pela frente fica 'pendente' (com o
            // erro anotado pro painel); 'erro' é estado terminal.
            'microvix_envio_estado' => $definitivo ? 'erro' : 'pendente',
            'microvix_envio_erro'   => mb_substr($mensagem, 0, 2000),
        ])->save();
    }
}
