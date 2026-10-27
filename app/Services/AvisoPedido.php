<?php

namespace App\Services;

use App\Jobs\EnviarAvisoPedido;
use App\Mail\PedidoNovo;
use App\Models\Order;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Aviso de pedido novo por e-mail para a equipe (leva 16).
 *
 * Automático: agendar() no momento em que o pagamento é confirmado (cartão
 * aprovado no checkout, Pix/boleto pelo webhook ou pela reconciliação) —
 * nunca na criação do pedido, então cartão recusado não avisa. Vai pela
 * fila 'notificacoes' e é idempotente por orders.notificado_em.
 *
 * Manual: o botão do admin chama enviar() na hora, mesmo já enviado.
 *
 * Falha nunca sobe para o checkout nem para o webhook: fica em
 * orders.notificacao_erro e no log.
 */
class AvisoPedido
{
    /** @return list<string> */
    public static function destinatarios(): array
    {
        return config('pedidos.notificar', []);
    }

    /** Enfileira o aviso automático. Já avisado = nada; erro ao enfileirar = só log. */
    public static function agendar(Order $order): void
    {
        if ($order->notificado_em) {
            return;
        }

        try {
            EnviarAvisoPedido::dispatch($order->id);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Envia agora para os destinatários da config e grava o resultado no
     * pedido. Relança a exceção (o job retenta; o admin mostra no flash).
     */
    public function enviar(Order $order): ?SentMessage
    {
        try {
            $para = self::destinatarios();

            if ($para === []) {
                throw new RuntimeException('Nenhum destinatário válido em PEDIDOS_NOTIFICAR (.env).');
            }

            $enviada = Mail::to($para)->send(new PedidoNovo($order));
        } catch (Throwable $e) {
            $order->forceFill([
                'notificacao_erro'    => mb_substr(get_class($e).': '.$e->getMessage(), 0, 2000),
                'notificacao_erro_em' => now(),
            ])->save();

            Log::error('Aviso de pedido por e-mail falhou', ['order_id' => $order->id, 'erro' => $e->getMessage()]);

            throw $e;
        }

        $order->forceFill([
            'notificado_em'       => now(),
            'notificacao_erro'    => null,
            'notificacao_erro_em' => null,
        ])->save();

        return $enviada;
    }
}
