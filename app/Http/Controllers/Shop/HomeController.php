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
        // Leva 08: só produto COM foto — placeholder não entra no carrossel.
        // A foto antiga do site sai da galeria no import, então ter imagem
        // principal = ter foto do pacote novo (ou subida pelo admin).
        $showcase = Product::where('active', true)
            ->whereHas('primaryImage')
            ->with(['category', 'primaryImage'])
            ->orderByDesc('featured')
            ->orderBy('name')
            ->limit(8)
            ->get();

        // Dicas e Novidades (leva 06): os 3 últimos posts publicados.
        $latestPosts = \App\Models\Post::published()->limit(3)->get();

        return view('shop.home', compact('showcase', 'latestPosts'));
    }
}
