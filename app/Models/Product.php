<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'short_description',
        'base',
        'abv',
        'sizes',
        'size_prices',
        'price',
        'compare_price',
        'sku',
        'stock_qty',
        'weight',
        'active',
        'featured',
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'compare_price' => 'decimal:2',
        'weight'        => 'decimal:3',
        'stock_qty'     => 'integer',
        'abv'           => 'integer',
        'sizes'         => 'array',
        'size_prices'   => 'array',
        'active'        => 'boolean',
        'featured'      => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    /* -----------------------------------------------------------------
     | Preço por volume
     | `sizes` define quais volumes existem; `size_prices` define o
     | preço de cada um. Volume sem preço próprio cai no `price` base.
     ------------------------------------------------------------------ */

    /** O produto tem mais de um volume à venda? */
    public function hasSizes(): bool
    {
        return is_array($this->sizes) && count($this->sizes) > 0;
    }

    /** Preço de um volume específico (ou o preço base). */
    public function priceForSize(?string $size): float
    {
        if ($size !== null && is_array($this->size_prices) && isset($this->size_prices[$size])) {
            return (float) $this->size_prices[$size];
        }

        return (float) $this->price;
    }

    /**
     * Opções de compra: [volume => preço], na ordem de `sizes`.
     * Sem volumes cadastrados, devolve [null => preço base].
     */
    public function sizeOptions(): array
    {
        if (! $this->hasSizes()) {
            return [];
        }

        $options = [];
        foreach ($this->sizes as $size) {
            $options[$size] = $this->priceForSize($size);
        }

        return $options;
    }

    /** Volume padrão pré-selecionado (o maior — normalmente o preço "cheio"). */
    public function defaultSize(): ?string
    {
        if (! $this->hasSizes()) {
            return null;
        }

        return $this->sizes[count($this->sizes) - 1];
    }
}
