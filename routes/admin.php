<?php

use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do Painel Administrativo  (prefixo /admin, nomes admin.*)
|--------------------------------------------------------------------------
| Protegidas por login + role admin.
*/

Route::middleware(['auth', AdminMiddleware::class])->group(function () {

    Route::get('/ping', function () {
        return 'admin ok — autenticado com role admin';
    })->name('ping');

});
