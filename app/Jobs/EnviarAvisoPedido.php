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
 * Aviso automático de pedido pago para a equipe (leva 16). Fila
 * 'notificacoes', processada pelo mesmo worker do scheduler da fila
 * microvix. Já avisado (notificado_em) = não envia de novo; webhook e
 * polling chegando juntos não enfileiram dois (ShouldBeUnique).
 */
class EnviarAvisoPedido implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** Lock de unicidade: se o worker estiver parado, solta depois de 1 h. */
    public int $uniqueFor = 3600;

    public function __construct(public int $orderId)
    {
        $this->onQueue('notificacoes');
    }

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    /** Retry: 1 min, 5 min. */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(AvisoPedido $aviso): void
    {
        $order = Order::find($this->orderId);

        if (! $order || $order->notificado_em) {
            return;
        }

        $aviso->enviar($order); // falha: grava o erro no pedido e relança (retry)
    }
}
