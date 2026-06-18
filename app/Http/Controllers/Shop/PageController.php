<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        // Páginas com layout próprio.
        if ($slug === 'sobre') {
            return view('shop.sobre');
        }

        if ($slug === 'clube') {
            $plans = SubscriptionPlan::where('active', true)->orderBy('sort_order')->get();

            return view('shop.clube', compact('plans'));
        }

        // Demais páginas institucionais — template único (hero + blocos + fechamento).
        $pages = $this->institutionalPages();
        abort_unless(isset($pages[$slug]), 404);

        return view('shop.page', ['page' => $pages[$slug]]);
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
     * Textos e CTAs são editáveis — ajustar conforme o comercial.
     */
    private function institutionalPages(): array
    {
        return [
            'customizacao' => [
                'eyebrow'  => 'Rótulo personalizado',
                'title'    => 'Sua marca na nossa garrafa',
                'lead'     => 'Presentes corporativos, casamentos, aniversários ou a sua própria marca: personalizamos o rótulo e a curadoria do drink, do lote pequeno ao grande volume.',
                'image'    => null,
                'hero_cta' => ['label' => 'Pedir orçamento', 'href' => $this->mailto('Customização de rótulo — APTK')],
                'features_eyebrow' => 'Como funciona',
                'features_title'   => 'Do conceito à garrafa',
                'features' => [
                    ['n' => '01', 'h' => 'Escolha o drink', 'p' => 'Comece por um clássico ou um autoral da casa como base.'],
                    ['n' => '02', 'h' => 'Crie o rótulo', 'p' => 'A gente desenha — ou aplica a sua arte — dentro da identidade APTK.'],
                    ['n' => '03', 'h' => 'Defina o lote', 'p' => 'De poucas garrafas a grandes volumes, com prazo combinado.'],
                    ['n' => '04', 'h' => 'Receba pronto', 'p' => 'Engarrafado, rotulado e embalado para presentear ou vender.'],
                ],
                'closing_title' => 'Tem uma ideia de rótulo?',
                'closing_text'  => 'Conte a ocasião e o volume — devolvemos com proposta e prazo.',
                'closing_cta'   => ['label' => 'Pedir orçamento', 'href' => $this->mailto('Customização de rótulo — APTK')],
                'closing_cta2'  => ['label' => 'Ver os drinks', 'href' => route('catalog')],
            ],

            'parceiros' => [
                'eyebrow'  => 'Para o trade',
                'title'    => 'APTK no seu bar, restaurante ou loja',
                'lead'     => 'Bares, restaurantes, distribuidores e varejo: leve a coquetelaria engarrafada da APTK para o seu negócio, com condições de revenda e abastecimento recorrente.',
                'image'    => 'img/aptk/parceiros-hero.jpg',
                'hero_cta' => ['label' => 'Quero ser parceiro', 'href' => $this->mailto('Quero ser parceiro — APTK')],
                'features_eyebrow' => 'O que oferecemos',
                'features_title'   => 'Parceria do balcão ao estoque',
                'features' => [
                    ['n' => 'Revenda',       'h' => 'Linha completa', 'p' => 'Drinks prontos e bases autorais para compor o seu mix.'],
                    ['n' => 'Logística',     'h' => 'Abastecimento', 'p' => 'Pedidos recorrentes com previsibilidade e entrega combinada.'],
                    ['n' => 'Marca',         'h' => 'Treinamento', 'p' => 'Apoio de marca e know-how de coquetelaria para a sua equipe.'],
                    ['n' => 'B2B',           'h' => 'Ice4Pros', 'p' => 'Gelo e insumos para o trade, dentro da própria holding.'],
                ],
                'closing_title' => 'Vamos fechar parceria?',
                'closing_text'  => 'Conte o seu formato de negócio e a gente monta a melhor condição.',
                'closing_cta'   => ['label' => 'Falar com o comercial', 'href' => $this->mailto('Parceria comercial — APTK')],
                'closing_cta2'  => null,
            ],

            'franquias' => [
                'eyebrow'  => 'Seja franqueado',
                'title'    => 'Abra uma unidade APTK',
                'lead'     => 'Um modelo de negócio com marca consolidada, produto próprio e operação enxuta — do quiosque em shopping à loja de rua.',
                'image'    => 'img/aptk/franquias-hero.jpg',
                'hero_cta' => ['label' => 'Quero franquear', 'href' => $this->mailto('Quero franquear — APTK')],
                'features_eyebrow' => 'O que você recebe',
                'features_title'   => 'Uma operação pronta para crescer',
                'features' => [
                    ['n' => '01', 'h' => 'Marca consolidada', 'p' => 'Uma identidade reconhecida e produto autoral exclusivo.'],
                    ['n' => '02', 'h' => 'Formatos flexíveis', 'p' => 'Do quiosque compacto à loja completa, conforme o ponto.'],
                    ['n' => '03', 'h' => 'Operação enxuta', 'p' => 'Processos definidos, fornecimento central e suporte contínuo.'],
                    ['n' => '04', 'h' => 'Suporte de abertura', 'p' => 'Implantação, treinamento e marketing para começar certo.'],
                ],
                'closing_title' => 'Quer os números?',
                'closing_text'  => 'Solicite o material com investimento, formatos e retorno estimado.',
                'closing_cta'   => ['label' => 'Pedir apresentação', 'href' => $this->mailto('Franquia APTK — apresentação')],
                'closing_cta2'  => null,
            ],

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
                'closing_cta2'  => ['label' => 'Ver o catálogo', 'href' => route('catalog')],
            ],

            'marcas' => [
                'eyebrow'  => 'A holding',
                'title'    => 'Uma casa, três marcas',
                'lead'     => 'A APTK Spirits reúne a coquetelaria autoral, a linha artesanal BARIN e a operação B2B da Ice4Pros — cada uma com o seu papel, o mesmo padrão.',
                'image'    => null,
                'hero_cta' => ['label' => 'Ver a loja', 'href' => route('catalog')],
                'features_eyebrow' => 'As marcas',
                'features_title'   => 'Cada uma no seu mundo',
                'features' => [
                    ['n' => 'Coquetelaria', 'h' => 'APTK Spirits', 'p' => 'Alta coquetelaria engarrafada, drinks prontos e bases autorais.'],
                    ['n' => 'Artesanal',    'h' => 'BARIN', 'p' => 'A linha artesanal da casa, com receitas próprias e alma de bar.'],
                    ['n' => 'B2B',          'h' => 'Ice4Pros', 'p' => 'Gelo e insumos para o trade, com capacidade e qualidade.'],
                ],
                'closing_title' => 'Negócios com a holding',
                'closing_text'  => 'Revenda, B2B e parcerias — fale com o nosso comercial.',
                'closing_cta'   => ['label' => 'Falar com o comercial', 'href' => $this->mailto('Marcas / negócios — APTK')],
                'closing_cta2'  => ['label' => 'Conhecer a APTK', 'href' => route('pages.show', 'sobre')],
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
