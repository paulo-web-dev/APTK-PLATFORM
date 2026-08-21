<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma alteração que a conciliação gravou no catálogo: qual produto+volume,
 * qual campo, de quanto para quanto. Dry-run não gera registro aqui.
 */
class MicrovixReconcileLog extends Model
{
    protected $table = 'microvix_reconcile_log';

    protected $fillable = [
        'product_id',
        'size',
        'codigoproduto',
        'campo',
        'de',
        'para',
    ];

    protected $casts = [
        'codigoproduto' => 'integer',
        'de'            => 'decimal:3',
        'para'          => 'decimal:3',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
