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
        'size_stock',
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
        // Estoque por volume (Microvix: cada volume é um SKU com saldo próprio).
        // Só a coluna existe por enquanto — a leitura de estoque não a usa ainda.
        'size_stock'    => 'array',
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

    /** SKUs do ERP vinculados a este produto (um por volume). */
    public function microvixProducts(): HasMany
    {
        return $this->hasMany(MicrovixProduct::class);
    }

    /**
     * Preço e estoque deste produto são do ERP? Só quando a fonte da verdade
     * é o ERP (MICROVIX_FONTE_VERDADE=erp) E há vínculo confirmado — os dois
     * juntos: a virada da chave sozinha não trava produto sem vínculo, e o
     * vínculo sozinho não trava nada antes da virada.
     */
    public function managedByErp(): bool
    {
        if (config('microvix.fonte_da_verdade') !== 'erp') {
            return false;
        }

        // Usa o withCount quando o chamador carregou (listas), senão consulta.
        return $this->microvix_products_count !== null
            ? $this->microvix_products_count > 0
            : $this->microvixProducts()->exists();
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

    /* -----------------------------------------------------------------
     | Estoque por volume (Microvix fase 1.5)
     | `size_stock` só é preenchido pela conciliação com o ERP. O fallback
     | é CONSERVADOR de propósito: produto (ou volume) sem entrada em
     | size_stock se comporta EXATAMENTE como antes — stock_qty agregado,
     | que hoje é placeholder e não limita nada. Nenhum produto não
     | conciliado passa a esgotar por causa deste bloco.
     ------------------------------------------------------------------ */

    /** Este produto tem estoque rastreado por volume? */
    public function tracksSizeStock(): bool
    {
        return is_array($this->size_stock) && $this->size_stock !== [];
    }

    /**
     * Saldo vendável de um volume. null = volume não rastreado: sem limite
     * novo (comportamento de sempre). Nunca confundir null com 0.
     */
    public function stockForSize(?string $size): ?int
    {
        if (! $this->tracksSizeStock() || ! isset($this->size_stock[$size])) {
            return null;
        }

        return (int) $this->size_stock[$size];
    }

    /** Volume rastreado E zerado. Volume não rastreado nunca está esgotado. */
    public function isSizeOutOfStock(?string $size): bool
    {
        $saldo = $this->stockForSize($size);

        return $saldo !== null && $saldo <= 0;
    }

    /**
     * Esgotado para a vitrine. Sem rastreio por volume, é a regra de sempre
     * (stock_qty agregado); com rastreio, só quando TODOS os volumes
     * rastreados zeraram — um volume sem entrada ainda conta como vendável.
     */
    public function isSoldOut(): bool
    {
        if (! $this->tracksSizeStock()) {
            return (int) $this->stock_qty <= 0;
        }

        foreach ($this->hasSizes() ? $this->sizes : [null] as $size) {
            if (! $this->isSizeOutOfStock($size)) {
                return false;
            }
        }

        return true;
    }

    /** Volume pré-selecionado na página: o padrão, a menos que esteja esgotado. */
    public function defaultAvailableSize(): ?string
    {
        if (! $this->hasSizes()) {
            return null;
        }

        $default = $this->defaultSize();
        if (! $this->isSizeOutOfStock($default)) {
            return $default;
        }

        foreach (array_reverse($this->sizes) as $size) {
            if (! $this->isSizeOutOfStock($size)) {
                return $size;
            }
        }

        return $default;
    }
}
