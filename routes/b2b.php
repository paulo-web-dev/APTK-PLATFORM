<?php

use App\Http\Middleware\B2BMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do Portal de Parceiros B2B  (prefixo /b2b, nomes b2b.*)
|--------------------------------------------------------------------------
| Protegidas por login + role b2b (admins também passam).
*/

Route::middleware(['auth', B2BMiddleware::class])->group(function () {

    Route::get('/ping', function () {
        return 'b2b ok — autenticado com role b2b';
    })->name('ping');

});
