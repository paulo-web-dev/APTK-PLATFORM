<?php

namespace App\Models;

use App\Services\CustomService;
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
        // Garrafa custom (leva 11): {bebida, texto, fonte, cor} como o cliente
        // enviou + bebida_nome (nome exibido no Custom na hora do pedido).
        'personalizacao' => 'array',
    ];

    public function isCustom(): bool
    {
        return ! empty($this->personalizacao);
    }

    /** Nome pra tela: na custom, o nome da bebida no Custom; senão o do produto. */
    public function nomeExibido(): string
    {
        if (! $this->isCustom()) {
            return $this->product_name;
        }

        return app(CustomService::class)->exibir($this->personalizacao)['bebida_nome'] ?? $this->product_name;
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
