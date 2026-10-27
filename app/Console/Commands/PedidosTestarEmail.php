<?php

namespace App\Console\Commands;

use App\Mail\PedidoNovo;
use App\Models\Order;
use App\Services\AvisoPedido;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Teste do aviso de pedido por e-mail (leva 16): manda o e-mail de um
 * pedido existente, na hora (sem fila), e mostra se o SMTP aceitou ou o
 * erro completo. Não mexe em orders.notificado_em — é só diagnóstico.
 */
class PedidosTestarEmail extends Command
{
    protected $signature = 'pedidos:testar-email
                            {pedido : id do pedido (12) ou número (APTK-00012)}
                            {--para=* : manda só para estes endereços em vez de PEDIDOS_NOTIFICAR}';

    protected $description = 'Envia o aviso de pedido novo por e-mail e mostra a resposta do SMTP';

    public function handle(): int
    {
        $id = (int) preg_replace('/\D/', '', (string) $this->argument('pedido'));
        $order = $id ? Order::find($id) : null;

        if (! $order) {
            $this->error('Pedido não encontrado: '.$this->argument('pedido'));

            return self::FAILURE;
        }

        $para = $this->option('para') ?: AvisoPedido::destinatarios();
        $mailer = config('mail.default');
        $smtp = config('mail.mailers.smtp');

        $this->table(['', ''], [
            ['Pedido', 'APTK-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT)],
            ['Mailer', $mailer],
            ['Servidor', $mailer === 'smtp' ? ($smtp['host'] ?? '—').':'.($smtp['port'] ?? '—').' ('.($smtp['encryption'] ?: 'sem criptografia').')' : '—'],
            ['Usuário', $mailer === 'smtp' ? ($smtp['username'] ?: '—') : '—'],
            ['Remetente', config('mail.from.name').' <'.config('mail.from.address').'>'],
            ['Reply-to', config('pedidos.reply_to') ?: '—'],
            ['Para', $para ? implode(', ', $para) : '—'],
        ]);

        if ($para === []) {
            $this->error('Nenhum destinatário: preencha PEDIDOS_NOTIFICAR no .env (e rode config:cache) ou use --para=.');

            return self::FAILURE;
        }

        if ($mailer !== 'smtp') {
            $this->warn("MAIL_MAILER={$mailer}: o e-mail não passa por SMTP (com 'log' vai para storage/logs).");
        }

        try {
            $enviada = Mail::to($para)->send(new PedidoNovo($order));
        } catch (Throwable $e) {
            $this->error('O servidor recusou o envio.');

            for ($erro = $e; $erro; $erro = $erro->getPrevious()) {
                $this->line(get_class($erro).': '.$erro->getMessage());
            }

            return self::FAILURE;
        }

        $this->info('SMTP aceitou o e-mail.');

        // Sem o getDebug(): a conversa SMTP inclui o AUTH com usuário e senha
        // em base64.
        if ($enviada) {
            $this->line('Message-ID: '.$enviada->getMessageId());
        }

        return self::SUCCESS;
    }
}
