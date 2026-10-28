<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\AvisoPedido;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Segundo aviso (leva 17): "pagamento confirmado" de um pedido que já foi
 * avisado como aguardando. Fila 'notificacoes'.
 *
 * Não envia se: já avisado (pagamento_notificado_em), pedido não pago, ou o
 * aviso do pedido ainda não saiu — nesse caso, quando ele sair, já mostra
 * "Pago" e conta como confirmação (AvisoPedido::enviar).
 */
class EnviarAvisoPagamento implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(public int $orderId)
    {
        $this->onQueue('notificacoes');
    }

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(AvisoPedido $aviso): void
    {
        $order = Order::find($this->orderId);

        if (! $order || $order->pagamento_notificado_em || ! $order->isPaid() || ! $order->notificado_em) {
            return;
        }

        $aviso->enviarPagamento($order);
    }
}
