<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        // Páginas com layout próprio.
        if ($slug === 'quem-somos') {
            // Quem Somos = antiga "Sobre" + bloco das marcas (antiga "Marcas").
            return view('shop.quem-somos');
        }

        if ($slug === 'clube') {
            // Leva 03 — pré-lançamento: a página é só captação de interesse.
            // Os planos seguem no banco (máquina de assinatura DORMENTE),
            // mas não são exibidos nem consultados aqui.
            return view('shop.clube');
        }

        // Demais páginas institucionais — template único (hero + blocos + fechamento).
        $pages = $this->institutionalPages();
        abort_unless(isset($pages[$slug]), 404);

        return view('shop.page', ['page' => $pages[$slug]]);
    }

    /**
     * Clube APTK (pré-lançamento): grava o interesse como Lead tipo "clube"
     * — base qualificada pra ativação quando o programa lançar.
     */
    public function clubeInteresse(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        Lead::create([
            'type'    => 'clube',
            'name'    => $data['name'],
            'email'   => $data['email'],
            'phone'   => $data['phone'] ?? null,
            'status'  => 'new',
            'message' => 'Lista de interesse do Clube APTK (pré-lançamento).',
        ]);

        return back()->with('clube_ok', 'Você está na lista! Avisamos em primeira mão quando o Clube abrir.');
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

    /** E-mail comercial com assunto pré-preenchido. */
    private function mailto(string $subject): string
    {
        return 'mailto:rafael@aptkspirits.com?subject='.rawurlencode($subject);
    }

    /**
     * Conteúdo das páginas institucionais, renderizado por shop/page.blade.php.
     * Estrutura: hero (eyebrow/title/lead/image/cta) + features + fechamento.
     * Páginas podem ter 'cases' (carrossel de projetos — usado na Collab).
     * Textos e CTAs são editáveis — ajustar conforme o comercial.
     */
    private function institutionalPages(): array
    {
        return [
            /*
             | COLLAB (antiga "Parceiros") — leva 01:
             |  - CTA do hero: "Falar com o comercial" (o texto do hero será
             |    alterado pelo cliente depois — manter editável).
             |  - Diferenciais sem o card "Abastecimento".
             |  - Carrossel de projetos feitos (fotos virão — placeholders
             |    marcados; trocar 'image' => null pelos paths quando chegarem).
             */
            'collabs' => [
                'eyebrow'  => 'Para o trade',
                'title'    => 'APTK no seu bar, restaurante ou loja',
                'lead'     => 'Bares, restaurantes, distribuidores e varejo: leve a coquetelaria engarrafada da APTK para o seu negócio, com condições de revenda e abastecimento recorrente.',
                // Vídeo VERTICAL da Ice4Pros (leva 02). Converter o
                // "ICE4PRO - CUBO.mov" para MP4 e soltar em
                // public/video/ice4pros-cubo.mp4. A imagem abaixo vira o poster.
                'video'     => 'video/ice4pros-cubo.mp4',
                'video_tag' => 'Ice4Pros — o cubo perfeito',
                'image'    => 'img/aptk/parceiros-hero.jpg',
                'hero_cta' => ['label' => 'Falar com o comercial', 'href' => $this->mailto('Collab / parceria comercial — APTK')],
                // Leva 04: seção "Nossos diferenciais" removida — os cases
                // (collabs já feitas) sobem pra logo após o hero, em destaque.
                'cases_eyebrow' => 'Projetos feitos',
                'cases_title'   => 'Quem já criou com a gente',
                'cases_lead'    => 'Muito além de gelo e bebida: produtos assinados em parceria com casas e marcas que a gente admira.',
                'cases' => [
                    ['h' => 'Gelo Carimbado',  'p' => 'Presença APTK em diversos estabelecimentos.', 'image' => null],
                    ['h' => 'Braz Pizzaria',   'p' => 'Limoncello Braz, criado para a casa.', 'image' => null],
                    ['h' => 'Gurumê',          'p' => 'Gin Gurumê, assinatura exclusiva.', 'image' => null],
                    ['h' => 'Hotel Emiliano',  'p' => 'Drink Cubo, servido no hotel.', 'image' => null],
                    ['h' => 'Cacau Show',      'p' => 'Licor de Chocolate e Whisky com Cacau.', 'image' => null],
                    ['h' => 'Guilhotina',      'p' => 'Vodka e Gin personalizados para o bar.', 'image' => null],
                    ['h' => 'Astor',           'p' => 'Vermute personalizado da casa.', 'image' => null],
                    ['h' => 'Hotéis & casas',  'p' => 'Hotel Unique, Hotel Pullman, Pirajá e outros.', 'image' => null],
                ],
                'closing_title' => 'Vamos fechar parceria?',
                'closing_text'  => 'Conte o seu formato de negócio e a gente monta a melhor condição.',
                'closing_cta'   => ['label' => 'Falar com o comercial', 'href' => $this->mailto('Parceria comercial — APTK')],
                'closing_cta2'  => null,
            ],

            /* Leva 04: página interna de Franquias APOSENTADA — o menu e a
               rota /franquias apontam pra LP do parceiro em
               https://lp.aptkspirits.com/ (form, GTM e vídeos mantidos lá). */

            'eventos' => [
                'eyebrow'  => 'Eventos & corporativo',
                'title'    => 'Drinks que fazem o evento',
                'lead'     => 'Casamentos, confraternizações e ativações de marca: coquetelaria APTK no copo dos seus convidados, com bar, equipe e curadoria sob medida.',
                'image'    => 'img/aptk/eventos-hero.jpg',
                'hero_cta' => ['label' => 'Solicitar proposta', 'href' => $this->mailto('Evento — solicitar proposta — APTK')],
                'features_eyebrow' => 'Formatos',
                'features_title'   => 'Do íntimo ao grande porte',
                'features' => [
                    ['n' => '01', 'h' => 'Open bar autoral', 'p' => 'Clássicos e autorais da casa, servidos como no balcão.'],
                    ['n' => '02', 'h' => 'Bar e equipe', 'p' => 'Estrutura e bartenders para atender qualquer escala.'],
                    ['n' => '03', 'h' => 'Brindes & kits', 'p' => 'Lembranças engarrafadas e rótulos personalizados do evento.'],
                    ['n' => '04', 'h' => 'Corporativo', 'p' => 'Ativações de marca, lançamentos e confraternizações.'],
                ],
                'closing_title' => 'Tem um evento chegando?',
                'closing_text'  => 'Conte a data, o local e o número de convidados — montamos a proposta.',
                'closing_cta'   => ['label' => 'Solicitar proposta', 'href' => $this->mailto('Evento — solicitar proposta — APTK')],
                'closing_cta2'  => ['label' => 'Ver a carta', 'href' => route('catalog')],
            ],

            'assinantes' => [
                'eyebrow'  => 'Área do assinante',
                'title'    => 'Sua conta, seus lotes',
                'lead'     => 'Acompanhe seus pedidos, gerencie a sua assinatura do Clube e seja o primeiro a saber dos lançamentos.',
                'image'    => null,
                'hero_cta' => ['label' => 'Entrar na conta', 'href' => route('login')],
                'features_eyebrow' => 'Vantagens',
                'features_title'   => 'Quem é da casa, recebe primeiro',
                'features' => [
                    ['n' => '01', 'h' => 'Seus pedidos', 'p' => 'Histórico e status das suas compras, num só lugar.'],
                    ['n' => '02', 'h' => 'Sua assinatura', 'p' => 'Gerencie o seu plano do Clube quando quiser.'],
                    ['n' => '03', 'h' => 'Acesso antecipado', 'p' => 'Lançamentos e lotes limitados antes de todo mundo.'],
                    ['n' => '04', 'h' => 'Brindes', 'p' => 'Mimos e convites exclusivos para membros.'],
                ],
                'closing_title' => 'Ainda não é assinante?',
                'closing_text'  => 'Conheça os planos do Clube APTK e comece a receber em casa.',
                'closing_cta'   => ['label' => 'Conhecer o Clube', 'href' => route('pages.show', 'clube')],
                'closing_cta2'  => ['label' => 'Entrar', 'href' => route('login')],
            ],
        ];
    }
}
