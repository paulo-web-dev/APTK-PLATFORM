<?php

namespace App\Http\Controllers\Webhooks;

use App\Exceptions\AppmaxException;
use App\Http\Controllers\Controller;
use App\Jobs\EnviarPedidoMicrovix;
use App\Models\Order;
use App\Services\AppmaxService;
use App\Services\AvisoPedido;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Webhook da Appmax.
 *
 * Formato principal: WEBHOOKS DO PAINEL (loja própria, painel → API →
 * Webhooks; doc guides/webhooks-painel):
 *   {"environment": "production", "event": "OrderPaid", "data": {"id": 99042, ...}}
 *   event em PascalCase; data.id = id do pedido na Appmax; User-Agent: apphook.
 * O formato da Appstore (event em snake_case, ex. order_paid) segue aceito.
 *
 * URL: https://SEU-DOMINIO/webhooks/appmax/{APPMAX_WEBHOOK_TOKEN}
 *
 * Segurança: as entregas NÃO são assinadas. Defesas, como a doc manda:
 *   1. token secreto no caminho da URL;
 *   2. só mexe em pedido que já tem o appmax_order_id gravado aqui;
 *   3. antes de mudar status, CONFIRMA na API (GET /v1/orders/{id}): o
 *      evento é só o gatilho, quem decide é o status consultado. Evento
 *      forjado, atrasado ou fora de ordem não muda nada.
 *
 * Resposta: 2xx = entregue. Se a consulta à Appmax falhar, devolve 503 e
 * a Appmax retenta (até 4 entregas: +10s, +100s, +~17min).
 */
class AppmaxWebhookController extends Controller
{
    /** Gatilho → ação. Painel (PascalCase) e Appstore (snake_case). */
    protected const ACOES = [
        // Pagamento: confirma "pago" na API antes de aprovar. Autorizado
        // (antifraude) também consulta — só aprova quando a API disser pago.
        'OrderApproved'            => 'pagamento',
        'OrderPaid'                => 'pagamento',
        'OrderPaidByPix'           => 'pagamento',
        'OrderIntegrated'          => 'pagamento',
        'OrderPendingIntegration'  => 'pagamento',
        'OrderAuthorized'          => 'pagamento',
        'OrderAuthorizedWithDelay' => 'pagamento',
        'order_approved'              => 'pagamento',
        'order_paid'                  => 'pagamento',
        'order_paid_by_pix'           => 'pagamento',
        'order_integrated'            => 'pagamento',
        'order_authorized'            => 'pagamento',
        'order_authorized_with_delay' => 'pagamento',

        // Recusa / expiração: cancela e devolve estoque (se não pago).
        'PaymentNotAuthorized'          => 'recusa',
        'PaymentNotAuthorizedWithDelay' => 'recusa',
        'OrderRefusedByRisk'            => 'recusa',
        'payment_not_authorized'        => 'recusa',
        'OrderPixExpired'               => 'expiracao',
        'OrderBilletOverdue'            => 'expiracao',
        'order_pix_expired'             => 'expiracao',
        'order_billet_overdue'          => 'expiracao',

        // Estorno total e chargeback.
        'OrderRefund'                   => 'estorno',
        'order_refund'                  => 'estorno',
        'OrderChargeBackInTreatment'    => 'chargeback',
        'order_chargeback_in_treatment' => 'chargeback',

        // Só registro (incidente no pedido, sem mudar status).
        'OrderPartialRefund'  => 'estorno_parcial',
        'OrderChargeBackGain' => 'chargeback_ganho',

        // Nomes antigos que este controller aceitava (compatibilidade).
        'PixExpired'        => 'expiracao',
        'BoletoExpired'     => 'expiracao',
        'ChargebackDispute' => 'chargeback',
    ];

    public function __construct(
        protected OrderService $orders,
        protected AppmaxService $appmax,
    ) {
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

        // O id do pedido Appmax: data.id no painel; as outras posições
        // cobrem as variações da Appstore.
        $appmaxOrderId = (int) (
            $data['id']
            ?? $data['order']['id']
            ?? $data['order_id']
            ?? $request->input('order.id')
            ?? $request->input('order_id')
            ?? 0
        );

        Log::info('Appmax webhook recebido', [
            'event'           => $event,
            'appmax_order_id' => $appmaxOrderId,
            'user_agent'      => $request->userAgent(),
        ]);

        if (! $event || ! $appmaxOrderId) {
            return response()->json(['ok' => true, 'ignored' => 'payload sem event/data.id']);
        }

        $acao = self::ACOES[$event] ?? null;
        if (! $acao) {
            Log::info('Appmax webhook: evento sem tratamento', ['event' => $event]);

            return response()->json(['ok' => true, 'ignored' => 'evento sem tratamento']);
        }

        // 2) Localiza o pedido local vinculado.
        $order = Order::where('appmax_order_id', $appmaxOrderId)->first();

        if (! $order) {
            // Pedido de outra origem (ex.: criado direto no painel) — ignora.
            return response()->json(['ok' => true, 'ignored' => 'pedido não encontrado']);
        }

        // 3) Eventos só de registro não precisam de confirmação.
        if (in_array($acao, ['estorno_parcial', 'chargeback_ganho'], true)) {
            $this->registrar($order, $acao, $data);

            return response()->json(['ok' => true]);
        }

        // 4) Confirma na API antes de mudar status (entregas não são assinadas).
        try {
            $situacao = $this->appmax->consultarSituacao($order);
        } catch (AppmaxException $e) {
            Log::warning('Appmax webhook: confirmação na API falhou, pedindo retentativa', [
                'event'    => $event,
                'order_id' => $order->id,
                'detalhe'  => $e->detalhe,
            ]);

            return response()->json(['ok' => false, 'retry' => true], 503);
        }

        // 5) Aplica (idempotente: reprocessar não duplica efeito).
        $aplicado = match ($acao) {
            'pagamento'  => $situacao === 'pago' && $this->markPaid($order),
            'recusa'     => in_array($situacao, ['recusado', 'pendente'], true) && $this->markFailed($order, $situacao),
            'expiracao'  => in_array($situacao, ['recusado', 'pendente'], true) && $this->markExpired($order),
            'estorno'    => $situacao === 'estornado' && $this->markRefunded($order, false),
            'chargeback' => $situacao === 'estornado' && $this->markRefunded($order, true),
        };

        if (! $aplicado) {
            Log::info('Appmax webhook: evento não confirmado pela API, nada mudou', [
                'event'    => $event,
                'order_id' => $order->id,
                'situacao' => $situacao,
            ]);
        }

        return response()->json(['ok' => true, 'situacao' => $situacao]);
    }

    protected function markPaid(Order $order): bool
    {
        if ($order->isPaid()) {
            return true; // reentrega do evento: nada a fazer, nada a re-enfileirar
        }

        if ($order->status === 'cancelled') {
            // Estorno/chargeback já tratados: aprovação atrasada não ressuscita.
            if (in_array($order->payment_status, ['refunded', 'chargeback'], true)) {
                Log::warning('Appmax webhook: aprovação de pedido estornado ignorada', ['order_id' => $order->id]);

                return false;
            }

            // Cancelado aqui, pago lá (timeout na cobrança, cartão preso
            // cancelado antes do webhook, Pix pago no limite): o dinheiro
            // entrou, então o pedido volta a valer e o estoque é retomado.
            $anterior = $order->payment_status;
            $faltas = $this->orders->reativarPago($order);

            $order->registrarIncidente(
                "Pagamento aprovado pela Appmax com o pedido já cancelado aqui (status de pagamento era \"{$anterior}\"). "
                .'Pedido reativado e estoque retomado.'
                .($faltas ? ' ATENÇÃO, estoque insuficiente: '.implode('; ', $faltas).'.' : '')
            );

            Log::warning('Appmax webhook: INCIDENTE — pedido cancelado reativado por aprovação', [
                'order_id'        => $order->id,
                'appmax_order_id' => $order->appmax_order_id,
                'status_anterior' => $anterior,
                'faltas'          => $faltas,
            ]);

            $this->enfileiraEnvioErp($order, 'confirmar');
            AvisoPedido::agendar($order);

            return true;
        }

        // Mesmo caminho da reconciliação: marca pago e efetiva a reserva no
        // ERP (fase 2 Microvix), sem nunca derrubar a resposta.
        $this->orders->aprovarPagamento($order);

        return true;
    }

    protected function markFailed(Order $order, string $situacao): bool
    {
        if ($order->isPaid()) {
            // Recusado pelo antifraude DEPOIS de aprovado aqui: a Appmax
            // estorna; o admin decide (o pedido pode já ter sido separado).
            $order->registrarIncidente(
                "Appmax recusou o pagamento (situação \"{$situacao}\") com o pedido já pago aqui. Conferir no painel antes de enviar."
            );
            Log::warning('Appmax webhook: INCIDENTE — recusa de pedido já pago', ['order_id' => $order->id]);

            return true;
        }

        // Cartão recusado em análise posterior: cancela e devolve estoque.
        $this->orders->cancelAndRestoreStock($order, 'failed');

        return true;
    }

    protected function markExpired(Order $order): bool
    {
        // Pix/boleto venceu sem pagamento: libera o estoque reservado.
        if (! $order->isPaid()) {
            $this->orders->cancelAndRestoreStock($order, 'expired');
        }

        return true;
    }

    /** Enfileirar o espelho no ERP jamais pode quebrar a resposta do webhook. */
    protected function enfileiraEnvioErp(Order $order, string $acao): void
    {
        if (! EnviarPedidoMicrovix::integracaoLigada()) {
            return; // integração desligada (leva 08): nem enfileira
        }

        try {
            EnviarPedidoMicrovix::dispatch($order->id, $acao);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function markRefunded(Order $order, bool $isChargeback): bool
    {
        $order->update([
            'payment_status' => $isChargeback ? 'chargeback' : 'refunded',
            'status'         => 'cancelled',
        ]);

        return true;
    }

    /** Estorno parcial e chargeback ganho: só trilha pro admin. */
    protected function registrar(Order $order, string $acao, array $data): void
    {
        $valor = $data['refund_amount'] ?? $data['total_refunded'] ?? null;

        $texto = $acao === 'estorno_parcial'
            ? 'Estorno PARCIAL feito na Appmax'.(is_numeric($valor) ? ' (R$ '.number_format((float) $valor, 2, ',', '.').')' : '').'. Status do pedido não mudou.'
            : 'Chargeback ganho na Appmax: o valor voltou para a loja. Conferir o status do pedido.';

        // Reentrega não duplica a trilha.
        if (! collect($order->incidentes())->contains('texto', $texto)) {
            $order->registrarIncidente($texto);
        }

        Log::info('Appmax webhook: '.$acao.' registrado', ['order_id' => $order->id]);
    }
}
