{{-- EVENTOS (leva 10) — view própria; antes usava o template shop.page
     (Collabs e Assinantes continuam nele). Textos do briefing p.25–30.
     Portfólio: grade de collabs (config/collabs.php, PageController::collabs)
     — a seção só aparece com ao menos 1 item. --}}
@extends('layouts.public')

@section('title', 'Eventos · APTK Spirits')
@section('meta_description', 'Eventos APTK: bar de alta coquetelaria, gifting, brand experiences e customização para eventos corporativos, lançamentos e celebrações privadas.')

{{-- Fechamento próprio ("Vamos criar juntos?"), sem a banda institucional. --}}
@section('hide_feature_band', '1')

@push('styles')
<style>
  /* ---- Eventos: hero com foto ---- */
  .evt-hero { position: relative; overflow: hidden; min-height: min(78vh, 720px); display: flex; align-items: flex-end; border-bottom: 1px solid var(--color-border); background: var(--color-ink); }
  .evt-hero img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
  .evt-hero::after { content: ""; position: absolute; inset: 0; background: linear-gradient(90deg, var(--color-ink) 0%, color-mix(in srgb, var(--color-ink) 80%, transparent) 40%, color-mix(in srgb, var(--color-ink) 25%, transparent) 100%), linear-gradient(0deg, var(--color-ink) 0%, transparent 45%); }
  .evt-hero .container-aptk { position: relative; z-index: 1; padding-block: clamp(64px, 9vw, 110px) clamp(48px, 7vw, 80px); }
  .evt-hero-copy { max-width: 640px; }
  .evt-hero .eyebrow { margin-bottom: 18px; }
  .evt-hero h1 { font-size: clamp(2.4rem, 5.4vw, 4rem); color: var(--color-cream); margin: 0 0 22px; }
  .evt-hero p { font-size: var(--text-lg); line-height: 1.75; color: color-mix(in srgb, var(--color-cream) 82%, transparent); margin: 0 0 14px; max-width: 580px; }
  .evt-hero .btn-aptk { margin-top: 16px; }

  /* ---- Ocasiões: 4 segmentos ---- */
  .evt-seg-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
  .evt-seg { border: 1px solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-bg-card); padding: 26px 24px; }
  .evt-seg h3 { font-family: var(--font-editorial); font-weight: 400; font-size: var(--text-xl); line-height: 1.25; margin: 0 0 10px; }
  .evt-seg p { color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.65; margin: 0; }

  /* ---- Portfólio e soluções: cards com foto ---- */
  .evt-band { background: var(--color-bg-card); border-block: 1px solid var(--color-border); }
  .evt-band .media-card { background: var(--color-bg); }
  .evt-sol-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
  .evt-sol { --mc-ratio: 16 / 10; }
  .evt-sol .ns-sub { font-family: var(--font-editorial); color: var(--color-text); margin: 0; }
  /* Âncora (#gifting, card "Arte de Presentear" da Home): desconta o header fixo */
  .evt-sol[id] { scroll-margin-top: calc(var(--header-h) + 24px); }

  /* ---- Fechamento ---- */
  .evt-closing { text-align: center; }
  .evt-closing .container-aptk { max-width: 720px; display: flex; flex-direction: column; align-items: center; }
  .evt-closing .sun { width: 120px; color: var(--color-primary); margin-bottom: 20px; }
  .evt-closing .eyebrow { margin-bottom: 16px; }
  .evt-closing h2 { font-size: clamp(2rem, 4.6vw, 3rem); margin: 0 0 16px; }
  .evt-closing p { color: var(--color-text-muted); font-size: var(--text-lg); line-height: 1.7; margin: 0 0 30px; max-width: 560px; }

  @media (max-width: 980px) {
    .evt-seg-grid { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 640px) {
    .evt-seg-grid, .evt-sol-grid { grid-template-columns: 1fr; }
    .evt-hero::after { background: linear-gradient(0deg, var(--color-ink) 0%, color-mix(in srgb, var(--color-ink) 80%, transparent) 55%, color-mix(in srgb, var(--color-ink) 35%, transparent) 100%); }
  }
</style>
@endpush

@section('content')
  {{-- HERO (briefing p.25–26) --}}
  <section class="evt-hero">
    <img src="{{ asset('img/aptk/eventos-hero-2.jpg') }}" alt="Balcão do IL BAR by APTK com as garrafas da casa">
    <div class="container-aptk">
      <div class="evt-hero-copy">
        <span class="eyebrow">Eventos</span>
        <h1 class="title-editorial">Grandes encontros merecem uma assinatura especial.</h1>
        <p>Da alta coquetelaria aos detalhes que fazem a diferença, criamos soluções personalizadas para eventos que pedem mais do que o convencional.</p>
        <p>Unimos criatividade, excelência e hospitalidade para transformar cada ocasião em uma celebração com identidade própria.</p>
        <a href="{{ $proposta }}" class="btn-aptk">Solicite uma proposta <span class="btn-arrow">→</span></a>
      </div>
    </div>
  </section>

  {{-- OCASIÕES (briefing p.26) --}}
  <section class="section">
    <div class="container-aptk">
      <div class="section-head">
        <h2 class="section-title title-editorial">Cada ocasião, infinitas possibilidades.</h2>
      </div>
      <div class="evt-seg-grid">
        <div class="evt-seg">
          <h3>Eventos Corporativos</h3>
          <p>Convenções, encontros empresariais e celebrações.</p>
        </div>
        <div class="evt-seg">
          <h3>Lançamentos &amp; Brand Experiences</h3>
          <p>Ativações, collabs e eventos de marca.</p>
        </div>
        <div class="evt-seg">
          <h3>Celebrações Privadas</h3>
          <p>Casamentos, aniversários e ocasiões especiais.</p>
        </div>
        <div class="evt-seg">
          <h3>Hospitalidade &amp; Eventos Exclusivos</h3>
          <p>Jantares, recepções e encontros personalizados.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- PORTFÓLIO (briefing p.25; leva 14: grade de collabs de
       config/collabs.php, a mesma de /collabs). Só renderiza com ao menos 1 item. --}}
  @if (count($portfolio))
    <section class="section evt-band">
      <div class="container-aptk">
        <div class="section-head">
          <span class="eyebrow">Portfólio</span>
          <h2 class="section-title title-editorial">Marcas que já brindaram com a APTK.</h2>
        </div>
        <x-portfolio-grid :itens="$portfolio" />
      </div>
    </section>
  @endif

  {{-- SOLUÇÕES (briefing p.27–28). Fotos da leva 14; 'pos' = object-position
       no recorte 16:10 (as fotos verticais). Customização usa a mesma foto do
       card Crie Seu Drink da Home. --}}
  @php
    $solucoes = [
      ['n' => '01', 'h' => 'Bar APTK', 'sub' => 'Alta coquetelaria, onde seu evento acontecer.',
       'p' => 'Levamos a assinatura da APTK ao seu evento, com coquetéis autorais, estrutura de bar e profissionais especializados.',
       'img' => 'img/aptk/eventos-bar.jpg', 'alt' => 'Bartender servindo Negroni no Bar APTK', 'pos' => '50% 50%'],
      ['n' => '02', 'id' => 'gifting', 'h' => 'Gifting & Presentes', 'sub' => 'Presentes que continuam contando histórias.',
       'p' => 'Coquetéis engarrafados, kits exclusivos e presentes personalizados para convidados, parceiros e colaboradores.',
       'img' => 'img/aptk/eventos-gifting.jpg', 'alt' => 'Kit de presente com oito coquetéis APTK', 'pos' => '50% 55%'],
      ['n' => '03', 'h' => 'Brand Experiences & Collabs', 'sub' => 'Sua marca encontra a nossa criatividade.',
       'p' => 'Projetos personalizados, ativações e colaborações que conectam marcas ao universo da coquetelaria e da hospitalidade.',
       'img' => 'img/aptk/eventos-brand.jpg', 'alt' => 'Convidadas brindando na loja da APTK', 'pos' => '50% 42%'],
      ['n' => '04', 'h' => 'Customização', 'sub' => 'Cada detalhe com a sua identidade.',
       'p' => 'Rótulos, embalagens e coquetéis personalizados para transformar produtos em lembranças únicas.',
       'img' => 'img/aptk/home-crie-seu-drink.jpg', 'alt' => 'Rótulo personalizado “Te Amo” sendo escrito à mão', 'pos' => '50% 62%'],
    ];
  @endphp
  <section class="section evt-band">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Soluções para eventos</span>
        <h2 class="section-title title-editorial">Diferentes formatos. A mesma excelência.</h2>
        <p>Da coquetelaria ao gifting, desenvolvemos soluções que conectam marcas, pessoas e ocasiões especiais. Cada projeto é pensado para traduzir a identidade de quem celebra.</p>
      </div>
      <div class="evt-sol-grid">
        @foreach ($solucoes as $s)
          <article class="media-card evt-sol" @isset($s['id']) id="{{ $s['id'] }}" @endisset>
            <div class="mc-media"><img src="{{ asset($s['img']) }}" alt="{{ $s['alt'] }}" style="object-position: {{ $s['pos'] }}" loading="lazy"></div>
            <div class="mc-body">
              <span class="mc-eyebrow">{{ $s['n'] }}</span>
              <h3 class="title-editorial">{{ $s['h'] }}</h3>
              <p class="ns-sub">{{ $s['sub'] }}</p>
              <p>{{ $s['p'] }}</p>
            </div>
          </article>
        @endforeach
      </div>
    </div>
  </section>

  {{-- NOSSO PROCESSO (briefing p.29) --}}
  <section class="section">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Nosso processo</span>
        <h2 class="section-title title-editorial">Da primeira ideia ao último brinde.</h2>
        <p>Cada evento tem sua própria história. Nosso papel é transformar ideias em projetos personalizados, combinando criatividade, planejamento e excelência em cada etapa.</p>
      </div>
      <ol class="num-steps">
        <li>
          <span class="ns-num">01</span>
          <h3>Conexão</h3>
          <p>Entendemos sua ideia, o perfil do evento e as oportunidades de criar algo especial.</p>
        </li>
        <li>
          <span class="ns-num">02</span>
          <h3>Criação</h3>
          <p>Desenvolvemos uma proposta personalizada, conectando coquetelaria, identidade e soluções sob medida.</p>
        </li>
        <li>
          <span class="ns-num">03</span>
          <h3>Planejamento</h3>
          <p>Cuidamos dos detalhes, da produção à logística, para garantir uma operação alinhada ao seu evento.</p>
        </li>
        <li>
          <span class="ns-num">04</span>
          <h3>Realização</h3>
          <p>Nossa equipe transforma o planejamento em realidade, com atenção a cada detalhe, do primeiro serviço ao último brinde.</p>
        </li>
      </ol>
    </div>
  </section>

  {{-- FECHAMENTO (briefing p.30) — "Ver a carta" saiu. --}}
  <section class="section section--dark evt-closing">
    <div class="container-aptk">
      <x-brand.sunburst class="sun" />
      <span class="eyebrow">Vamos criar juntos?</span>
      <h2 class="title-editorial">Seu próximo evento merece algo extraordinário.</h2>
      <p>Conte para nós o que você está planejando. Nossa equipe transforma ideias em soluções personalizadas, com a criatividade e a excelência da alta coquetelaria APTK.</p>
      <a href="{{ $proposta }}" class="btn-aptk">Solicitar proposta <span class="btn-arrow">→</span></a>
    </div>
  </section>
@endsection
