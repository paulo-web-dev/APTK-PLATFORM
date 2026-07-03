<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        // Produto em destaque da home (banner com seletor de volume).
        // Marcar "featured" no admin controla o que aparece aqui.
        $featured = Product::where('active', true)
            ->where('featured', true)
            ->with(['primaryImage', 'category'])
            ->orderBy('name')
            ->first();

        return view('shop.home', compact('featured'));
    }
}
