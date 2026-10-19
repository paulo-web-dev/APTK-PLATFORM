<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\ReconciliacaoPagamento;
use Illuminate\Console\Command;

/**
 * Reconcilia Pix e boleto pendentes com a Appmax (scheduler, a cada 5 min).
 * Independe do webhook: se ele se perder, o pedido pago aparece pago aqui
 * na rodada seguinte. Regras em ReconciliacaoPagamento.
 *
 * Janela: Pix até 2h, boleto até 10 dias (config appmax.reconciliacao).
 * Fora dela o pedido não é consultado nem cancelado: fica como "pagamento a
 * conferir" no admin.
 */
class ReconciliarPagamentos extends Command
{
    protected $signature = 'pagamentos:reconciliar
        {--dry-run : Consulta e mostra o que faria, sem gravar}';

    protected $description = 'Consulta na Appmax os Pix/boletos pendentes e aprova ou cancela só com resposta conclusiva';

    public function handle(ReconciliacaoPagamento $reconciliacao): int
    {
        $pedidos = ReconciliacaoPagamento::dentroDaJanela()->orderBy('id')->get();
        $aConferir = ReconciliacaoPagamento::aConferir()->count();

        if ($pedidos->isEmpty()) {
            $this->info('Nenhum Pix/boleto pendente dentro da janela.'.($aConferir ? " {$aConferir} a conferir no admin." : ''));

            return self::SUCCESS;
        }

        $aplicar = ! $this->option('dry-run');
        $linhas = [];
        $contagem = ['aprovar' => 0, 'cancelar' => 0, 'manter' => 0];

        foreach ($pedidos as $order) {
            $r = $reconciliacao->reconciliar($order, $aplicar);
            $contagem[$r['acao']]++;
            $linhas[] = ["#{$order->id}", $order->payment_method, "#{$order->appmax_order_id}", data_br($order->created_at), $r['acao'].' — '.$r['motivo']];
        }

        $this->table(['Pedido', 'Método', 'Appmax', 'Criado em', 'Ação'], $linhas);

        $this->info(($aplicar ? '' : 'Dry-run: ')
            ."{$contagem['aprovar']} aprovado(s), {$contagem['cancelar']} cancelado(s), {$contagem['manter']} mantido(s)."
            .($aConferir ? " {$aConferir} a conferir no admin." : ''));

        return self::SUCCESS;
    }
}
