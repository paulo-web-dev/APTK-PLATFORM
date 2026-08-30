<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Janela residual do checkout (ESTADO-ATUAL §3): placeFromCart commita o
 * pedido e debita estoque ANTES da chamada à Appmax. Se o processo morrer
 * entre o commit e a compensação (kill, OOM, deploy no meio), sobra um
 * pedido pending de CARTÃO com estoque preso — e, com o ERP mandando no
 * estoque, produto sumindo da vitrine sem venda.
 *
 * Pix e boleto ficam de fora: esses se resolvem sozinhos pelo webhook de
 * expiração da Appmax. Cartão é síncrono — pending velho é processo morto.
 *
 * A janela precisa ser folgada: se o cartão chegou a ser COBRADO antes do
 * processo morrer, a Appmax dispara order_approved em minutos e o webhook
 * marca o pedido como pago (markPaid). Um pending de cartão que passou
 * 60 min sem webhook não foi cobrado.
 */
class CancelarCartaoPreso extends Command
{
    protected $signature = 'pedidos:cancelar-cartao-preso
        {--minutos=60 : Idade mínima do pedido pendente para cancelar}
        {--dry-run : Mostra o que seria cancelado, sem cancelar}';

    protected $description = 'Cancela pedidos pending de cartão parados (processo de pagamento morto) e devolve o estoque';

    public function handle(OrderService $orders): int
    {
        $minutos = max(1, (int) $this->option('minutos'));
        $dryRun = (bool) $this->option('dry-run');

        $presos = Order::where('status', 'pending')
            ->where('payment_status', 'pending')
            ->where('payment_method', 'cartao')
            ->where('created_at', '<=', now()->subMinutes($minutos))
            ->orderBy('id')
            ->get();

        if ($presos->isEmpty()) {
            $this->info("Nenhum pedido pending de cartão com mais de {$minutos} min.");

            return self::SUCCESS;
        }

        $this->table(
            ['Pedido', 'Cliente', 'Total', 'Criado em'],
            $presos->map(fn (Order $o) => [
                "#{$o->id}",
                $o->ship_name ?? $o->user?->email ?? '—',
                'R$ '.number_format((float) $o->total, 2, ',', '.'),
                $o->created_at->format('d/m/Y H:i'),
            ])->all(),
        );

        if ($dryRun) {
            $this->info("Dry-run: {$presos->count()} pedido(s) seriam cancelado(s). Nada foi gravado.");

            return self::SUCCESS;
        }

        foreach ($presos as $order) {
            // Idempotente e já devolve size_stock + stock_qty.
            $orders->cancelAndRestoreStock($order, 'failed');

            // Trilha no próprio pedido — o admin precisa saber que foi
            // automático, não um cancelamento de gestão.
            $order->update([
                'notes' => trim(($order->notes ?? '')
                    ."\nCancelado automaticamente em ".now()->format('d/m/Y H:i')
                    .": pagamento de cartão interrompido (pendente há mais de {$minutos} min), estoque devolvido."),
            ]);

            Log::info('pedidos:cancelar-cartao-preso — pedido cancelado, estoque devolvido', [
                'order_id' => $order->id,
                'total'    => $order->total,
                'criado'   => $order->created_at->toDateTimeString(),
            ]);
        }

        $this->info("{$presos->count()} pedido(s) cancelado(s), estoque devolvido.");

        return self::SUCCESS;
    }
}
