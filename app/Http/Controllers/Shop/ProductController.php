<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $categories = ProductCategory::where('active', true)
            ->orderBy('sort_order')
            ->get();

        $query = Product::where('active', true)
            ->with(['primaryImage', 'category']);

        // Filtro por categoria via ?categoria=slug
        $activeCategory = null;
        if ($request->filled('categoria')) {
            $activeCategory = $categories->firstWhere('slug', $request->query('categoria'));
            if ($activeCategory) {
                $query->where('category_id', $activeCategory->id);
            }
        }

        $products = $query->orderByDesc('featured')
            ->orderBy('name')
            ->paginate(9)
            ->withQueryString();

        return view('shop.catalog', compact('categories', 'products', 'activeCategory'));
    }

    public function show(string $slug): View
    {
        $product = Product::where('slug', $slug)
            ->where('active', true)
            ->with(['category', 'images'])
            ->firstOrFail();

        $related = Product::where('active', true)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with('primaryImage')
            ->limit(3)
            ->get();

        return view('shop.product', compact('product', 'related'));
    }
}
