<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

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

    /**
     * Menor preço à venda: o menor entre os volumes; sem volumes, o preço
     * base. null = produto sem preço (não pode ficar ativo — leva 08).
     */
    public function menorPreco(): ?float
    {
        $precos = $this->hasSizes() ? array_values($this->sizeOptions()) : [(float) $this->price];
        $precos = array_filter($precos, fn ($v) => $v > 0);

        return $precos ? (float) min($precos) : null;
    }

    public function temPreco(): bool
    {
        return $this->menorPreco() !== null;
    }

    /* -----------------------------------------------------------------
     | Ativação
     | Ativo = tem preço E tem foto no padrão. Regra única do admin e do
     | produtos:aplicar-fotos (o importador da planilha não ativa nada).
     ------------------------------------------------------------------ */

    /**
     * Foto no padrão: foto do manifesto fundo branco (foto-branco-{chave}-{lado}.jpg,
     * rótulo atual) ou upload do admin. O pacote fundo preto
     * ({grupo}-{N}ml-{lado}.jpg, foto-preto-*) tem rótulo antigo e não conta
     * mais, nem foto antiga do site ({slug}.jpg).
     */
    public static function ehFotoNoPadrao(string $path): bool
    {
        return (bool) preg_match('#^products/foto-branco-[a-z0-9-]+-(frente|verso)\.jpg$#', $path)
            || (new ProductImage(['path' => $path]))->isUploadDoAdmin();
    }

    /** @param  array|null  $galeria  paths a considerar (padrão: a galeria gravada) */
    public function temFotoNoPadrao(?array $galeria = null): bool
    {
        $galeria ??= $this->images->pluck('path')->all();

        foreach ($galeria as $path) {
            if (self::ehFotoNoPadrao($path)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array|null  $galeria  paths a considerar (padrão: a galeria gravada) */
    public function podeAtivar(?array $galeria = null): bool
    {
        return $this->temPreco() && $this->temFotoNoPadrao($galeria);
    }

    /* -----------------------------------------------------------------
     | Foto por volume (leva 18)
     | product_images.volume: null = geral (garrafa grande, vale pra todo
     | volume); "375 ml" = só daquela garrafa. Volume escolhido sem foto
     | própria cai nas gerais. is_primary é a principal das gerais.
     ------------------------------------------------------------------ */

    /** Fotos a mostrar pra um volume: as dele, senão as gerais. */
    public function fotosDoVolume(?string $volume): Collection
    {
        if ($volume !== null) {
            $doVolume = $this->images->where('volume', $volume)->values();
            if ($doVolume->isNotEmpty()) {
                return $doVolume;
            }
        }

        $gerais = $this->images->whereNull('volume')->values();
        if ($gerais->isNotEmpty()) {
            return $gerais;
        }

        // Sem geral (o admin não deixa, mas dado antigo pode ter): qualquer
        // foto de volume à venda antes do placeholder.
        return $this->images->filter(fn ($i) => $this->hasSizes() && in_array($i->volume, $this->sizes, true))->values();
    }

    public function fotoDoVolume(?string $volume): ?ProductImage
    {
        return $this->fotosDoVolume($volume)->first();
    }

    /** Foto de card (loja, Home, relacionados): a do volume padrão. */
    public function fotoPadrao(): ?ProductImage
    {
        return $this->fotoDoVolume($this->defaultSize());
    }

    /** Renumera a galeria com as gerais primeiro; a primeira geral é a principal. */
    public function renumerarGaleria(): void
    {
        $this->images()->get() // images() já ordena por sort_order
            ->sortBy(fn (ProductImage $img) => [$img->volume !== null, $img->sort_order, $img->id])
            ->values()
            ->each(fn (ProductImage $img, int $i) => $img->update(['sort_order' => $i, 'is_primary' => $i === 0]));

        $this->unsetRelation('images');
    }

    /** Volumes que podem ter foto própria: os à venda, menos o padrão (o padrão usa as gerais). */
    public function volumesComFotoPropria(): array
    {
        return $this->hasSizes() ? array_values(array_diff($this->sizes, [$this->defaultSize()])) : [];
    }

    /**
     * Marcação de volume das fotos, a partir do volume da garrafa em cada uma
     * (null = não se sabe, ex. upload do admin). Só marca volume com foto
     * própria; se nenhuma sobrar geral (ex.: só foto da 375 de produto que
     * vende 750), todas ficam gerais até chegar a da grande.
     *
     * @param  array<string, ?string>  $garrafa  path => volume da garrafa
     * @return array<string, ?string> path => volume a gravar (null = geral)
     */
    public function marcarVolumes(array $garrafa): array
    {
        $proprios = $this->volumesComFotoPropria();
        $marca = array_map(fn ($v) => in_array($v, $proprios, true) ? $v : null, $garrafa);

        return in_array(null, $marca, true) ? $marca : array_fill_keys(array_keys($marca), null);
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
