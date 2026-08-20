<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cotação de frete do checkout (POST /frete/cotar).
 *
 * Resposta no formato que uma transportadora real devolveria — array de
 * opções com nome, valor e prazo_dias — para a troca futura ser só de
 * implementação, sem mexer no front.
 */
class FreteController extends Controller
{
    public function cotar(Request $request): JsonResponse
    {
        $data = $request->validate(
            ['zipcode' => ['required', 'string', 'regex:/^\d{5}-?\d{3}$/']],
            ['zipcode.regex' => 'Informe um CEP válido (00000-000).'],
        );

        // TODO: substituir por cotação real (SuperFrete) — sem conta/token ainda.
        // Enquanto isso: opção única fixa, para o checkout ter frete de verdade
        // no fluxo (valor entra em shipping_cost quando o front for ligado).
        return response()->json([
            'cep'    => $data['zipcode'],
            'opcoes' => [
                [
                    'nome'       => 'Entrega padrão',
                    'valor'      => 10.00,
                    'prazo_dias' => 5,
                ],
            ],
        ]);
    }
}
