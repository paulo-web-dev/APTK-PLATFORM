<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        // Vitrine de produtos (leva 06): o destaque marcado no admin vem
        // primeiro e os demais ativos entram no carrossel ("passar pro lado").
        $showcase = Product::where('active', true)
            ->with(['category', 'images'])
            ->orderByDesc('featured')
            ->orderBy('name')
            ->limit(8)
            ->get();

        // Dicas e Novidades (leva 06): os 3 últimos posts publicados.
        $latestPosts = \App\Models\Post::published()->limit(3)->get();

        return view('shop.home', compact('showcase', 'latestPosts'));
    }
}
