<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook da Appmax (Apphooks).
 *
 * Cadastrar no painel Appmax (Configurações → Apphooks → Novo Webhook):
 *   URL:      https://SEU-DOMINIO/webhooks/appmax/{APPMAX_WEBHOOK_TOKEN}
 *   Eventos:  OrderApproved, OrderPaid, OrderPaidByPix, OrderAuthorized,
 *             PaymentNotAuthorized, OrderRefund, PixExpired, BoletoExpired,
 *             ChargebackDispute
 *
 * A origem é validada pelo token secreto na URL (a Appmax v3 não assina o
 * corpo). Como defesa extra, só atualizamos pedidos que já têm o
 * appmax_order_id correspondente gravado localmente.
 */
class AppmaxWebhookController extends Controller
{
    public function __construct(protected OrderService $orders)
    {
    }

    public function handle(Request $request, string $token): JsonResponse
    {
        // 1) Valida o token secreto da URL.
        $expected = (string) config('appmax.webhook_token');
        if ($expected === '' || ! hash_equals($expected, $token)) {
            Log::warning('Appmax webhook: token inválido', ['ip' => $request->ip()]);

            return response()->json(['ok' => false], 403);
        }

        $event = (string) $request->input('event', '');
        $data  = (array) $request->input('data', []);

        // O id do pedido Appmax: o payload varia por evento (doc 1.2) —
        // procura nas posições conhecidas.
        $appmaxOrderId = (int) (
            $data['order']['id']
            ?? $data['order_id']
            ?? $data['id']
            ?? $request->input('order.id')
            ?? $request->input('order_id')
            ?? 0
        );

        Log::info('Appmax webhook recebido', ['event' => $event, 'appmax_order_id' => $appmaxOrderId]);

        if (! $event || ! $appmaxOrderId) {
            return response()->json(['ok' => true, 'ignored' => 'payload sem event/data.id']);
        }

        // 2) Localiza o pedido local vinculado.
        $order = Order::where('appmax_order_id', $appmaxOrderId)->first();

        if (! $order) {
            // Pedido de outra origem (ex.: criado direto no painel) — ignora.
            return response()->json(['ok' => true, 'ignored' => 'pedido não encontrado']);
        }

        // 3) Aplica o evento (idempotente: reprocessar não duplica efeito).
        // Eventos oficiais em snake_case (doc 1.2); nomes PascalCase antigos
        // mantidos por compatibilidade.
        match (true) {
            in_array($event, [
                'order_approved', 'order_paid', 'order_paid_by_pix',
                'order_authorized', 'order_authorized_with_delay', 'order_integrated',
                'OrderApproved', 'OrderPaid', 'OrderPaidByPix', 'OrderAuthorized', 'OrderIntegrated',
            ], true) => $this->markPaid($order),

            in_array($event, ['payment_not_authorized', 'PaymentNotAuthorized'], true)
                => $this->markFailed($order),

            in_array($event, ['order_pix_expired', 'order_billet_overdue', 'PixExpired', 'BoletoExpired'], true)
                => $this->markExpired($order),

            in_array($event, ['order_refund', 'order_chargeback_in_treatment', 'OrderRefund', 'ChargebackDispute'], true)
                => $this->markRefunded($order, $event),

            default => Log::info('Appmax webhook: evento sem tratamento', ['event' => $event]),
        };

        return response()->json(['ok' => true]);
    }

    protected function markPaid(Order $order): void
    {
        if ($order->isPaid()) {
            return;
        }

        $order->update([
            'payment_status' => 'paid',
            'status'         => $order->status === 'pending' ? 'processing' : $order->status,
            'paid_at'        => $order->paid_at ?? now(),
        ]);
    }

    protected function markFailed(Order $order): void
    {
        // Cartão recusado em análise posterior: cancela e devolve estoque.
        if (! $order->isPaid()) {
            $this->orders->cancelAndRestoreStock($order, 'failed');
        }
    }

    protected function markExpired(Order $order): void
    {
        // Pix/boleto venceu sem pagamento: libera o estoque reservado.
        if (! $order->isPaid()) {
            $this->orders->cancelAndRestoreStock($order, 'expired');
        }
    }

    protected function markRefunded(Order $order, string $event): void
    {
        $isChargeback = in_array($event, ['order_chargeback_in_treatment', 'ChargebackDispute'], true);

        $order->update([
            'payment_status' => $isChargeback ? 'chargeback' : 'refunded',
            'status'         => 'cancelled',
        ]);
    }
}
