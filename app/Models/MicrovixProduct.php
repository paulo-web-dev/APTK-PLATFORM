<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Espelho de um SKU do Microvix ERP (cada volume é um codigoproduto próprio).
 * O vínculo com o nosso catálogo (product_id + size) é feito MANUALMENTE.
 */
class MicrovixProduct extends Model
{
    protected $fillable = [
        'codigoproduto',
        'product_id',
        'size',
        'referencia',
        'codauxiliar1',
        'nome_produto',
        'saldo',
        'preco',
        'ativo',
        'raw',
        'synced_at',
    ];

    protected $casts = [
        'codigoproduto' => 'integer',
        'saldo'         => 'decimal:3',
        'preco'         => 'decimal:2',
        'ativo'         => 'boolean',
        'raw'           => 'array',
        'synced_at'     => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
