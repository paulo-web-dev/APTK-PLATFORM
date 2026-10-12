<?php

namespace App\Jobs;

use App\Exceptions\MicrovixException;
use App\Exceptions\MicrovixNegocioException;
use App\Models\Order;
use App\Services\Microvix\MicrovixPedidoSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Fase 2 — envia um pedido ao Microvix (ação: reservar | confirmar |
 * cancelar). Fila database, worker pelo scheduler (queue:work
 * --stop-when-empty a cada minuto).
 *
 * Falha aqui NUNCA afeta o pedido do cliente: não cancela venda, não mexe
 * em estoque local, não muda pagamento — só as colunas microvix_envio_*.
 */
class EnviarPedidoMicrovix implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** 1 chamada + 3 retries (só erro de rede/HTTP); depois para em erro. */
    public int $tries = 4;

    public function __construct(public int $orderId, public string $acao)
    {
        $this->onQueue('microvix');
    }

    /**
     * Integração de pedido ligada (MICROVIX_ENVIA_PEDIDO)? Leva 08: com o
     * Microvix fora de operação, os disparos automáticos (checkout, webhook,
     * cancelamento) nem enfileiram — sem XML simulado, sem estado, sem erro
     * e sem banner por pedido real. A trava do sender segue valendo para o
     * reenvio manual do painel.
     */
    public static function integracaoLigada(): bool
    {
        return (bool) config('microvix.envia_pedido');
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
        // prova de corrida com um envio por vez — e o lock vale mesmo se
        // alguém subir um segundo queue:work na mão ou o painel reenviar.
        return [(new WithoutOverlapping('microvix-pedido'))->releaseAfter(15)->expireAfter(300)];
    }

    public function handle(MicrovixPedidoSender $sender): void
    {
        $order = Order::with(['items', 'user'])->find($this->orderId);

        if ($order === null || $this->jaResolvido($order)) {
            return;
        }

        if ($this->acao === 'confirmar' && $order->status === 'cancelled') {
            // Pagamento aprovado de pedido já cancelado no site: não
            // ressuscitar o pedido no ERP — tratar manualmente (reembolso?).
            // A mesma mensagem vai na exceção: failed() regrava o erro a
            // partir dela e não pode apagar o detalhe.
            $mensagem = "Pagamento aprovado com o pedido #{$order->id} já cancelado no site — não confirmado no ERP; tratar manualmente.";
            $this->marcaErro($order, $mensagem);
            $this->fail(new MicrovixNegocioException($mensagem));

            return;
        }

        $order->fill([
            'microvix_envio_estado'     => 'enviando',
            'microvix_envio_tentativas' => $order->microvix_envio_tentativas + 1,
            'microvix_envio_em'         => now(),
        ])->save();

        try {
            $estado = match ($this->acao) {
                'reservar'  => $sender->reservar($order),
                'confirmar' => $sender->confirmar($order),
                'cancelar'  => $sender->cancelar($order),
                default     => throw new MicrovixNegocioException("Ação de envio desconhecida: '{$this->acao}'."),
            };

            if ($estado === 'pendente') {
                // Trava desligada: o sender só simulou (XML em microvix_logs).
                $order->fill(['microvix_envio_estado' => 'pendente'])->save();
            }
        } catch (MicrovixNegocioException $e) {
            // Erro de negócio: retry não resolve — para em erro na PRIMEIRA
            // tentativa e espera intervenção (reenvio manual do painel).
            $this->marcaErro($order, $e->getMessage());
            $this->fail($e);
        } catch (MicrovixException $e) {
            // Rede/HTTP: anota e deixa o backoff retentar. Seguro mesmo com
            // timeout em B2CCadastraPedido: toda tentativa recomeça pelo
            // B2CConsultaPedidos no sender — requisição que chegou não é
            // repetida, reserva não duplica.
            $this->marcaErro($order, $e->getMessage(), definitivo: $this->attempts() >= $this->tries);
            throw $e;
        }
    }

    /** Job atrasado ou duplicado não regride estado (confirmação > reserva). */
    protected function jaResolvido(Order $order): bool
    {
        return match ($this->acao) {
            'reservar'  => in_array($order->microvix_envio_estado, ['reservado', 'confirmado', 'cancelado'], true),
            'confirmar' => $order->microvix_envio_estado === 'confirmado',
            'cancelar'  => $order->microvix_envio_estado === 'cancelado',
            default     => false,
        };
    }

    /** Últimas tentativas esgotadas (ou fail explícito): erro parado, sem loop eterno. */
    public function failed(Throwable $e): void
    {
        Order::whereKey($this->orderId)->update([
            'microvix_envio_estado' => 'erro',
            'microvix_envio_erro'   => mb_substr($e->getMessage(), 0, 2000),
            'microvix_envio_em'     => now(),
        ]);
    }

    protected function marcaErro(Order $order, string $mensagem, bool $definitivo = true): void
    {
        $order->fill([
            // Enquanto ainda há retry pela frente fica 'pendente' (com o
            // erro anotado pro painel); 'erro' é estado terminal.
            'microvix_envio_estado' => $definitivo ? 'erro' : 'pendente',
            'microvix_envio_erro'   => mb_substr($mensagem, 0, 2000),
        ])->save();
    }
}
