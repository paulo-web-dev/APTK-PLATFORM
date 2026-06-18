<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    /** Frentes ainda não construídas — renderizam uma página "em breve" enquanto não saem. */
    private const PAGES = [
        'customizacao' => ['Customização de Rótulos', 'Monte sua garrafa: escolha o líquido, o modelo de rótulo, escreva sua mensagem e veja a prévia em tempo real.'],
        'clube'        => ['Clube de Assinatura', 'Cinco planos de recorrência com curadoria mensal, brindes e acesso antecipado aos lotes.'],
        'assinantes'   => ['Área de Assinantes', 'Pontos, edições limitadas via crowdfunding, sorteios e experiências exclusivas para membros.'],
        'parceiros'    => ['Portal de Parceiros', 'Catálogo com preços especiais, financeiro, histórico de pedidos e materiais — para franqueados e distribuidores.'],
        'eventos'      => ['Eventos & Corporativo', 'Bar APTK, kits corporativos, garrafas personalizadas e ativações de marca para o seu evento.'],
        'franquias'    => ['Seja Franqueado', 'Modelo de negócio, formatos, investimento e suporte da APTK para abrir a sua unidade.'],
        'marcas'       => ['Nossas Marcas', 'BARIN — bebidas artesanais. Ice4Pros — gelo e insumos B2B para o trade.'],
        'sobre'        => ['Sobre a APTK', 'Small Batches Holding: destilados autorais, clube de assinatura e as marcas da casa.'],
    ];

    public function show(string $slug): View
    {
        // "Sobre" tem página própria (não é mais "em breve").
        if ($slug === 'sobre') {
            return view('shop.about');
        }

        abort_unless(isset(self::PAGES[$slug]), 404);

        [$title, $description] = self::PAGES[$slug];

        return view('shop.coming-soon', compact('title', 'description', 'slug'));
    }

    public function newsletter(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        Lead::create([
            'type'   => 'newsletter',
            'name'   => 'Assinante newsletter',
            'email'  => $data['email'],
            'status' => 'new',
        ]);

        return back()->with('newsletter_ok', 'Pronto! Você entrou na lista.');
    }
}
