<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'size',
        'qty',
        'unit_price',
        'total_price',
        'personalizacao',
    ];

    protected $casts = [
        'qty'         => 'integer',
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
        // Garrafa custom (leva 11): {bebida, texto, fonte, cor} como o cliente enviou.
        'personalizacao' => 'array',
    ];

    public function isCustom(): bool
    {
        return ! empty($this->personalizacao);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
