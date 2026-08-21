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

        $product = Product::findOrFail($data['product_id']);

        // O volume precisa existir no cadastro do produto (evita preço forjado).
        $size = $data['size'] ?? null;
        if ($size !== null && (! is_array($product->sizes) || ! in_array($size, $product->sizes, true))) {
            $size = null;
        }

        // Estoque por volume: o carrinho não passa do saldo. Volume não
        // rastreado (saldo null) segue sem limite, como sempre foi.
        $saldo = $product->stockForSize($size);
        if ($saldo !== null) {
            $rotulo = '"'.$product->name.($size ? " ({$size})" : '').'"';
            $noCarrinho = (int) ($this->cart->line($this->cart->lineKey($product->id, $size))['qty'] ?? 0);

            if ($saldo <= 0) {
                return back()->with('stock_error', "{$rotulo} está esgotado.");
            }

            if ($noCarrinho + ($data['qty'] ?? 1) > $saldo) {
                return redirect()->route('cart.index')->with(
                    'stock_error',
                    "Só temos {$saldo} unidade(s) de {$rotulo}".($noCarrinho > 0 ? " e você já tem {$noCarrinho} no carrinho" : '').'.',
                );
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

        // Revalida contra o saldo do volume ao alterar quantidade.
        $line = $this->cart->line($data['key']);
        if ($line && $data['qty'] > 0) {
            $product = Product::find($line['product_id']);
            $saldo = $product?->stockForSize($line['size']);

            if ($saldo !== null && $data['qty'] > $saldo) {
                $rotulo = '"'.$product->name.($line['size'] ? " ({$line['size']})" : '').'"';

                return redirect()->route('cart.index')->with(
                    'stock_error',
                    "Só temos {$saldo} unidade(s) de {$rotulo} — quantidade mantida em {$line['qty']}.",
                );
            }
        }

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
