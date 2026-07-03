<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(protected CartService $cart)
    {
    }

    public function index(): View
    {
        return view('shop.cart', [
            'items' => $this->cart->items(),
            'total' => $this->cart->total(),
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'qty'        => ['nullable', 'integer', 'min:1'],
            'size'       => ['nullable', 'string', 'max:30'],
        ]);

        // O volume precisa existir no cadastro do produto (evita preço forjado).
        $size = $data['size'] ?? null;
        if ($size !== null) {
            $product = Product::findOrFail($data['product_id']);
            if (! is_array($product->sizes) || ! in_array($size, $product->sizes, true)) {
                $size = null;
            }
        }

        $this->cart->add($data['product_id'], $data['qty'] ?? 1, $size);

        return redirect()
            ->route('cart.index')
            ->with('status', 'Produto adicionado ao carrinho.');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:60'],
            'qty' => ['required', 'integer', 'min:0'],
        ]);

        $this->cart->update($data['key'], $data['qty']);

        return redirect()->route('cart.index');
    }

    public function remove(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:60'],
        ]);

        $this->cart->remove($data['key']);

        return redirect()->route('cart.index');
    }
}
