<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    private const STATUSES = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];

    public function index(Request $request): View
    {
        $active = $request->query('status');

        $query = Order::with('user')->withCount('items')->latest();

        if (in_array($active, self::STATUSES, true)) {
            $query->where('status', $active);
        }

        $orders = $query->paginate(20)->withQueryString();

        return view('admin.orders.index', [
            'orders'   => $orders,
            'statuses' => self::STATUSES,
            'active'   => $active,
        ]);
    }

    public function show(Order $order): View
    {
        $order->load(['user', 'items']);

        return view('admin.orders.show', [
            'order'    => $order,
            'statuses' => self::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::STATUSES)],
        ]);

        $order->status = $data['status'];

        if ($data['status'] === 'paid') {
            $order->payment_status = 'paid';
        } elseif ($data['status'] === 'cancelled') {
            $order->payment_status = 'failed';
        }

        $order->save();

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'Status do pedido atualizado.');
    }
}
