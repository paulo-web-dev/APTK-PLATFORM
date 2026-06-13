<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $now = now();

        $ordersThisMonth = Order::whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        $revenueThisMonth = (float) Order::whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->sum('total');

        $avgTicket   = $ordersThisMonth > 0 ? $revenueThisMonth / $ordersThisMonth : 0.0;
        $totalOrders = Order::count();
        $activeProds = Product::where('active', true)->count();
        $totalProds  = Product::count();
        $customers   = User::where('role', 'customer')->count();
        $newThisWeek = User::where('role', 'customer')
            ->where('created_at', '>=', $now->copy()->startOfWeek())
            ->count();

        $recentOrders = Order::with('user')
            ->withCount('items')
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact(
            'ordersThisMonth', 'revenueThisMonth', 'avgTicket', 'totalOrders',
            'activeProds', 'totalProds', 'customers', 'newThisWeek', 'recentOrders'
        ));
    }
}
