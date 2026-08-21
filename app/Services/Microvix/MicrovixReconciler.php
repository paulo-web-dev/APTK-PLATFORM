<?php

namespace App\Services\Microvix;

use App\Exceptions\MicrovixException;
use App\Models\MicrovixProduct;
use App\Models\MicrovixReconcileLog;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Conciliação ERP → catálogo do site, só para vínculos confirmados:
 *   saldo      → products.size_stock[volume]
 *   precovenda → products.size_prices[volume]
 *
 * NUNCA toca em nome, slug, descrição, imagem, destaque ou active — o ERP
 * manda em preço e estoque, o site é dono do editorial, e produto com tudo
 * zerado fica SEM ESTOQUE, não inativo (active é decisão do time).
 *
 * Registro do ERP sem vínculo é ignorado: não cria produto no site.
 * Volume do site sem espelho no ERP mantém o último valor e vira aviso.
 */
class MicrovixReconciler
{
    /**
     * Calcula o que a conciliação faria, sem gravar nada.
     *
     * @return array{
     *     mudancas: array<int, array{codigoproduto: int, product: Product, size: ?string,
     *         campo: string, de: ?float, para: float, exibido_hoje: ?float, sem_size_prices: bool}>,
     *     avisos: string[],
     * }
     */
    public function plano(): array
    {
        $mudancas = [];
        $avisos = [];

        $vinculados = MicrovixProduct::whereNotNull('product_id')
            ->with('product')
            ->orderBy('codigoproduto')
            ->get();

        foreach ($vinculados as $mp) {
            $product = $mp->product;

            if (! $product) {
                $avisos[] = "Código {$mp->codigoproduto} vinculado ao produto #{$mp->product_id}, que não existe mais — vínculo órfão, resolver na tela de vínculo.";

                continue;
            }

            if ($mp->ativo === false) {
                $avisos[] = "Código {$mp->codigoproduto} ({$product->name}".($mp->size ? " — {$mp->size}" : '').") está INATIVO no ERP — saldo/preço ainda são aplicados; o active do site não muda.";
            }

            if ($mp->saldo !== null) {
                $de = isset($product->size_stock[$mp->size]) ? (float) $product->size_stock[$mp->size] : null;
                // Estoque vendável é inteiro: fração do ERP arredonda para baixo.
                $para = (float) floor((float) $mp->saldo);

                if ($de === null || $de !== $para) {
                    $mudancas[] = [
                        'codigoproduto'   => (int) $mp->codigoproduto,
                        'product'         => $product,
                        'size'            => $mp->size,
                        'campo'           => 'saldo',
                        'de'              => $de,
                        'para'            => $para,
                        'exibido_hoje'    => null,
                        'sem_size_prices' => false,
                    ];
                }
            }

            if ($mp->preco !== null) {
                $semSizePrices = ! isset($product->size_prices[$mp->size]);
                $de = $semSizePrices ? null : round((float) $product->size_prices[$mp->size], 2);
                $para = round((float) $mp->preco, 2);

                if ($de === null || $de !== $para) {
                    $mudancas[] = [
                        'codigoproduto'   => (int) $mp->codigoproduto,
                        'product'         => $product,
                        'size'            => $mp->size,
                        'campo'           => 'preco',
                        'de'              => $de,
                        // O que o cliente vê HOJE nesse volume (cai no price
                        // base quando não há size_prices) — é esse valor que
                        // muda no ato da primeira gravação.
                        'exibido_hoje'    => round($product->priceForSize($mp->size), 2),
                        'para'            => $para,
                        'sem_size_prices' => $semSizePrices,
                    ];
                }
            }
        }

        // Volumes do site sem espelho próprio no ERP: mantêm o último valor
        // conhecido — sinalizados aqui em vez de zerados em silêncio.
        $cobertos = $vinculados->groupBy('product_id')
            ->map(fn ($grupo) => $grupo->pluck('size')->all());

        foreach ($cobertos as $productId => $sizes) {
            $product = $vinculados->firstWhere('product_id', $productId)?->product;
            foreach ($product?->sizes ?? [] as $size) {
                if (! in_array($size, $sizes, true)) {
                    $avisos[] = "{$product->name} — {$size} não tem espelho no ERP: mantém o valor atual do site.";
                }
            }
        }

        return ['mudancas' => $mudancas, 'avisos' => $avisos];
    }

    /**
     * Grava o plano no catálogo e registra cada alteração (de → para) em
     * microvix_reconcile_log.
     *
     * Trava de segurança: com MICROVIX_FONTE_VERDADE=site é IMPOSSÍVEL gravar
     * — a exceção está aqui no service, não só no comando, para valer também
     * para o painel e qualquer chamador futuro.
     */
    public function executar(): array
    {
        if (config('microvix.fonte_da_verdade') !== 'erp') {
            throw new MicrovixException(
                'Conciliação só grava com MICROVIX_FONTE_VERDADE=erp — hoje a fonte da verdade é o site.',
            );
        }

        $plano = $this->plano();

        DB::transaction(function () use ($plano) {
            /** @var array<int, Product> $paraSalvar */
            $paraSalvar = [];

            foreach ($plano['mudancas'] as $m) {
                $product = $paraSalvar[$m['product']->id] ??= $m['product'];

                $coluna = $m['campo'] === 'saldo' ? 'size_stock' : 'size_prices';
                $valores = $product->{$coluna} ?? [];
                $valores[$m['size']] = $m['campo'] === 'saldo' ? (int) $m['para'] : $m['para'];
                $product->{$coluna} = $valores;

                MicrovixReconcileLog::create([
                    'product_id'    => $product->id,
                    'size'          => $m['size'],
                    'codigoproduto' => $m['codigoproduto'],
                    'campo'         => $m['campo'],
                    'de'            => $m['de'],
                    'para'          => $m['para'],
                ]);
            }

            foreach ($paraSalvar as $product) {
                $product->save();
            }
        });

        return $plano;
    }
}
