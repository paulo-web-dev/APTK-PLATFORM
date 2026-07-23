<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\CustomController;
use App\Http\Controllers\Shop\FreteController;
use App\Http\Controllers\Shop\NovidadesController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\PageController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\SubscriptionController;
use App\Http\Controllers\Webhooks\AppmaxWebhookController;
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

// Cotação de frete (SuperFrete) — usada pelo bloco de CEP do checkout.
Route::post('/frete/cotar', [FreteController::class, 'cotar'])->name('frete.cotar');

/*
|--------------------------------------------------------------------------
| Landing pages das marcas (leva 03) — branding antes da venda
|--------------------------------------------------------------------------
*/
Route::view('/barin', 'shop.barin')->name('barin');

// Dicas e Novidades (leva 06) — mini-blog.
Route::get('/novidades', [NovidadesController::class, 'index'])->name('novidades.index');
Route::get('/novidades/{slug}', [NovidadesController::class, 'show'])->name('novidades.show');
Route::view('/ice4pros', 'shop.ice4pros')->name('ice4pros');

// Clube (pré-lançamento): captação de interesse → Lead tipo "clube".
Route::post('/clube/interesse', [PageController::class, 'clubeInteresse'])->name('clube.interesse');

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
    Route::get('/pedido/{order}/status-pagamento', [CheckoutController::class, 'paymentStatus'])->name('checkout.payment-status');

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
| Webhook Appmax (sem CSRF — exceção em VerifyCsrfToken)
|--------------------------------------------------------------------------
| Cadastrar no painel: https://SEU-DOMINIO/webhooks/appmax/{APPMAX_WEBHOOK_TOKEN}
*/
Route::post('/webhooks/appmax/{token}', [AppmaxWebhookController::class, 'handle'])
    ->name('webhooks.appmax');

// Callback da autorização da instalação do app Appmax (doc 2.2/2.3).
// CAPTURA GENÉRICA: registra tudo o que a Appmax enviar (query + corpo) em
// storage/logs/laravel.log com o prefixo "Appmax callback" — as credenciais
// do merchant chegam por aqui. Será substituída pelo handler definitivo
// quando o formato (doc 2.3) for confirmado.
Route::match(['GET', 'POST'], '/appmax/callback', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Log::info('Appmax callback recebido', [
        'method' => $request->method(),
        'query'  => $request->query(),
        'body'   => $request->all(),
    ]);

    return response(
        '<h2 style="font-family:sans-serif;">APTK ✓ Autorização recebida.</h2>'.
        '<p style="font-family:sans-serif;">Os dados foram registrados no servidor. Pode fechar esta janela.</p>',
        200,
    )->header('Content-Type', 'text/html; charset=utf-8');
})->name('appmax.callback');

// URL de validação do app Appmax (doc 2.3): GET = status manual;
// POST = health check da instalação, que ENTREGA as credenciais do merchant
// e exige resposta {"external_id": uuid-v4-novo} — ver AppmaxInstallController.
Route::match(['GET', 'POST'], '/appmax/health', [\App\Http\Controllers\Webhooks\AppmaxInstallController::class, 'health'])
    ->name('appmax.health');

/*
|--------------------------------------------------------------------------
| Redirects da leva 01 (URLs antigas → novas, preservando SEO/links)
|--------------------------------------------------------------------------
*/
Route::redirect('/customizacao', '/custom', 301);
// Leva 04: Franquias agora é a LP do parceiro (form + GTM mantidos lá).
Route::redirect('/franquias', 'https://lp.aptkspirits.com/', 301);
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
    ->where('slug', 'clube|assinantes|collabs|eventos|quem-somos')
    ->name('pages.show');

require __DIR__.'/auth.php';
