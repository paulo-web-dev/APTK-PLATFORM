<?php

namespace App\Services;

use App\Exceptions\AppmaxException;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Reconciliação de Pix e boleto pendentes com a Appmax, independente do
 * webhook: um webhook perdido nunca deixa pedido pago como pendente.
 *
 * Fonte da verdade é a consulta GET /v1/orders/{id}. Só age com resposta
 * CONCLUSIVA:
 *   - pago                         → aprova (mesmo caminho do webhook);
 *   - recusado (cancelado na API)  → cancela e devolve o estoque;
 *   - Pix "pendente" DEPOIS da expiração (+ folga) → cancela: é a própria
 *     Appmax dizendo que não foi pago, nunca só o relógio local;
 *   - o resto (pendente dentro do prazo, antifraude, estornado, inexistente,
 *     desconhecido, consulta com erro) → não mexe.
 *
 * Boleto "pendente" nunca cancela aqui (compensação pode atrasar): passada
 * a janela, o pedido aparece no admin como "pagamento a conferir".
 *
 * Usada por pagamentos:reconciliar (scheduler, 5 min), pelo polling da tela
 * de sucesso e pelo appmax:diagnostico.
 */
class ReconciliacaoPagamento
{
    public const METODOS = ['pix', 'boleto'];

    public function __construct(
        protected AppmaxService $appmax,
        protected OrderService $orders,
    ) {
    }

    /** Pedidos abertos de Pix/boleto que já chegaram à Appmax. */
    public static function abertos(): Builder
    {
        return Order::query()
            ->where('status', 'pending')
            ->where('payment_status', 'pending')
            ->whereIn('payment_method', self::METODOS)
            ->whereNotNull('appmax_order_id');
    }

    /** Dentro da janela de reconciliação automática (Pix 2h, boleto 10 dias). */
    public static function dentroDaJanela(): Builder
    {
        return self::abertos()->where(function (Builder $q) {
            $q->where(fn ($p) => $p->where('payment_method', 'pix')
                ->where('created_at', '>=', now()->subHours((int) config('appmax.reconciliacao.pix_horas', 2))))
              ->orWhere(fn ($b) => $b->where('payment_method', 'boleto')
                ->where('created_at', '>=', now()->subDays((int) config('appmax.reconciliacao.boleto_dias', 10))));
        });
    }

    /** Fora da janela e ainda aberto: "pagamento a conferir" no admin. */
    public static function aConferir(): Builder
    {
        return self::abertos()->whereNotIn('id', self::dentroDaJanela()->select('id'));
    }

    public static function precisaConferir(Order $order): bool
    {
        return self::aConferir()->whereKey($order->id)->exists();
    }

    /**
     * Consulta a Appmax e decide (e aplica, salvo $aplicar = false).
     *
     * @return array{acao: 'aprovar'|'cancelar'|'manter', motivo: string, situacao: ?string}
     */
    public function reconciliar(Order $order, bool $aplicar = true): array
    {
        if ($order->status !== 'pending' || $order->payment_status !== 'pending') {
            return ['acao' => 'manter', 'motivo' => 'pedido não está pendente aqui', 'situacao' => null];
        }

        if (! $order->appmax_order_id) {
            return ['acao' => 'manter', 'motivo' => 'sem pedido na Appmax', 'situacao' => null];
        }

        try {
            $situacao = $this->appmax->consultarSituacao($order);
        } catch (AppmaxException $e) {
            Log::warning('Reconciliação: consulta à Appmax falhou, pedido mantido', [
                'order_id' => $order->id,
                'detalhe'  => $e->detalhe,
            ]);

            return ['acao' => 'manter', 'motivo' => 'consulta à Appmax falhou', 'situacao' => null];
        }

        $decisao = $this->decidir($order, $situacao) + ['situacao' => $situacao];

        if ($aplicar) {
            $this->aplicar($order, $decisao);
        }

        return $decisao;
    }

    /** @return array{acao: 'aprovar'|'cancelar'|'manter', motivo: string} */
    protected function decidir(Order $order, string $situacao): array
    {
        if ($situacao === 'pago') {
            return ['acao' => 'aprovar', 'motivo' => 'Appmax: pago'];
        }

        // Cartão aqui só aprova; cancelar cartão é do cancelar-cartao-preso.
        if ($order->payment_method === 'cartao') {
            return ['acao' => 'manter', 'motivo' => "Appmax: {$situacao} (cartão: só aprovação)"];
        }

        if ($situacao === 'recusado') {
            return ['acao' => 'cancelar', 'motivo' => 'Appmax: cancelado'];
        }

        if ($situacao === 'pendente' && $order->payment_method === 'pix') {
            $expira = $this->expiracaoPix($order);

            if (! $expira) {
                return ['acao' => 'manter', 'motivo' => 'Appmax: pendente; Pix sem data de expiração gravada'];
            }

            $limite = $expira->copy()->addMinutes((int) config('appmax.reconciliacao.pix_folga_minutos', 10));

            return now()->greaterThan($limite)
                ? ['acao' => 'cancelar', 'motivo' => 'Appmax: não pago depois da expiração do Pix']
                : ['acao' => 'manter', 'motivo' => 'Appmax: pendente, Pix ainda dentro do prazo'];
        }

        return ['acao' => 'manter', 'motivo' => "Appmax: {$situacao}"];
    }

    protected function aplicar(Order $order, array $decisao): void
    {
        if ($decisao['acao'] === 'aprovar') {
            $this->orders->aprovarPagamento($order);
            $this->anotar($order, 'Aprovado pela reconciliação com a Appmax');
        } elseif ($decisao['acao'] === 'cancelar') {
            $this->orders->cancelAndRestoreStock($order, 'expired');
            $this->anotar($order, 'Cancelado pela reconciliação com a Appmax ('.$decisao['motivo'].'), estoque devolvido');
        } else {
            return;
        }

        Log::info('Reconciliação: pedido '.$decisao['acao'], [
            'order_id'        => $order->id,
            'appmax_order_id' => $order->appmax_order_id,
            'motivo'          => $decisao['motivo'],
        ]);
    }

    protected function anotar(Order $order, string $texto): void
    {
        $order->update([
            'notes' => trim(($order->notes ?? '')."\n".$texto.' em '.data_br(now()).'.'),
        ]);
    }

    /** pix_expiration_date da resposta da Appmax (horário de Brasília). */
    protected function expiracaoPix(Order $order): ?Carbon
    {
        $exp = $order->payment_details['expiration'] ?? null;

        if (blank($exp)) {
            return null;
        }

        try {
            return Carbon::parse($exp, 'America/Sao_Paulo');
        } catch (\Throwable) {
            return null;
        }
    }
}
