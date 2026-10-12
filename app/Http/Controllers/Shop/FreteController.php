<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Services\FreteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cotação de frete do checkout (POST /frete/cotar).
 *
 * Estimativa própria (FreteService, config/frete.php) sobre o carrinho da
 * sessão. Resposta no formato que uma transportadora real devolveria —
 * array de opções com nome, valor e prazo_dias — para a troca futura
 * (SuperFrete) ser só de implementação, sem mexer no front.
 *
 * O valor daqui é só exibição: o fechamento do pedido recalcula.
 */
class FreteController extends Controller
{
    public function cotar(Request $request, CartService $cart, FreteService $frete): JsonResponse
    {
        $data = $request->validate(
            ['zipcode' => ['required', 'string', 'regex:/^\d{5}-?\d{3}$/']],
            ['zipcode.regex' => 'Informe um CEP válido (00000-000).'],
        );

        return response()->json([
            'cep'    => $data['zipcode'],
            'opcoes' => $frete->cotar($cart->items(), $data['zipcode']),
        ]);
    }
}
