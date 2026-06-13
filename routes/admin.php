<?php

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\StockController;
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

    // Produtos (CRUD)
    Route::get('/produtos', [ProductController::class, 'index'])->name('products.index');
    Route::get('/produtos/novo', [ProductController::class, 'create'])->name('products.create');
    Route::post('/produtos', [ProductController::class, 'store'])->name('products.store');
    Route::get('/produtos/{product}/editar', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/produtos/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/produtos/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    // Clientes
    Route::get('/clientes', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/clientes/{user}', [CustomerController::class, 'show'])->name('customers.show');

    // Estoque
    Route::get('/estoque', [StockController::class, 'index'])->name('stock.index');
    Route::patch('/estoque/{product}', [StockController::class, 'update'])->name('stock.update');

});
