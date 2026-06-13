<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class CartService
{
    protected const KEY = 'cart';

    /** Estrutura crua na sessão: [product_id => qty] */
    protected function raw(): array
    {
        return Session::get(self::KEY, []);
    }

    protected function save(array $items): void
    {
        Session::put(self::KEY, $items);
    }

    public function add(int $productId, int $qty = 1): void
    {
        $items = $this->raw();
        $items[$productId] = ($items[$productId] ?? 0) + max(1, $qty);
        $this->save($items);
    }

    public function update(int $productId, int $qty): void
    {
        $items = $this->raw();

        if ($qty <= 0) {
            unset($items[$productId]);
        } else {
            $items[$productId] = $qty;
        }

        $this->save($items);
    }

    public function remove(int $productId): void
    {
        $items = $this->raw();
        unset($items[$productId]);
        $this->save($items);
    }

    public function clear(): void
    {
        Session::forget(self::KEY);
    }

    /** Soma das quantidades (pro selo do header) */
    public function count(): int
    {
        return array_sum($this->raw());
    }

    public function isEmpty(): bool
    {
        return empty($this->raw());
    }

    /**
     * Itens enriquecidos: faz UMA query e devolve uma coleção de objetos
     * com product, qty e subtotal de linha. Produtos inexistentes são ignorados.
     */
    public function items(): Collection
    {
        $raw = $this->raw();

        if (empty($raw)) {
            return collect();
        }

        return Product::whereIn('id', array_keys($raw))
            ->get()
            ->map(fn (Product $product) => (object) [
                'product'  => $product,
                'qty'      => $raw[$product->id],
                'subtotal' => $product->price * $raw[$product->id],
            ])
            ->values();
    }

    public function total(): float
    {
        return (float) $this->items()->sum('subtotal');
    }
}
