<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    /** Abaixo ou igual a este valor o item é considerado "estoque baixo". */
    private const LOW = 5;

    public function index(Request $request): View
    {
        $filter = $request->query('filtro'); // baixo | esgotado | null

        $products = Product::with('category')
            ->when($filter === 'baixo', fn ($q) => $q->where('stock_qty', '>', 0)->where('stock_qty', '<=', self::LOW))
            ->when($filter === 'esgotado', fn ($q) => $q->where('stock_qty', '<=', 0))
            ->orderBy('stock_qty')
            ->paginate(30)
            ->withQueryString();

        $stats = [
            'skus'     => Product::count(),
            'baixo'    => Product::where('stock_qty', '>', 0)->where('stock_qty', '<=', self::LOW)->count(),
            'esgotado' => Product::where('stock_qty', '<=', 0)->count(),
            'unidades' => (int) Product::sum('stock_qty'),
        ];

        return view('admin.stock.index', [
            'products' => $products,
            'stats'    => $stats,
            'low'      => self::LOW,
            'filter'   => $filter,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'stock_qty' => ['required', 'integer', 'min:0'],
        ]);

        $product->update($data);

        return back()->with('status', "Estoque de \"{$product->name}\" atualizado para {$data['stock_qty']}.");
    }
}
