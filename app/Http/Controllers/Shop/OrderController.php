<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        $orders = auth()->user()->orders()
            ->withCount('items')
            ->latest()
            ->paginate(10);

        return view('shop.orders.index', compact('orders'));
    }

    public function show(Order $order): View
    {
        // Só o dono enxerga o pedido (404 não revela existência).
        abort_unless($order->user_id === auth()->id(), 404);

        $order->load('items');

        return view('shop.orders.show', compact('order'));
    }
}
