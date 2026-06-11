<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class B2BMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Parceiros B2B e admins (supervisão) têm acesso ao portal.
        if (! $user || ! ($user->isB2b() || $user->isAdmin())) {
            abort(403, 'Acesso restrito ao portal de parceiros.');
        }

        return $next($request);
    }
}
