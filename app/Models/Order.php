<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
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
    ];

    protected $casts = [
        'subtotal'        => 'decimal:2',
        'discount'        => 'decimal:2',
        'shipping_cost'   => 'decimal:2',
        'total'           => 'decimal:2',
        'payment_details' => 'array',
        'paid_at'         => 'datetime',
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
