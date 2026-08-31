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
    ];

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
