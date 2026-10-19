<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Destino após login/registro (o Breeze redireciona pra cá).
     */
    public const HOME = '/dashboard';

    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            // API — padrão do Laravel
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Loja pública  →  /
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Painel administrativo  →  /admin/*   (nomes: admin.*)
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            // Portal de parceiros B2B  →  /b2b/*   (nomes: b2b.*)
            Route::middleware('web')
                ->prefix('b2b')
                ->name('b2b.')
                ->group(base_path('routes/b2b.php'));
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Identificação no checkout (leva 09). Limitadores NOMEADOS, cada um
        // com a sua chave: o throttle:N,M genérico usa só domínio|IP e as
        // rotas dividiriam o mesmo balde (verificar e-mail gastaria o login).
        RateLimiter::for('checkout-email', fn (Request $request) => Limit::perMinute(10)->by('checkout-email|'.$request->ip()));
        RateLimiter::for('checkout-entrar', fn (Request $request) => Limit::perMinute(10)->by('checkout-entrar|'.$request->ip()));
        RateLimiter::for('checkout-cadastrar', fn (Request $request) => Limit::perMinute(5)->by('checkout-cadastrar|'.$request->ip()));
    }
}
