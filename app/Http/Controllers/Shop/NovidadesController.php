<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\View\View;

/** Dicas e Novidades — páginas públicas do mini-blog (leva 06). */
class NovidadesController extends Controller
{
    public function index(): View
    {
        $posts = Post::published()->paginate(9);

        return view('shop.novidades.index', compact('posts'));
    }

    public function show(string $slug): View
    {
        $post = Post::published()->where('slug', $slug)->firstOrFail();

        $related = Post::published()
            ->where('id', '!=', $post->id)
            ->limit(3)
            ->get();

        return view('shop.novidades.show', compact('post', 'related'));
    }
}
