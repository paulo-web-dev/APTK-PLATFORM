<?php

namespace App\Models;

use App\Services\FundoFoto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'path',
        'fundo',
        'alt',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Cor de fundo da foto (FundoFoto) calculada ao gravar: upload do admin,
     * produtos:aplicar-fotos e reversão passam todos por aqui. Path novo
     * recalcula; fundo informado explicitamente prevalece.
     */
    protected static function booted(): void
    {
        static::saving(function (ProductImage $img) {
            if (! $img->isDirty('fundo') && ($img->isDirty('path') || $img->fundo === null)) {
                $img->fundo = FundoFoto::doDisco($img->path);
            }
        });
    }

    /** Fundo seguro pra ir no style (null = fallback --color-bg-media). */
    public function corFundo(): ?string
    {
        return FundoFoto::valida($this->fundo);
    }

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
