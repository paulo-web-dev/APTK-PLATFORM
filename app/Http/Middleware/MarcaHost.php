<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Domínio próprio de marca (config/marcas.php): a raiz serve a LP da marca
 * com layout isolado; o resto leva 301 pro mesmo caminho no domínio da APTK.
 *
 * Global no Kernel, depois do TrustProxies (Host correto atrás de proxy) e
 * do modo manutenção (em manutenção, marca responde 503 como todo o resto).
 * Roda antes do grupo web: a resposta da marca sai sem sessão nem cookie.
 */
class MarcaHost
{
    /**
     * Primeiros segmentos que NÃO redirecionam no domínio de marca:
     * assets referenciados pela própria LP (o <video> pode não seguir 301
     * dependendo do browser) e o acme-challenge da renovação do certificado.
     */
    private const PASSTHROUGH = [
        'css', 'js', 'img', 'fonts', 'video', 'storage',
        'favicon.ico', 'robots.txt', '.well-known',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $marca = $this->marcaDoHost($request->getHost());

        if ($marca === null) {
            return $next($request);
        }

        if ($request->path() === '/') {
            return response()->view($marca['view'], [
                'layoutMarca' => 'layouts.marca',
                'marca'       => $marca,
            ]);
        }

        if (in_array($request->segment(1), self::PASSTHROUGH, true)) {
            return $next($request);
        }

        $destino = rtrim(config('app.url'), '/').'/'.$request->path();

        if ($query = $request->getQueryString()) {
            $destino .= '?'.$query;
        }

        return redirect()->away($destino, 301);
    }

    private function marcaDoHost(string $host): ?array
    {
        foreach (config('marcas.marcas', []) as $marca) {
            if (in_array($host, $marca['hosts'], true)) {
                return $marca;
            }
        }

        return null;
    }
}
