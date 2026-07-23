<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do Painel Administrativo  (prefixo /admin, nomes admin.*)
|--------------------------------------------------------------------------
| Protegidas por login + role admin.
*/

Route::middleware(['auth', AdminMiddleware::class])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Pedidos
    Route::get('/pedidos', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/pedidos/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('/pedidos/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
    Route::post('/pedidos/{order}/estorno', [OrderController::class, 'refund'])->name('orders.refund');

    // Produtos (CRUD)
    Route::get('/produtos', [ProductController::class, 'index'])->name('products.index');
    Route::get('/produtos/novo', [ProductController::class, 'create'])->name('products.create');
    Route::post('/produtos', [ProductController::class, 'store'])->name('products.store');
    Route::get('/produtos/{product}/editar', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/produtos/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/produtos/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    // Categorias (CRUD)
    Route::get('/categorias', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categorias/nova', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categorias', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categorias/{category}/editar', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categorias/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categorias/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    // Cupons (CRUD)
    Route::get('/cupons', [CouponController::class, 'index'])->name('coupons.index');
    Route::get('/cupons/novo', [CouponController::class, 'create'])->name('coupons.create');
    Route::post('/cupons', [CouponController::class, 'store'])->name('coupons.store');
    Route::get('/cupons/{coupon}/editar', [CouponController::class, 'edit'])->name('coupons.edit');
    Route::put('/cupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
    Route::delete('/cupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');

    // Clube — planos (CRUD)
    Route::get('/clube/planos', [PlanController::class, 'index'])->name('plans.index');
    Route::get('/clube/planos/novo', [PlanController::class, 'create'])->name('plans.create');
    Route::post('/clube/planos', [PlanController::class, 'store'])->name('plans.store');
    Route::get('/clube/planos/{plan}/editar', [PlanController::class, 'edit'])->name('plans.edit');
    Route::put('/clube/planos/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::delete('/clube/planos/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');

    // Clube — assinaturas (gestão)
    Route::get('/assinaturas', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::get('/assinaturas/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
    Route::patch('/assinaturas/{subscription}/pausar', [SubscriptionController::class, 'pause'])->name('subscriptions.pause');
    Route::patch('/assinaturas/{subscription}/retomar', [SubscriptionController::class, 'resume'])->name('subscriptions.resume');
    Route::patch('/assinaturas/{subscription}/cancelar', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

    // Novidades (mini-blog "Dicas e Novidades" — leva 06)
    Route::get('/novidades', [PostController::class, 'index'])->name('posts.index');
    Route::get('/novidades/nova', [PostController::class, 'create'])->name('posts.create');
    Route::post('/novidades', [PostController::class, 'store'])->name('posts.store');
    Route::get('/novidades/{post}/editar', [PostController::class, 'edit'])->name('posts.edit');
    Route::put('/novidades/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/novidades/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

    // Leads (gestão)
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::patch('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');

    // Configurações (chave/valor)
    Route::get('/configuracoes', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/configuracoes', [SettingController::class, 'store'])->name('settings.store');
    Route::put('/configuracoes/{setting}', [SettingController::class, 'update'])->name('settings.update');
    Route::delete('/configuracoes/{setting}', [SettingController::class, 'destroy'])->name('settings.destroy');

    // Clientes
    Route::get('/clientes', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/clientes/{user}', [CustomerController::class, 'show'])->name('customers.show');

    // Estoque
    Route::get('/estoque', [StockController::class, 'index'])->name('stock.index');
    Route::patch('/estoque/{product}', [StockController::class, 'update'])->name('stock.update');

});
