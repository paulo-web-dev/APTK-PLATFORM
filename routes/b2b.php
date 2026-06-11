<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do Portal de Parceiros (B2B)
|--------------------------------------------------------------------------
| Registradas em App\Providers\RouteServiceProvider com:
|   - prefixo de URL "b2b"      → tudo aqui começa em /b2b
|   - middleware "web"          → sessão, cookies, CSRF
|   - prefixo de nome "b2b."    → nomes viram b2b.dashboard, b2b.catalog, etc.
|
| O middleware de permissão (B2BMiddleware) entra na Etapa 6.
*/

Route::get('/ping', function () {
    return 'b2b ok';
})->name('ping');