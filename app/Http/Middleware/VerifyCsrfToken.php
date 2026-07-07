<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'webhooks/appmax/*', // POST server-to-server da Appmax (validado por token na URL)
        'appmax/callback',   // callback da autorização de instalação do app
        //
    ];
}
