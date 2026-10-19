<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'address_id',
        'status',
        'subtotal',
        'discount',
        'shipping_cost',
        'total',
        'payment_method',
        'payment_status',
        'appmax_order_id',
        'appmax_customer_id',
        'appmax_pay_reference',
        'payment_details',
        'paid_at',
        'shipping_method',
        'shipping_days',
        'notes',
        // Snapshot do endereço de entrega (congelado na compra; ver OrderService)
        'ship_name',
        'ship_cpf',
        'ship_phone',
        'ship_zipcode',
        'ship_street',
        'ship_number',
        'ship_complement',
        'ship_neighborhood',
        'ship_city',
        'ship_state',
        // Integração Linx Microvix (fase 2 preenche)
        'microvix_pedido_id',
        'microvix_cod_cliente',
        'microvix_status',
        'microvix_synced_at',
        'microvix_reserved',
        'microvix_envio_estado',
        'microvix_envio_tentativas',
        'microvix_envio_erro',
        'microvix_envio_em',
    ];

    protected $casts = [
        'subtotal'        => 'decimal:2',
        'discount'        => 'decimal:2',
        'shipping_cost'   => 'decimal:2',
        'total'           => 'decimal:2',
        'payment_details' => 'array',
        'paid_at'         => 'datetime',
        'microvix_synced_at' => 'datetime',
        'microvix_reserved'  => 'boolean',
        'microvix_envio_em'  => 'datetime',
        'shipping_days'      => 'integer',
    ];

    /** Nome da opção de frete ("PAC", "SEDEX"); pedido antigo mostra o código cru. */
    public function shippingName(): ?string
    {
        if (blank($this->shipping_method)) {
            return null;
        }

        if ($this->shipping_method === \App\Services\FreteService::CODIGO_ISENTO) {
            return \App\Services\FreteService::NOME_ISENTO;
        }

        return config('frete.servicos.'.$this->shipping_method) ?? ucfirst($this->shipping_method);
    }

    /** "PAC · até 5 dias úteis" — o prazo só existe a partir da leva 08. */
    public function shippingLabel(): ?string
    {
        $nome = $this->shippingName();

        if ($nome === null) {
            return null;
        }

        return $this->shipping_days
            ? $nome.' · até '.$this->shipping_days.' '.($this->shipping_days === 1 ? 'dia útil' : 'dias úteis')
            : $nome;
    }

    /** Pagamento confirmado (cartão aprovado ou Pix/boleto compensado). */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /** Aguardando pagamento (Pix gerado / boleto emitido / cartão em análise). */
    public function isAwaitingPayment(): bool
    {
        return in_array($this->payment_status, ['pending', 'processing'], true);
    }

    /**
     * Cobrança de cartão com resultado indefinido (timeout/5xx): o pedido
     * fica pending até a reconciliação pela consulta da Appmax ou o webhook.
     */
    public function pagamentoEmVerificacao(): bool
    {
        return ! $this->isPaid()
            && $this->status !== 'cancelled'
            && (bool) ($this->payment_details['em_verificacao'] ?? false);
    }

    /** Incidentes de pagamento (ex.: aprovado depois de cancelado) — o admin vê no pedido. */
    public function incidentes(): array
    {
        return $this->payment_details['incidentes'] ?? [];
    }

    public function registrarIncidente(string $texto): void
    {
        $details = $this->payment_details ?? [];
        $details['incidentes'][] = ['em' => data_br(now()), 'texto' => $texto];

        $this->update(['payment_details' => $details]);
    }

    /** Mescla chaves em payment_details sem perder as existentes. */
    public function mesclarPaymentDetails(array $novos): void
    {
        $this->update(['payment_details' => array_merge($this->payment_details ?? [], $novos)]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
