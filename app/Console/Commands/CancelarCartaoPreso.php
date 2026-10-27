<?php

namespace App\Console\Commands;

use App\Exceptions\AppmaxException;
use App\Models\Order;
use App\Services\AppmaxService;
use App\Services\AvisoPedido;
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
 * expiração da Appmax. Cartão é síncrono — pending velho é processo morto
 * ou cobrança que ficou indefinida (timeout/5xx, "pagamento em verificação").
 *
 * NUNCA cancela às cegas: pedido que chegou à Appmax é consultado antes
 * (GET /v1/orders/{id}). Aprovado lá → aprova aqui. Recusado/pendente/
 * inexistente → cancela e devolve o estoque. Em análise antifraude ou
 * consulta falhou → não mexe, tenta de novo na próxima rodada.
 *
 * Pedido "em verificação" é reconciliado já a partir de 2 min (só com
 * resposta conclusiva); o resto respeita a janela de --minutos.
 */
class CancelarCartaoPreso extends Command
{
    protected $signature = 'pedidos:cancelar-cartao-preso
        {--minutos=60 : Idade mínima do pedido pendente para cancelar}
        {--dry-run : Mostra o que seria feito, sem gravar}';

    protected $description = 'Reconcilia com a Appmax os pedidos pending de cartão parados: aprova o que foi pago, cancela o resto e devolve o estoque';

    /** Idade mínima para reconciliar um pedido em verificação. */
    protected const MINUTOS_VERIFICACAO = 2;

    public function handle(OrderService $orders, AppmaxService $appmax): int
    {
        $minutos = max(1, (int) $this->option('minutos'));
        $dryRun = (bool) $this->option('dry-run');

        $candidatos = Order::where('status', 'pending')
            ->where('payment_status', 'pending')
            ->where('payment_method', 'cartao')
            ->where('created_at', '<=', now()->subMinutes(min($minutos, self::MINUTOS_VERIFICACAO)))
            ->orderBy('id')
            ->get()
            ->filter(fn (Order $o) => $o->created_at <= now()->subMinutes($minutos) || $o->pagamentoEmVerificacao())
            ->values();

        if ($candidatos->isEmpty()) {
            $this->info("Nenhum pedido pending de cartão com mais de {$minutos} min.");

            return self::SUCCESS;
        }

        // Decide antes de gravar (o dry-run também consulta: GET não muda nada).
        $planos = $candidatos->map(fn (Order $o) => [$o, $this->decidir($o, $appmax, $minutos)]);

        $this->table(
            ['Pedido', 'Cliente', 'Total', 'Criado em', 'Ação'],
            $planos->map(fn ($p) => [
                "#{$p[0]->id}",
                $p[0]->ship_name ?? $p[0]->user?->email ?? '—',
                'R$ '.number_format((float) $p[0]->total, 2, ',', '.'),
                data_br($p[0]->created_at),
                $p[1]['acao'].' — '.$p[1]['motivo'],
            ])->all(),
        );

        $cancelar = $planos->where('1.acao', 'cancelar');
        $aprovar = $planos->where('1.acao', 'aprovar');

        if ($dryRun) {
            $this->info("Dry-run: {$cancelar->count()} pedido(s) seriam cancelado(s) e {$aprovar->count()} aprovado(s). Nada foi gravado.");

            return self::SUCCESS;
        }

        foreach ($aprovar as [$order, $plano]) {
            $order->update([
                'status'         => 'processing',
                'payment_status' => 'paid',
                'paid_at'        => $order->paid_at ?? now(),
                'notes'          => trim(($order->notes ?? '')
                    ."\nAprovado automaticamente em ".data_br(now())
                    .': a consulta à Appmax mostrou o pagamento aprovado.'),
            ]);

            Log::info('pedidos:cancelar-cartao-preso — pedido aprovado pela consulta à Appmax', [
                'order_id'        => $order->id,
                'appmax_order_id' => $order->appmax_order_id,
            ]);

            AvisoPedido::agendar($order);
        }

        foreach ($cancelar as [$order, $plano]) {
            // Idempotente e já devolve size_stock + stock_qty.
            $orders->cancelAndRestoreStock($order, 'failed');

            // Trilha no próprio pedido — o admin precisa saber que foi
            // automático, não um cancelamento de gestão.
            $order->update([
                'notes' => trim(($order->notes ?? '')
                    ."\nCancelado automaticamente em ".data_br(now())
                    .": pagamento de cartão interrompido ({$plano['motivo']}), estoque devolvido."),
            ]);

            Log::info('pedidos:cancelar-cartao-preso — pedido cancelado, estoque devolvido', [
                'order_id' => $order->id,
                'total'    => $order->total,
                'criado'   => $order->created_at->toDateTimeString(),
                'motivo'   => $plano['motivo'],
            ]);
        }

        $mantidos = $planos->count() - $cancelar->count() - $aprovar->count();

        $this->info("{$cancelar->count()} pedido(s) cancelado(s), estoque devolvido. {$aprovar->count()} aprovado(s). {$mantidos} mantido(s) pendente(s).");

        return self::SUCCESS;
    }

    /** @return array{acao: 'cancelar'|'aprovar'|'manter', motivo: string} */
    protected function decidir(Order $order, AppmaxService $appmax, int $minutos): array
    {
        $velho = $order->created_at <= now()->subMinutes($minutos);

        // Nem chegou a criar o pedido na Appmax: nenhuma cobrança saiu.
        if (! $order->appmax_order_id) {
            return $velho
                ? ['acao' => 'cancelar', 'motivo' => "pendente há mais de {$minutos} min, sem pedido na Appmax"]
                : ['acao' => 'manter', 'motivo' => 'sem pedido na Appmax, dentro da janela'];
        }

        try {
            $situacao = $appmax->consultarSituacao($order);
        } catch (AppmaxException $e) {
            Log::warning('pedidos:cancelar-cartao-preso — consulta à Appmax falhou, pedido mantido', [
                'order_id' => $order->id,
                'detalhe'  => $e->detalhe,
            ]);

            return ['acao' => 'manter', 'motivo' => 'consulta à Appmax falhou, tenta na próxima rodada'];
        }

        return match ($situacao) {
            'pago' => ['acao' => 'aprovar', 'motivo' => 'Appmax aprovada'],

            // Conclusivo pra recusa: cancela já, mesmo dentro da janela.
            'recusado', 'inexistente', 'estornado' => ['acao' => 'cancelar', 'motivo' => "Appmax: {$situacao}"],

            // Cartão "pendente" na Appmax = não autorizado; só cancela velho.
            'pendente' => $velho
                ? ['acao' => 'cancelar', 'motivo' => "Appmax pendente há mais de {$minutos} min"]
                : ['acao' => 'manter', 'motivo' => 'Appmax pendente, dentro da janela'],

            default => ['acao' => 'manter', 'motivo' => "Appmax: {$situacao}"],
        };
    }
}
