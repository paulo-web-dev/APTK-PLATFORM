<?php

namespace App\Console\Commands;

use App\Exceptions\AppmaxException;
use App\Models\Order;
use App\Services\AppmaxService;
use App\Services\ReconciliacaoPagamento;
use Illuminate\Console\Command;

/**
 * Raio-x de um pedido: o que está gravado aqui × o que a Appmax diz.
 * Só leitura por padrão; --reconciliar aplica a mesma regra da rotina de 5
 * min (aprova/cancela só com resposta conclusiva), ignorando a janela.
 * Nunca imprime credencial nem token.
 */
class AppmaxDiagnostico extends Command
{
    protected $signature = 'appmax:diagnostico
        {--pedido= : ID do pedido aqui (orders.id)}
        {--reconciliar : Aplica a reconciliação com a resposta da Appmax}';

    protected $description = 'Compara um pedido local com a Appmax (GET /v1/orders/{id}) e, opcionalmente, reconcilia';

    public function handle(AppmaxService $appmax, ReconciliacaoPagamento $reconciliacao): int
    {
        $order = Order::find((int) $this->option('pedido'));

        if (! $order) {
            $this->error('Pedido não encontrado. Use --pedido=ID (o número de APTK-000ID).');

            return self::FAILURE;
        }

        $this->line('<options=bold>Local</>');
        $this->table(['Campo', 'Valor'], [
            ['Pedido', 'APTK-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT)],
            ['Método', $order->payment_method],
            ['Status', $order->status.' ('.$order->statusLabel().')'],
            ['Pagamento', $order->payment_status],
            ['Total', 'R$ '.number_format((float) $order->total, 2, ',', '.')
                .' (frete '.number_format((float) $order->shipping_cost, 2, ',', '.').')'],
            ['Criado em', data_br($order->created_at)],
            ['Pago em', data_br($order->paid_at)],
            ['Pix expira', $order->payment_details['expiration'] ?? '—'],
            ['Pedido Appmax', $order->appmax_order_id ? '#'.$order->appmax_order_id : '—'],
            ['Janela de reconciliação', ReconciliacaoPagamento::dentroDaJanela()->whereKey($order->id)->exists()
                ? 'dentro' : (ReconciliacaoPagamento::precisaConferir($order) ? 'fora — a conferir' : 'n/a')],
        ]);

        if (! $order->appmax_order_id) {
            $this->warn('Pedido sem appmax_order_id: não chegou à Appmax, nada a consultar.');

            return self::SUCCESS;
        }

        try {
            $consulta = $appmax->consultarPedido($order);
        } catch (AppmaxException $e) {
            $this->error('Consulta à Appmax falhou: '.($e->detalhe ?? $e->getMessage()));

            return self::FAILURE;
        }

        $dados = $consulta['dados'];
        $centavos = fn ($v) => is_numeric($v) ? 'R$ '.number_format($v / 100, 2, ',', '.') : '—';

        $this->newLine();
        $this->line('<options=bold>Appmax</>');
        $this->table(['Campo', 'Valor'], [
            ['Status (API)', $consulta['status'] ?? '—'],
            ['Situação', $consulta['situacao']],
            ['Total pago', $centavos($dados['order']['total_paid'] ?? null)],
            ['Produtos', $centavos($dados['order']['amounts']['sub_total'] ?? null)],
            ['Frete', $centavos($dados['order']['amounts']['shipping_value'] ?? null)],
            ['Desconto', $centavos($dados['order']['amounts']['discount'] ?? null)],
            ['Método', $dados['payment']['method'] ?? '—'],
            ['Pago em (Appmax)', $dados['payment']['paid_at'] ?? '—'],
        ]);

        $r = $reconciliacao->reconciliar($order, (bool) $this->option('reconciliar'));

        $this->newLine();
        if ($this->option('reconciliar')) {
            $order->refresh();
            $this->info("Reconciliação: {$r['acao']} — {$r['motivo']}. Agora: {$order->status} / {$order->payment_status}.");
        } else {
            $this->info("Decisão (sem gravar): {$r['acao']} — {$r['motivo']}. Rode com --reconciliar para aplicar.");
        }

        return self::SUCCESS;
    }
}
