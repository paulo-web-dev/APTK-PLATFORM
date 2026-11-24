<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\Microvix\MicrovixEnvio;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Segundo aviso (leva 17): pagamento confirmado de um pedido já avisado
 * como aguardando. Curto; o detalhe está no primeiro e-mail e no admin.
 */
class PagamentoConfirmado extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->order->loadMissing('user');
    }

    public function numero(): string
    {
        return 'APTK-'.str_pad((string) $this->order->id, 5, '0', STR_PAD_LEFT);
    }

    public function envelope(): Envelope
    {
        $replyTo = config('pedidos.reply_to');

        return new Envelope(
            subject: 'Pagamento confirmado '.$this->numero(),
            replyTo: $replyTo ? [new Address($replyTo)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pagamento-confirmado',
            text: 'emails.pagamento-confirmado-texto',
            with: $this->dados(),
        );
    }

    public function dados(): array
    {
        $o = $this->order;
        $d = $o->payment_details ?? [];

        return [
            'numero'     => $this->numero(),
            'linx_manual' => app(MicrovixEnvio::class)->avisoManual($o),
            'cliente'    => $o->user->name ?? $o->ship_name ?? '—',
            'total'      => moeda_br($o->total),
            'cupom'      => $o->coupon_code ? "cupom {$o->coupon_code} (−".moeda_br($o->discount).')' : null,
            'forma'      => (PedidoNovo::FORMAS[$o->payment_method] ?? ($o->payment_method ?: '—'))
                .($o->payment_method === 'cartao' ? ' · '.((int) ($d['installments'] ?? 1)).'x' : ''),
            'pago_em'    => data_br($o->paid_at ?? now()),
            'link_admin' => aptk_route('admin.orders.show', $o),
        ];
    }
}
