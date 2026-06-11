<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do Painel Administrativo
|--------------------------------------------------------------------------
| Registradas em App\Providers\RouteServiceProvider com:
|   - prefixo de URL "admin"    → tudo aqui começa em /admin
|   - middleware "web"          → sessão, cookies, CSRF
|   - prefixo de nome "admin."  → nomes viram admin.dashboard, admin.products.index, etc.
|
| O middleware de permissão (AdminMiddleware) entra na Etapa 6.
*/

Route::get('/ping', function () {
    return 'admin ok';
})->name('ping');