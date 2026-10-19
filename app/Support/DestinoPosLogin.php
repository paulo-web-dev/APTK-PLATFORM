<?php

namespace App\Support;

use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Para onde vai quem acabou de entrar ou criar conta pelas páginas normais
 * (/login, /register). Compra por impulso: com carrinho cheio, nunca cai
 * na área "minha conta".
 *
 *   1. veio do checkout (?voltar=checkout, ou intended = /checkout) → /checkout
 *   2. ?voltar=carrinho, ou carrinho com item                      → /carrinho
 *   3. senão                                                        → intended ou home
 *
 * O ?voltar= é lido na página de login/cadastro e guardado na sessão
 * (whitelist), então sobrevive à troca login ↔ cadastro e ao POST.
 */
class DestinoPosLogin
{
    public const CHAVE = 'auth.voltar';

    public const PERMITIDOS = ['checkout', 'carrinho'];

    public static function lembrar(Request $request): void
    {
        $voltar = $request->query('voltar');

        if (in_array($voltar, self::PERMITIDOS, true)) {
            $request->session()->put(self::CHAVE, $voltar);
        }
    }

    public static function redirecionar(Request $request): RedirectResponse
    {
        $voltar = $request->session()->pull(self::CHAVE);
        $intended = (string) $request->session()->get('url.intended', '');

        if ($voltar === 'checkout' || parse_url($intended, PHP_URL_PATH) === '/checkout') {
            $request->session()->forget('url.intended');

            return redirect()->route('checkout.index');
        }

        if ($voltar === 'carrinho' || ! app(CartService::class)->isEmpty()) {
            $request->session()->forget('url.intended');

            return redirect()->route('cart.index');
        }

        return redirect()->intended(route('home'));
    }
}
