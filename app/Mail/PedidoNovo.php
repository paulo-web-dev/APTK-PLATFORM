<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\CustomService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso de pedido pago para a equipe (leva 16). Enviado por AvisoPedido
 * (fila ou botão do admin), nunca direto.
 */
class PedidoNovo extends Mailable
{
    use Queueable, SerializesModels;

    public const FORMAS = ['pix' => 'Pix', 'cartao' => 'Cartão de crédito', 'boleto' => 'Boleto'];

    public const STATUS_PAGAMENTO = [
        'pending'    => 'Aguardando',
        'paid'       => 'Pago',
        'failed'     => 'Recusado',
        'expired'    => 'Expirado',
        'refunded'   => 'Estornado',
        'chargeback' => 'Chargeback',
    ];

    public function __construct(public Order $order)
    {
        $this->order->loadMissing('items', 'user');
    }

    public function numero(): string
    {
        return 'APTK-'.str_pad((string) $this->order->id, 5, '0', STR_PAD_LEFT);
    }

    public function envelope(): Envelope
    {
        $replyTo = config('pedidos.reply_to');

        return new Envelope(
            subject: 'Novo pedido '.$this->numero(),
            replyTo: $replyTo ? [new Address($replyTo)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pedido-novo',
            text: 'emails.pedido-novo-texto',
            with: $this->dados(),
        );
    }

    /** Tudo já formatado para as duas versões (HTML e texto). */
    public function dados(): array
    {
        $o = $this->order;
        $d = $o->payment_details ?? [];

        $endereco = array_values(array_filter([
            trim($o->ship_street.', '.$o->ship_number.($o->ship_complement ? ' — '.$o->ship_complement : ''), ', '),
            $o->ship_neighborhood,
            trim($o->ship_city.($o->ship_state ? ' / '.$o->ship_state : '')),
            $o->ship_zipcode ? 'CEP '.$o->ship_zipcode : null,
        ]));

        $itens = $o->items->map(function ($it) {
            $custom = null;

            if ($it->isCustom()) {
                $p = app(CustomService::class)->exibir($it->personalizacao);
                $custom = [
                    'bebida' => $it->nomeExibido(),
                    'texto'  => $p['texto'],
                    // A fonte de verdade (o arquivo) + o nome de exibição do configurador.
                    'fonte'  => trim(($p['fonte'] ?? '').($p['fonte_nome'] && $p['fonte_nome'] !== $p['fonte'] ? ' ('.$p['fonte_nome'].')' : '')),
                    'cor'    => $p['cor_nome'].($p['cor_hex'] ? ' ('.$p['cor_hex'].')' : ''),
                ];
            }

            return [
                'nome'     => $it->product_name,
                'volume'   => $it->size,
                'qtd'      => (int) $it->qty,
                'unitario' => moeda_br($it->unit_price),
                'subtotal' => moeda_br($it->total_price),
                'custom'   => $custom,
            ];
        })->all();

        return [
            'numero'   => $this->numero(),
            'data'     => data_br($o->created_at),
            'pago_em'  => $o->paid_at ? data_br($o->paid_at) : null,
            'cliente'  => [
                'nome'     => $o->user->name ?? $o->ship_name ?? '—',
                'email'    => $o->user->email ?? '—',
                'cpf'      => cpf_formatado($o->ship_cpf ?: $o->user?->cpf) ?? '—',
                'telefone' => $o->ship_phone ?: ($o->user->phone ?? '—'),
            ],
            'destinatario' => $o->ship_name,
            'endereco'     => $endereco,
            'frete'        => [
                'nome'  => $o->shippingName() ?? '—',
                'valor' => moeda_br($o->shipping_cost),
                'prazo' => $o->shipping_days
                    ? 'até '.$o->shipping_days.' '.($o->shipping_days === 1 ? 'dia útil' : 'dias úteis')
                    : null,
            ],
            'itens'     => $itens,
            'subtotal'  => moeda_br($o->subtotal),
            'desconto'  => (float) $o->discount > 0 ? '− '.moeda_br($o->discount) : moeda_br(0),
            'frete_valor' => moeda_br($o->shipping_cost),
            'total'     => moeda_br($o->total),
            'pagamento' => [
                'forma'    => self::FORMAS[$o->payment_method] ?? ($o->payment_method ?: '—'),
                'parcelas' => $o->payment_method === 'cartao' ? ((int) ($d['installments'] ?? 1)).'x' : null,
                'status'   => self::STATUS_PAGAMENTO[$o->payment_status] ?? ($o->payment_status ?: '—'),
            ],
            'link_admin' => aptk_route('admin.orders.show', $o),
        ];
    }
}
