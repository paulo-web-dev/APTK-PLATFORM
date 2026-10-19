<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    /**
     * Portfólio de eventos realizados (leva 10, view shop.eventos). A seção
     * só aparece com ao menos 1 item. Cada evento:
     *   ['titulo' => 'Nome do evento', 'quando' => '2025 · São Paulo',
     *    'descricao' => 'Uma linha sobre o que a APTK fez',
     *    'image' => 'img/aptk/arquivo.jpg' ou null (bloco reservado)]
     * Aguardando a gestão: nome, cliente, data, local, foto e autorização de uso.
     */
    public const PORTFOLIO_EVENTOS = [];

    public function show(string $slug): View
    {
        // Páginas com layout próprio.
        if ($slug === 'quem-somos') {
            // Quem Somos = antiga "Sobre" + bloco das marcas (antiga "Marcas").
            return view('shop.quem-somos');
        }

        if ($slug === 'clube') {
            // Leva 05 — lançamento da mecânica oficial: 4 níveis do banco
            // (ClubePlanos2026Seeder). A vitrine antiga ficou inativa.
            $plans = \App\Models\SubscriptionPlan::where('active', true)
                ->orderBy('sort_order')
                ->get();

            return view('shop.clube', compact('plans'));
        }

        if ($slug === 'eventos') {
            // Leva 10: view própria (antes usava o template shop.page).
            return view('shop.eventos', [
                'portfolio' => self::PORTFOLIO_EVENTOS,
                'proposta'  => $this->mailto('Evento — solicitar proposta — APTK'),
            ]);
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
                'eyebrow'  => 'Collab',
                'title'    => 'Tenha um coquetel para chamar de seu',
                'lead'     => 'Transformamos ideias em edições exclusivas. Seja para presentear clientes, celebrar um marco importante, lançar uma collab ou criar um brinde memorável, a APTK desenvolve projetos personalizados que unem coquetelaria autoral, design e curadoria — garrafas únicas, pensadas para representar a identidade de cada projeto, do líquido ao rótulo.',
                'video'     => 'video/ice4pros-cubo.mp4',
                'video_tag' => 'Ice4Pros — o cubo perfeito',
                'image'    => 'img/aptk/parceiros-hero.jpg',
                'hero_cta' => ['label' => 'Compartilhar minha ideia', 'href' => $this->mailto('Collab / projeto personalizado — APTK')],

                // Parcerias — lista oficial (doc leva 05). Fotos: aguardando material.
                'cases_eyebrow' => 'Nossas parcerias',
                'cases_title'   => 'Algumas histórias também são servidas em uma garrafa',
                'cases_lead'    => 'A APTK está presente em alguns dos principais destinos de gastronomia, hospitalidade e lifestyle do país — rótulos exclusivos e projetos personalizados para quem compartilha do nosso compromisso com excelência, curadoria e atenção aos detalhes.',
                'cases' => [
                    ['h' => 'Selvagem',           'p' => 'Projeto personalizado para a casa.', 'image' => null],
                    ['h' => 'Hotel Unique',       'p' => 'Rótulo exclusivo da casa.', 'image' => null],
                    ['h' => 'Rosewood São Paulo', 'p' => 'Parceria com assinatura APTK.', 'image' => null],
                    ['h' => 'Bráz Pizzaria',      'p' => 'Limoncello Bráz, criado para a casa.', 'image' => null],
                    ['h' => 'Gurumê',             'p' => 'Gin Gurumê, assinatura exclusiva.', 'image' => null],
                    ['h' => 'Pirajá',             'p' => 'Projeto exclusivo para a casa.', 'image' => null],
                    ['h' => 'Hotel Emiliano',     'p' => 'Drink Cubo, servido no hotel.', 'image' => null],
                    ['h' => 'Camolese',           'p' => 'Rótulo personalizado da casa.', 'image' => null],
                    ['h' => 'Barbacoa',           'p' => 'Projeto em parceria com a marca.', 'image' => null],
                ],
                'cases_footer' => 'Mais do que um produto, entregamos uma extensão da marca de nossos parceiros — coquetelaria autoral como elemento de conexão, memória e celebração.',

                // Como funciona — passos oficiais + prazo (doc leva 05).
                'steps_eyebrow' => 'Como funciona?',
                'steps_title'   => 'Da ideia ao brinde',
                'steps' => [
                    ['h' => 'Entre em contato',            'p' => 'Compartilhe sua ideia, ocasião e objetivo.'],
                    ['h' => 'Reunião de alinhamento',      'p' => 'Definimos quantidade, receita, prazo e necessidades do projeto.'],
                    ['h' => 'Criação e aprovação',         'p' => 'Desenvolvemos o rótulo, embalagem e todos os detalhes visuais.'],
                    ['h' => 'Alinhamento de comunicação',  'p' => 'Caso necessário, desenhamos a narrativa e os materiais de apoio da collab.'],
                    ['h' => 'Produção',                    'p' => 'Iniciamos a produção das garrafas personalizadas.'],
                    ['h' => 'Entrega',                     'p' => 'Seu projeto é entregue pronto para brindar.'],
                ],
                'steps_note' => 'Prazo estimado: de 15 a 20 dias úteis, podendo variar conforme a complexidade e o nível de personalização do projeto.',

                'closing_title' => 'Na APTK, cada garrafa pode contar uma história',
                'closing_text'  => 'Conte a sua — e a gente transforma em uma edição exclusiva.',
                'closing_cta'   => ['label' => 'Falar com o comercial', 'href' => $this->mailto('Collab / projeto personalizado — APTK')],
                'closing_cta2'  => null,
            ],

            /* Leva 04: página interna de Franquias APOSENTADA — o menu e a
               rota /franquias apontam pra LP do parceiro em
               https://lp.aptkspirits.com/ (form, GTM e vídeos mantidos lá). */

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
