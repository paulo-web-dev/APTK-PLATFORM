<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Custom (antiga Customização) — página + formulário do Custom Simples.
 *
 * Fluxos:
 *  - Custom Simples: fonte + coquetel + mensagem (até 8 palavras) → Lead
 *    tipo "custom" no admin. E-mail à APTK fica pronto ("casca") mas
 *    desativado até haver SMTP no .env — ver comentário no store().
 *  - Full Custom: contato direto via WhatsApp (link na view).
 */
class CustomController extends Controller
{
    /** Fontes disponíveis no Custom Simples (modelos de referência). */
    public const FONTES = ['PP Rader', 'Amithen', 'Blackword'];

    /** Coquetéis disponíveis para personalização (lista do comercial). */
    public const COQUETEIS = [
        'Limoncello',
        'Fitzgerald',
        'Caipirinha',
        'Cosmopolitan',
        'Dry Martini',
        'Negroni',
        'Boulevardier',
        'Moscow Mule',
        "Sur L'Orange",
        'Old Fashioned',
    ];

    /** WhatsApp da equipe de criação (Full Custom). */
    public const WHATSAPP = '5511941766319';

    public function show(): View
    {
        return view('shop.custom', [
            'fontes'    => self::FONTES,
            'coqueteis' => self::COQUETEIS,
            'whatsapp'  => self::WHATSAPP,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:20'],
            'fonte'    => ['required', 'in:'.implode(',', self::FONTES)],
            'coquetel' => ['required', 'in:'.implode(',', self::COQUETEIS)],
            'mensagem' => ['required', 'string', 'max:120', function ($attribute, $value, $fail) {
                if (count(Str::of($value)->squish()->explode(' ')->filter()->all()) > 8) {
                    $fail('A mensagem da personalização pode ter no máximo 8 palavras.');
                }
            }],
        ], [
            'fonte.in'          => 'Escolha uma das fontes disponíveis.',
            'coquetel.in'       => 'Escolha um coquetel da lista.',
            'mensagem.required' => 'Escreva a mensagem que vai no rótulo.',
        ]);

        $lead = Lead::create([
            'type'    => 'custom',
            'name'    => $data['name'],
            'email'   => $data['email'],
            'phone'   => $data['phone'] ?? null,
            'status'  => 'new',
            'message' => implode("\n", [
                'Custom Simples — pedido de rótulo personalizado',
                'Fonte: '.$data['fonte'],
                'Coquetel: '.$data['coquetel'],
                'Mensagem do rótulo: "'.Str::squish($data['mensagem']).'"',
            ]),
        ]);

        /*
         | E-mail para a APTK — ATIVAR quando o SMTP estiver no .env:
         |
         |   \Illuminate\Support\Facades\Mail::to('rafael@aptkspirits.com')
         |       ->send(new \App\Mail\CustomOrderRequested($lead));
         |
         | A casca (Mailable + template markdown) já está pronta em
         | app/Mail/CustomOrderRequested.php e resources/views/emails/.
         */

        return back()->with('custom_ok', 'Pedido recebido! Nossa equipe entra em contato com a proposta.');
    }
}
