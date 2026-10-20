<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class CartService
{
    protected const KEY = 'cart';

    /*
     | Estrutura na sessão (v2, com volume):
     |   [ "12:750 ml" => ['product_id' => 12, 'size' => '750 ml', 'qty' => 2], ... ]
     | O identificador de linha é "productId:size" ("12:" quando sem volume).
     | Carrinhos antigos ([product_id => qty]) são migrados on-the-fly.
     |
     | Garrafa custom (leva 11): a linha leva 'personalizacao' ({bebida,
     | texto, fonte, cor}) e a chave ganha ":c{hash}" — mesma bebida com
     | texto diferente vira outra linha. Preço da linha = config custom.preco.
     */
    protected function raw(): array
    {
        return $this->migrate(Session::get(self::KEY, []));
    }

    protected function save(array $items): void
    {
        Session::put(self::KEY, $items);
    }

    /** Migra o formato antigo [product_id => qty] para o formato com volume. */
    protected function migrate(array $items): array
    {
        $out = [];

        foreach ($items as $key => $value) {
            if (is_array($value) && isset($value['product_id'])) {
                $out[$key] = $value; // já está no formato novo
                continue;
            }

            // formato antigo: chave = product_id, valor = qty (sem volume)
            $out[$this->lineKey((int) $key, null)] = [
                'product_id' => (int) $key,
                'size'       => null,
                'qty'        => (int) $value,
            ];
        }

        return $out;
    }

    /** Identificador único da linha do carrinho (produto + volume [+ personalização]). */
    public function lineKey(int $productId, ?string $size, ?array $personalizacao = null): string
    {
        $key = $productId.':'.($size ?? '');

        return $personalizacao ? $key.':c'.app(CustomService::class)->hash($personalizacao) : $key;
    }

    /** Linha crua do carrinho (product_id, size, qty) ou null se não existe. */
    public function line(string $key): ?array
    {
        return $this->raw()[$key] ?? null;
    }

    /**
     * Quantidade de um produto + volume somando TODAS as linhas (a 375
     * normal e as custom da mesma bebida dividem o mesmo estoque).
     */
    public function qtyDoVolume(int $productId, ?string $size): int
    {
        return (int) collect($this->raw())
            ->filter(fn (array $l) => (int) $l['product_id'] === $productId && ($l['size'] ?? null) === $size)
            ->sum('qty');
    }

    public function add(int $productId, int $qty = 1, ?string $size = null, ?array $personalizacao = null): void
    {
        $items = $this->raw();
        $key   = $this->lineKey($productId, $size, $personalizacao);

        $items[$key] = array_filter([
            'product_id'     => $productId,
            'size'           => $size,
            'qty'            => ($items[$key]['qty'] ?? 0) + max(1, $qty),
            'personalizacao' => $personalizacao,
        ], fn ($v, $k) => $k !== 'personalizacao' || $v !== null, ARRAY_FILTER_USE_BOTH);

        $this->save($items);
    }

    public function update(string $key, int $qty): void
    {
        $items = $this->raw();

        if (! isset($items[$key])) {
            return;
        }

        if ($qty <= 0) {
            unset($items[$key]);
        } else {
            $items[$key]['qty'] = $qty;
        }

        $this->save($items);
    }

    public function remove(string $key): void
    {
        $items = $this->raw();
        unset($items[$key]);
        $this->save($items);
    }

    public function clear(): void
    {
        Session::forget(self::KEY);
    }

    /** Soma das quantidades (pro selo do header). */
    public function count(): int
    {
        return array_sum(array_column($this->raw(), 'qty'));
    }

    public function isEmpty(): bool
    {
        return empty($this->raw());
    }

    /**
     * Itens enriquecidos: UMA query e uma coleção de objetos com
     * key, product, size, qty, unit_price (do volume, ou o preço fixo do
     * custom), subtotal e personalizacao (null na linha normal).
     * Produtos inexistentes são ignorados.
     */
    public function items(): Collection
    {
        $raw = $this->raw();

        if (empty($raw)) {
            return collect();
        }

        $products = Product::whereIn('id', array_unique(array_column($raw, 'product_id')))
            ->with('primaryImage') // miniatura no carrinho e no checkout
            ->get()
            ->keyBy('id');

        return collect($raw)
            ->map(function (array $line, string $key) use ($products) {
                $product = $products->get($line['product_id']);

                if (! $product) {
                    return null;
                }

                $personalizacao = $line['personalizacao'] ?? null;
                $unit = $personalizacao
                    ? app(CustomService::class)->preco()
                    : $product->priceForSize($line['size']);

                return (object) [
                    'key'            => $key,
                    'product'        => $product,
                    'size'           => $line['size'],
                    'qty'            => $line['qty'],
                    'unit_price'     => $unit,
                    'subtotal'       => $unit * $line['qty'],
                    'personalizacao' => $personalizacao,
                ];
            })
            ->filter()
            ->values();
    }

    public function total(): float
    {
        return (float) $this->items()->sum('subtotal');
    }
}
