<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\CustomController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\PageController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Loja pública
|--------------------------------------------------------------------------
| "Produtos" no menu → rota /loja mantida (decisão leva 01 do feedback).
*/
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/loja', [ProductController::class, 'index'])->name('catalog');
Route::get('/produto/{slug}', [ProductController::class, 'show'])->name('product');

/*
|--------------------------------------------------------------------------
| Custom (antiga Customização) — página + pedido do Custom Simples
|--------------------------------------------------------------------------
*/
Route::get('/custom', [CustomController::class, 'show'])->name('custom');
Route::post('/custom/pedido', [CustomController::class, 'store'])->name('custom.store');

/*
|--------------------------------------------------------------------------
| Carrinho (sessão) — linhas identificadas por produto + volume ("key")
|--------------------------------------------------------------------------
*/
Route::get('/carrinho', [CartController::class, 'index'])->name('cart.index');
Route::post('/carrinho/adicionar', [CartController::class, 'add'])->name('cart.add');
Route::patch('/carrinho/atualizar', [CartController::class, 'update'])->name('cart.update');
Route::delete('/carrinho/remover', [CartController::class, 'remove'])->name('cart.remove');

/*
|--------------------------------------------------------------------------
| Newsletter (formulário do rodapé) → PageController@newsletter
|--------------------------------------------------------------------------
*/
Route::post('/newsletter', [PageController::class, 'newsletter'])->name('newsletter');

/*
|--------------------------------------------------------------------------
| Área logada (Breeze) + Checkout + Meus pedidos
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Perfil (Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Checkout (exige login: pedido precisa de user_id)
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/pedido/{order}/confirmado', [CheckoutController::class, 'success'])->name('checkout.success');

    // Meus pedidos (histórico do cliente)
    Route::get('/meus-pedidos', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/meus-pedidos/{order}', [OrderController::class, 'show'])->name('orders.show');

    // Clube — assinatura
    Route::get('/clube/assinar/{plan}', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::post('/clube/assinar/{plan}', [SubscriptionController::class, 'store'])->name('subscription.store');
    Route::get('/minha-assinatura', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/minha-assinatura/{subscription}/pausar', [SubscriptionController::class, 'pause'])->name('subscription.pause');
    Route::post('/minha-assinatura/{subscription}/retomar', [SubscriptionController::class, 'resume'])->name('subscription.resume');
    Route::post('/minha-assinatura/{subscription}/cancelar', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
});

/*
|--------------------------------------------------------------------------
| Redirects da leva 01 (URLs antigas → novas, preservando SEO/links)
|--------------------------------------------------------------------------
*/
Route::redirect('/customizacao', '/custom', 301);
Route::redirect('/parceiros', '/collabs', 301);
Route::redirect('/sobre', '/quem-somos', 301);
Route::redirect('/marcas', '/quem-somos', 301);

/*
|--------------------------------------------------------------------------
| Páginas institucionais → PageController@show
|--------------------------------------------------------------------------
| Slugs fixos (gate também no controller via abort_unless). Fica por último
| para não capturar nenhuma rota literal acima.
*/
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', 'clube|assinantes|collabs|eventos|franquias|quem-somos')
    ->name('pages.show');

require __DIR__.'/auth.php';
