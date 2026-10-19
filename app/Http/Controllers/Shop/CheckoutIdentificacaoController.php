<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

/**
 * Identificação DENTRO do checkout (leva 09): visitante informa o e-mail,
 * entra com a senha ou cria a conta, tudo por AJAX, sem sair da página e
 * sem perder o carrinho (sessão migrada no login, dados preservados).
 *
 * Proteções:
 *   - e-mail: throttle por IP (rotas) + latência mínima igual com ou sem
 *     conta, pra não servir de varredura de quem é cliente;
 *   - login: o LoginRequest do Breeze (5 tentativas por e-mail+IP);
 *   - cadastro: throttle por IP (rotas).
 */
class CheckoutIdentificacaoController extends Controller
{
    /** Latência mínima da verificação de e-mail (ms), com ou sem conta. */
    protected const LATENCIA_MINIMA_MS = 400;

    public function email(Request $request): JsonResponse
    {
        $inicio = microtime(true);

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'email.required' => 'Informe seu e-mail.',
            'email.email'    => 'Confira o e-mail digitado.',
        ]);

        $conta = User::where('email', mb_strtolower(trim($data['email'])))->exists();

        // Mesma latência nos dois casos: o tempo de resposta não entrega nada.
        $resto = self::LATENCIA_MINIMA_MS - (int) ((microtime(true) - $inicio) * 1000);
        if ($resto > 0) {
            usleep($resto * 1000);
        }

        return response()->json(['conta' => $conta]);
    }

    public function entrar(LoginRequest $request): JsonResponse
    {
        // Mesmo login do Breeze: credenciais, rate limit e mensagem de erro.
        $request->authenticate();

        // Troca o id da sessão e MIGRA os dados: o carrinho continua.
        $request->session()->regenerate();

        return $this->identificado($request);
    }

    public function cadastrar(Request $request): JsonResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password'    => ['required', Rules\Password::defaults()],
            'maior_idade' => ['accepted'],
        ], [
            'name.required'        => 'Informe seu nome.',
            'email.unique'         => 'Este e-mail já tem conta. Volte e entre com a sua senha.',
            'password.required'    => 'Crie uma senha.',
            'maior_idade.accepted' => 'Para comprar, confirme que você tem 18 anos ou mais.',
        ]);

        $user = User::create([
            'name'           => $data['name'],
            'email'          => $data['email'],
            'password'       => Hash::make($data['password']),
            'role'           => 'customer',
            'maior_idade_em' => now(),
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return $this->identificado($request);
    }

    /** Dados que o checkout precisa pra seguir: nome, e-mail, CSRF e o 18+. */
    protected function identificado(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'ok'                  => true,
            'nome'                => $user->name,
            'email'               => $user->email,
            'csrf'                => csrf_token(),
            'precisa_maior_idade' => ! $user->declarouMaioridade(),
        ]);
    }
}
