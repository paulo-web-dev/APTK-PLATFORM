<?php

namespace App\Services;

use App\Jobs\EnviarAvisoPagamento;
use App\Jobs\EnviarAvisoPedido;
use App\Mail\PagamentoConfirmado;
use App\Mail\PedidoNovo;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Avisos de pedido por e-mail para a equipe (leva 16, revista na leva 17).
 *
 * 1. Pedido criado: agendar() no fim do POST /checkout, qualquer método e
 *    qualquer desfecho (pago, aguardando, recusado). O e-mail mostra o
 *    status do momento do envio. Trava: orders.notificado_em.
 * 2. Pagamento confirmado depois (webhook, reconciliação, polling da tela de
 *    sucesso, cartão preso aprovado): agendarPagamento(), e-mail curto.
 *    Trava: orders.pagamento_notificado_em. Se o aviso 1 já saiu com o pedido
 *    pago, ele já conta como confirmação e o 2 não sai.
 *
 * Os dois vão pela fila 'notificacoes' com afterCommit. Falha nunca sobe
 * para o checkout nem para o webhook: fica em orders.notificacao_erro e no
 * log. O botão do admin chama enviar() na hora, mesmo já enviado.
 */
class AvisoPedido
{
    /** @return list<string> */
    public static function destinatarios(): array
    {
        return config('pedidos.notificar', []);
    }

    /** Enfileira o aviso de pedido criado. Já avisado = nada; erro ao enfileirar = só log. */
    public static function agendar(Order $order): void
    {
        if ($order->notificado_em) {
            return;
        }

        try {
            EnviarAvisoPedido::dispatch($order->id)->afterCommit();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Enfileira o aviso de pagamento confirmado. Já avisado = nada. */
    public static function agendarPagamento(Order $order): void
    {
        if ($order->pagamento_notificado_em) {
            return;
        }

        try {
            EnviarAvisoPagamento::dispatch($order->id)->afterCommit();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Aviso de pedido (status do momento), agora. Relança a exceção (o job
     * retenta; o admin mostra no flash).
     */
    public function enviar(Order $order): ?SentMessage
    {
        $enviada = $this->mandar($order, new PedidoNovo($order));

        $order->forceFill([
            'notificado_em' => now(),
            // Pedido já pago no aviso: a confirmação está nele.
            'pagamento_notificado_em' => $order->isPaid() ? ($order->pagamento_notificado_em ?? now()) : $order->pagamento_notificado_em,
        ])->save();

        return $enviada;
    }

    /** Aviso curto de pagamento confirmado, agora. */
    public function enviarPagamento(Order $order): ?SentMessage
    {
        $enviada = $this->mandar($order, new PagamentoConfirmado($order));

        $order->forceFill(['pagamento_notificado_em' => now()])->save();

        return $enviada;
    }

    protected function mandar(Order $order, Mailable $mail): ?SentMessage
    {
        try {
            $para = self::destinatarios();

            if ($para === []) {
                throw new RuntimeException('Nenhum destinatário válido em PEDIDOS_NOTIFICAR (.env).');
            }

            $enviada = Mail::to($para)->send($mail);
        } catch (Throwable $e) {
            $order->forceFill([
                'notificacao_erro'    => mb_substr(get_class($e).': '.$e->getMessage(), 0, 2000),
                'notificacao_erro_em' => now(),
            ])->save();

            Log::error('Aviso de pedido por e-mail falhou', ['order_id' => $order->id, 'aviso' => class_basename($mail), 'erro' => $e->getMessage()]);

            throw $e;
        }

        $order->forceFill(['notificacao_erro' => null, 'notificacao_erro_em' => null])->save();

        return $enviada;
    }
}
