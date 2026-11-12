<?php

namespace App\Services\Microvix;

use App\Jobs\EnviarPedidoMicrovix;
use App\Models\MicrovixProduct;
use App\Models\Order;
use App\Rules\Cidade;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Regras do envio de pedido à Linx (fase 2), num lugar só — usadas pelo
 * job, pelo sender, pelos pontos de pagamento aprovado, pelo admin, pelos
 * e-mails de aviso e pela conciliação de estoque:
 *
 * - só pedido PAGO vai, direto com somente_reserva=0 (sem reserva prévia);
 * - só pedido criado a partir de MICROVIX_ENVIO_A_PARTIR_DE;
 * - pedido com microvix_pedido_id nunca é reenviado;
 * - item sem vínculo, garrafa personalizada, meio/parcela sem plano, sem
 *   CPF ou endereço incompleto → "manual" (lançar manualmente na Linx).
 *
 * Estados (orders.microvix_envio_estado): pendente | enviando | enviado |
 * manual | erro | lancado_manual. O motivo do manual/erro fica em
 * microvix_envio_erro.
 */
class MicrovixEnvio
{
    /** Estados em que o admin pode reenviar. */
    public const REENVIAVEIS = ['erro', 'manual'];

    public const SITUACOES = [
        'enviado'        => 'Enviado',
        'pendente'       => 'Na fila',
        'manual'         => 'Manual',
        'erro'           => 'Erro',
        'lancado_manual' => 'Lançado manualmente',
        'nao_aplicavel'  => 'Não aplicável',
    ];

    public static function ligado(): bool
    {
        return (bool) config('microvix.envia_pedido');
    }

    /** MICROVIX_ENVIO_A_PARTIR_DE (dd/mm/aaaa) no início do dia; null se vazia ou inválida. */
    public static function dataDeCorte(): ?Carbon
    {
        $valor = trim((string) config('microvix.envio_a_partir_de'));
        if ($valor === '' || ! preg_match('#^\d{2}/\d{2}/\d{4}$#', $valor)) {
            return null;
        }

        try {
            $data = Carbon::createFromFormat('!d/m/Y', $valor);
        } catch (Throwable) {
            return null;
        }

        // createFromFormat aceita 31/02 rolando pro mês seguinte: recusa.
        return $data->format('d/m/Y') === $valor ? $data : null;
    }

    /** Pedido dentro da integração (criado a partir da data de corte). */
    public static function noPeriodo(Order $order): bool
    {
        $corte = self::dataDeCorte();

        return $corte !== null && $order->created_at !== null && $order->created_at->gte($corte);
    }

    /** Pode (ainda) ir para a Linx: no período, pago, não cancelado e nunca enviado. */
    public static function elegivel(Order $order): bool
    {
        return self::noPeriodo($order)
            && $order->isPaid()
            && $order->status !== 'cancelled'
            && $order->microvix_pedido_id === null
            && ! in_array($order->microvix_envio_estado, ['enviado', 'lancado_manual'], true);
    }

    /**
     * Pagamento aprovado (webhook, reconciliação, checkout com cartão aprovado
     * na hora, cancelar-cartao-preso, reativação): enfileira o envio. Nunca
     * derruba o chamador.
     */
    public static function agendar(Order $order): void
    {
        if (! self::ligado()) {
            return;
        }

        try {
            $order->refresh();

            if (self::dataDeCorte() === null) {
                Log::warning('Microvix: MICROVIX_ENVIO_A_PARTIR_DE vazia ou inválida — pedido não enviado', ['order_id' => $order->id]);

                return;
            }

            if (! self::elegivel($order) || in_array($order->microvix_envio_estado, ['pendente', 'enviando'], true)) {
                return;
            }

            $order->forceFill(['microvix_envio_estado' => 'pendente'])->save();
            EnviarPedidoMicrovix::dispatch($order->id)->afterCommit();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Admin pode reenviar? (só erro/manual, respeitando corte e idempotência) */
    public static function podeReenviar(Order $order): bool
    {
        return in_array($order->microvix_envio_estado, self::REENVIAVEIS, true) && self::elegivel($order);
    }

    /**
     * Motivo para NÃO enviar automaticamente (lançar manualmente na Linx),
     * ou null se o pedido pode ir. Só leitura: o e-mail usa o mesmo cálculo
     * do job, sem depender de o job já ter rodado.
     */
    public function motivoManual(Order $order): ?string
    {
        $order->loadMissing('items');
        $motivos = [];

        foreach ($order->items as $item) {
            $rotulo = trim($item->product_name.' '.$item->size);
            if ($item->isCustom()) {
                $motivos[] = "garrafa personalizada ({$rotulo})";
            } elseif ($this->codigoProduto($item->product_id, $item->size) === null) {
                $motivos[] = "item sem vínculo com a Linx ({$rotulo})";
            }
        }

        if ($this->planoPagamento($order) === null) {
            $motivos[] = 'sem plano de pagamento na Linx para '.$this->descricaoPagamento($order);
        }

        if (preg_replace('/\D+/', '', (string) $order->ship_cpf) === '') {
            $motivos[] = 'pedido sem CPF';
        }

        $faltando = array_keys(array_filter([
            'rua'    => $order->ship_street,
            'número' => $order->ship_number,
            'bairro' => $order->ship_neighborhood,
            'CEP'    => $order->ship_zipcode,
            'cidade' => $order->ship_city,
            'UF'     => $order->ship_state,
        ], fn ($v) => trim((string) $v) === ''));
        if ($faltando) {
            $motivos[] = 'endereço incompleto (falta '.implode(', ', $faltando).')';
        } elseif (! Cidade::valida($order->ship_city)) {
            $motivos[] = 'cidade inválida ("'.trim((string) $order->ship_city).'")';
        }

        return $motivos ? implode('; ', $motivos) : null;
    }

    /** Linha do e-mail de aviso, ou null quando não há nada a lançar à mão. */
    public function avisoManual(Order $order): ?string
    {
        if (! self::ligado() || ! self::noPeriodo($order) || ! $order->isPaid()
            || $order->microvix_pedido_id !== null || $order->microvix_envio_estado === 'lancado_manual') {
            return null;
        }

        $motivo = $this->motivoManual($order);

        return $motivo === null ? null : "Lançar manualmente na Linx: {$motivo}";
    }

    public function planoPagamento(Order $order): ?int
    {
        $planos = config('microvix.planos_pagamento', []);

        $plano = $order->payment_method === 'cartao'
            ? ($planos['cartao'][$this->parcelas($order)] ?? null)
            : ($planos[$order->payment_method] ?? null);

        return filled($plano) && (int) $plano > 0 ? (int) $plano : null;
    }

    public function parcelas(Order $order): int
    {
        return max(1, (int) ($order->payment_details['installments'] ?? 1));
    }

    public function codigoProduto(?int $productId, ?string $size): ?int
    {
        if ($productId === null) {
            return null;
        }

        $codigo = MicrovixProduct::where('product_id', $productId)->where('size', $size)->value('codigoproduto');

        return $codigo === null ? null : (int) $codigo;
    }

    protected function descricaoPagamento(Order $order): string
    {
        return match ($order->payment_method) {
            'cartao' => 'cartão '.$this->parcelas($order).'x',
            'pix'    => 'Pix',
            'boleto' => 'boleto',
            default  => "'{$order->payment_method}'",
        };
    }

    /* -----------------------------------------------------------------
     | Situação para o admin (rótulo + filtro com o mesmo critério)
     ------------------------------------------------------------------ */

    /** @return array{chave: ?string, rotulo: string} chave null = sem filtro (aguardando pagamento) */
    public static function situacao(Order $order): array
    {
        if (self::dataDeCorte() === null || ! self::noPeriodo($order)) {
            return ['chave' => 'nao_aplicavel', 'rotulo' => 'Não aplicável (anterior à integração)'];
        }
        if ($order->microvix_pedido_id !== null) {
            return ['chave' => 'enviado', 'rotulo' => 'Enviado'];
        }

        return match ($order->microvix_envio_estado) {
            'manual'               => ['chave' => 'manual', 'rotulo' => 'Manual'],
            'erro'                 => ['chave' => 'erro', 'rotulo' => 'Erro'],
            'lancado_manual'       => ['chave' => 'lancado_manual', 'rotulo' => 'Lançado manualmente'],
            'pendente', 'enviando' => ['chave' => 'pendente', 'rotulo' => 'Na fila'],
            default                => match (true) {
                $order->status === 'cancelled' => ['chave' => 'nao_aplicavel', 'rotulo' => 'Não aplicável (cancelado sem pagamento)'],
                $order->isPaid()               => ['chave' => 'pendente', 'rotulo' => 'Na fila'],
                default                        => ['chave' => null, 'rotulo' => 'Aguardando pagamento'],
            },
        };
    }

    public static function filtrar(Builder $q, string $chave): Builder
    {
        $corte = self::dataDeCorte();

        if ($chave === 'nao_aplicavel') {
            return $corte === null ? $q : $q->where(fn ($w) => $w
                ->where('created_at', '<', $corte)
                ->orWhere(fn ($c) => $c->where('status', 'cancelled')->whereNull('microvix_pedido_id')->whereNull('microvix_envio_estado')));
        }

        if ($corte === null) {
            return $q->whereRaw('1 = 0');
        }

        $q->where('created_at', '>=', $corte);

        return match ($chave) {
            'enviado'  => $q->whereNotNull('microvix_pedido_id'),
            'pendente' => $q->whereNull('microvix_pedido_id')->where(fn ($w) => $w
                ->whereIn('microvix_envio_estado', ['pendente', 'enviando'])
                ->orWhere(fn ($n) => $n->whereNull('microvix_envio_estado')->where('payment_status', 'paid')->where('status', '!=', 'cancelled'))),
            default    => $q->whereNull('microvix_pedido_id')->where('microvix_envio_estado', $chave),
        };
    }

    /* -----------------------------------------------------------------
     | Estoque (regra 7): o que o site já vendeu e a Linx ainda não viu
     ------------------------------------------------------------------ */

    /**
     * Quantidade por "product_id|size" em pedidos do período ainda não
     * refletidos na Linx: pendentes de pagamento (não cancelados nem
     * expirados) e pagos sem microvix_pedido_id (fora "lançado manualmente").
     *
     * @return array<string, int>
     */
    public static function quantidadesNaoRefletidas(): array
    {
        $corte = self::dataDeCorte();
        if ($corte === null) {
            return [];
        }

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('order_items.product_id')
            ->where('orders.created_at', '>=', $corte)
            ->where('orders.status', '!=', 'cancelled')
            ->where(fn ($w) => $w
                ->where('orders.payment_status', 'pending')
                ->orWhere(fn ($p) => $p
                    ->where('orders.payment_status', 'paid')
                    ->whereNull('orders.microvix_pedido_id')
                    ->where(fn ($e) => $e->whereNull('orders.microvix_envio_estado')->orWhere('orders.microvix_envio_estado', '!=', 'lancado_manual'))))
            ->groupBy('order_items.product_id', 'order_items.size')
            ->selectRaw('order_items.product_id, order_items.size, SUM(order_items.qty) as qtd')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->product_id.'|'.$r->size => (int) $r->qtd])
            ->all();
    }
}
