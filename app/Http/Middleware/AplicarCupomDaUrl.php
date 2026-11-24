<?php

namespace App\Http\Middleware;

use App\Services\CouponService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Link de campanha: qualquer GET com ?cupom=CODIGO guarda o cupom na
 * sessão e redireciona pra mesma URL SEM o parâmetro cupom (os demais,
 * utm_* etc., ficam), com um aviso discreto. Recusado não fica na sessão
 * e o aviso diz o motivo. Só GET: POST/forms nunca são desviados.
 *
 * No grupo web (precisa de sessão). Domínio de marca: o MarcaHost já leva
 * o ?cupom= pra APTK no 301.
 */
class AplicarCupomDaUrl
{
    public function __construct(protected CouponService $cupons)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Admin fica fora: lá ?cupom= é o filtro da lista de pedidos.
        if (! $request->isMethod('GET') || ! $request->query->has('cupom') || $request->is('admin', 'admin/*')) {
            return $next($request);
        }

        $codigo = CouponService::normalizar((string) $request->query('cupom'));

        if ($codigo !== '') {
            // Prévia só com o e-mail da conta (o carrinho pode estar vazio:
            // mínimo/primeira compra são reavaliados no carrinho e no fechamento).
            $avaliacao = $this->cupons->avaliar($codigo, PHP_FLOAT_MAX, $request->user()?->email);

            if ($avaliacao->aplicado) {
                $this->cupons->guardar($codigo);
            }

            session()->flash('cupom_aviso', [
                'ok'       => $avaliacao->aplicado,
                'mensagem' => $avaliacao->mensagem(),
            ]);
        }

        $resto = $request->query();
        unset($resto['cupom']);

        $destino = $request->url().($resto ? '?'.http_build_query($resto) : '');

        return redirect()->to($destino);
    }
}
