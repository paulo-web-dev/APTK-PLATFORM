<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected OrderService $orders,
    ) {
    }

    public function index(): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index');
        }

        return view('shop.checkout', [
            'items' => $this->cart->items(),
            'total' => $this->cart->total(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'zipcode'         => ['required', 'string', 'max:9'],
            'street'          => ['required', 'string', 'max:255'],
            'number'          => ['required', 'string', 'max:20'],
            'complement'      => ['nullable', 'string', 'max:255'],
            'neighborhood'    => ['required', 'string', 'max:255'],
            'city'            => ['required', 'string', 'max:255'],
            'state'           => ['required', 'string', 'size:2'],
            'shipping_method' => ['nullable', 'string', 'max:50'],
            'payment_method'  => ['required', 'in:pix,cartao,boleto'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ]);

        $order = $this->orders->placeFromCart($request->user(), $data);

        return redirect()->route('checkout.success', $order);
    }

    public function success(Order $order): View
    {
        abort_unless($order->user_id === auth()->id(), 403);

        $order->load('items');

        return view('shop.checkout-success', compact('order'));
    }
}
