<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'path',
        'alt',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Subida pelo admin (FotoUpload, ou store() do Laravel nas antigas: nome
     * aleatório de 40 caracteres).
     * Só esse arquivo o admin apaga do disco — foto do pacote (import) e foto
     * antiga do site não têm como ser regeradas em produção.
     */
    public function isUploadDoAdmin(): bool
    {
        return (bool) preg_match('#^products/[A-Za-z0-9]{40}\.[A-Za-z0-9]+$#', $this->path);
    }
}
