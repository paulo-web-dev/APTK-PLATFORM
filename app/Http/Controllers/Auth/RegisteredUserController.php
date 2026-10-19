<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\DestinoPosLogin;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        DestinoPosLogin::lembrar($request); // ?voltar=checkout|carrinho

        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            // Venda de bebida alcoólica: declaração obrigatória (leva 09).
            'maior_idade' => ['accepted'],
        ], [
            'maior_idade.accepted' => 'Para criar a conta, confirme que você tem 18 anos ou mais.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'maior_idade_em' => now(),
        ]);

        event(new Registered($user));

        // Auth::login migra a sessão (novo id, mesmos dados): o carrinho fica.
        Auth::login($user);

        return DestinoPosLogin::redirecionar($request);
    }
}
