<?php

namespace App\Services\Microvix;

use App\Models\MicrovixProduct;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Sugere vínculos entre o espelho do ERP (microvix_products) e o catálogo do
 * site (product + size). Deliberadamente enxuto — são ~22 combinações, a tela
 * resolve o resto: dois critérios, sem similaridade de nome, sem ação em massa.
 *
 * Sugestão NUNCA vira vínculo sozinha: quem grava é o admin na tela.
 */
class MicrovixMatcher
{
    /**
     * @param  Collection<int, Product>  $products  catálogo carregado uma vez pelo chamador
     * @return array{product: Product, size: ?string, criterio: string, confianca: string}|null
     */
    public function sugerir(MicrovixProduct $mp, Collection $products): ?array
    {
        return $this->porSku($mp, $products) ?? $this->porReferencia($mp, $products);
    }

    /**
     * codauxiliar1 do ERP casando com o sku do site pela base antes do hífen
     * (ex.: COSMO-375 → sku COSMO-750, volume 375 ml). O sufixo numérico do
     * sku do site não codifica volume de verdade, então só a base conta.
     */
    protected function porSku(MicrovixProduct $mp, Collection $products): ?array
    {
        if (! $mp->codauxiliar1) {
            return null;
        }

        $base = strtok($mp->codauxiliar1, '-');
        $candidatos = $products->filter(
            fn (Product $p) => $p->sku && strcasecmp(strtok($p->sku, '-'), $base) === 0,
        );

        if ($candidatos->count() !== 1) {
            return null;
        }

        $product = $candidatos->first();
        $size = $this->sizePorDigitos($product, preg_replace('/\D/', '', $mp->codauxiliar1))
            ?? $this->sizeUnico($product);

        return $size === false ? null : [
            'product'   => $product,
            'size'      => $size,
            'criterio'  => 'sku',
            'confianca' => 'alta',
        ];
    }

    /**
     * referencia do ERP casando com a primeira palavra do nome do produto.
     * Ambiguidade (ex.: SQUADRA casa com dois produtos) é desfeita procurando
     * o nome completo do produto dentro do nome_produto do ERP; se ainda
     * sobrar mais de um, não há sugestão — decisão vai para o humano.
     */
    protected function porReferencia(MicrovixProduct $mp, Collection $products): ?array
    {
        if (! $mp->referencia) {
            return null;
        }

        $candidatos = $products->filter(
            fn (Product $p) => strcasecmp(strtok($p->name, ' '), $mp->referencia) === 0,
        );

        if ($candidatos->count() > 1 && $mp->nome_produto) {
            $nomeErp = mb_strtoupper($mp->nome_produto);
            $candidatos = $candidatos->filter(
                fn (Product $p) => str_contains($nomeErp, mb_strtoupper($p->name)),
            );
        }

        if ($candidatos->count() !== 1) {
            return null;
        }

        $product = $candidatos->first();
        $size = $this->sizePorDigitos($product, $this->digitosDoNomeErp($mp))
            ?? $this->sizeUnico($product);

        return $size === false ? null : [
            'product'   => $product,
            'size'      => $size,
            'criterio'  => 'referência',
            'confianca' => 'média',
        ];
    }

    /** Volume do produto cujos dígitos batem (ex.: "375" → "375 ml"). */
    protected function sizePorDigitos(Product $product, string $digitos): ?string
    {
        if ($digitos === '') {
            return null;
        }

        foreach ($product->sizes ?? [] as $size) {
            if (preg_replace('/\D/', '', $size) === $digitos) {
                return $size;
            }
        }

        return null;
    }

    /**
     * Sem dígitos para casar: só dá para sugerir volume se o produto tem UM.
     * Retorna false (não null) quando é ambíguo, para o chamador descartar
     * a sugestão inteira em vez de sugerir produto sem volume.
     */
    protected function sizeUnico(Product $product): string|false|null
    {
        return match (count($product->sizes ?? [])) {
            0       => null,
            1       => $product->sizes[0],
            default => false,
        };
    }

    /** Volume embutido no nome do ERP (ex.: "APTK NEGRONI 375ML" → "375"). */
    protected function digitosDoNomeErp(MicrovixProduct $mp): string
    {
        return preg_match('/(\d{2,4})\s*ML\b/iu', (string) $mp->nome_produto, $m) ? $m[1] : '';
    }
}
