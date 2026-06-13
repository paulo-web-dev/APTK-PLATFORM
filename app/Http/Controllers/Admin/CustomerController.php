<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $customers = User::where('role', 'customer')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->withCount('orders')
            ->withSum('orders', 'total')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.customers.index', compact('customers', 'search'));
    }

    public function show(User $user): View
    {
        abort_unless($user->role === 'customer', 404);

        $orders = $user->orders()
            ->withCount('items')
            ->latest()
            ->paginate(15);

        $stats = [
            'orders'   => $user->orders()->count(),
            'total'    => (float) $user->orders()->sum('total'),
            'lastDate' => optional($user->orders()->latest()->first())->created_at,
        ];

        return view('admin.customers.show', compact('user', 'orders', 'stats'));
    }
}
