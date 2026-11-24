{{-- Identidade da Ice4Pros (layouts.ice4pros + ice4pros-tokens.css) nos dois
     endereços: /ice4pros na APTK e a raiz de ice4pros.com.br (MarcaHost). --}}
@extends('layouts.ice4pros')

@section('brand_title', 'Ice4Pros — O Melhor Gelo Cristal do Brasil')
@section('meta_description', 'Ice4Pros: gelo cristal gourmet, cristalino e padronizado. O gelo oficial do Guia Michelin Brasil. Cubos, esferas, diamantes e retangulares para bares, restaurantes e eventos.')
{{-- Mesma página nos dois contextos; o domínio próprio é o preferido. --}}
@section('canonical', config('marcas.marcas.ice4pros.url').'/')

@push('styles')
<style>
  /* ---- LP Ice4Pros: componentes base em ice4pros-tokens.css ---- */
  .i4p-hero { border-bottom: 1px solid var(--i4p-borda); }
  .i4p-hero .i4p-container { display: grid; grid-template-columns: 1.15fr .85fr; gap: clamp(32px, 5vw, 64px); align-items: center; padding-block: clamp(48px, 7vw, 88px); }
  .i4p-hero h1 { font-size: clamp(2.6rem, 7vw, 4.6rem); margin-bottom: 20px; max-width: 13ch; }
  .i4p-hero .lead { font-size: 1.125rem; max-width: 540px; margin-bottom: 30px; }
  .i4p-video { justify-self: center; width: min(100%, 320px); }
  .i4p-video video { width: 100%; aspect-ratio: 9 / 16; max-height: 560px; object-fit: cover; border: 1px solid var(--i4p-borda); border-radius: var(--i4p-radius); background: var(--i4p-superficie); }

  /* Selo Michelin: faixa turquesa */
  .i4p-michelin .i4p-container { display: flex; align-items: center; justify-content: center; gap: 18px; padding-block: 20px; flex-wrap: wrap; text-align: center; }
  .i4p-michelin p { margin: 0; font-family: var(--i4p-font-display); font-size: 1.05rem; letter-spacing: .12em; text-transform: uppercase; }
  .i4p-michelin .m-star { font-size: 1.3rem; line-height: 1; }

  /* Sobre */
  .i4p-sobre .i4p-container { display: grid; grid-template-columns: 1fr 1fr; gap: clamp(32px, 5vw, 64px); align-items: center; }
  .i4p-num-strip { display: flex; gap: 40px; margin-top: 30px; flex-wrap: wrap; }
  .i4p-num-strip .num { display: block; font-family: var(--i4p-font-display); font-weight: 700; font-size: 2.6rem; line-height: 1; }
  .i4p-num-strip .lbl { font-family: var(--i4p-font-display); font-size: 12px; letter-spacing: .16em; text-transform: uppercase; color: var(--i4p-texto-suave); }
  .i4p-ph { aspect-ratio: 4 / 3; border: 1px dashed var(--i4p-borda-forte); border-radius: var(--i4p-radius); display: grid; place-items: center; background: var(--i4p-superficie); color: var(--i4p-texto-suave); font-family: var(--i4p-font-display); font-size: 12px; letter-spacing: .16em; text-transform: uppercase; text-align: center; padding: 10px; }

  /* Produtos, soluções, diferenciais */
  .gelo-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
  .gelo-card .g-icon { display: block; font-size: 34px; line-height: 1; margin-bottom: 18px; }
  .sol-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
  .sol-card .s-kicker { display: block; font-family: var(--i4p-font-display); font-size: 12px; letter-spacing: .18em; text-transform: uppercase; color: var(--i4p-texto-suave); margin-bottom: 12px; }
  .sol-card .s-num { display: block; font-family: var(--i4p-font-display); font-weight: 700; font-size: 2.4rem; line-height: 1; margin-bottom: 14px; }

  /* Clientes */
  .cli-strip { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
  .cli-chip { font-family: var(--i4p-font-display); font-size: 14px; letter-spacing: .12em; text-transform: uppercase; border: 1px solid var(--i4p-borda-forte); border-radius: var(--i4p-radius); padding: 8px 18px; }

  /* Fechamento: faixa turquesa com a caveira em preto */
  .i4p-closing .i4p-container { max-width: 760px; display: flex; flex-direction: column; align-items: center; text-align: center; }
  .i4p-closing .i4p-caveira { width: min(260px, 70%); height: auto; margin-bottom: 28px; }
  .i4p-closing h2 { font-size: clamp(2rem, 5vw, 3rem); }
  .i4p-closing p { font-size: 1.125rem; max-width: 520px; margin-bottom: 30px; }
  .i4p-closing .i4p-ctas { justify-content: center; }

  @media (max-width: 980px) {
    .i4p-hero .i4p-container, .i4p-sobre .i4p-container { grid-template-columns: 1fr; }
    .gelo-grid { grid-template-columns: repeat(2, 1fr); }
    .sol-grid { grid-template-columns: 1fr; }
  }
  @media (max-width: 560px) { .gelo-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')

  {{-- HERO — vídeo vertical do cubo (mesmo arquivo da Collab) --}}
  <section class="i4p-hero">
    <div class="i4p-container">
      <div>
        <span class="i4p-eyebrow">Ice4Pros · marca da holding</span>
        <h1>O melhor gelo cristal do Brasil</h1>
        <p class="lead">Gelos perfeitos, cristalinos e padronizados — de classe mundial. Água hiperfiltrada, pedras translúcidas e a logotipagem pioneira no país, servindo a alta coquetelaria desde 2017.</p>
        <div class="i4p-ctas">
          <a href="{{ aptk_route('catalog', ['categoria' => 'ice4pros']) }}" class="i4p-btn">Conheça os produtos</a>
          <a href="{{ aptk_route('pages.show', 'collabs') }}" class="i4p-btn i4p-btn--outline">Falar com o comercial</a>
        </div>
      </div>
      <div class="i4p-video">
        <video autoplay muted loop playsinline poster="{{ asset('img/aptk/parceiros-hero.jpg') }}">
          <source src="{{ asset('video/ice4pros-cubo.mp4') }}" type="video/mp4">
        </video>
      </div>
    </div>
  </section>

  {{-- SELO MICHELIN --}}
  <section class="i4p-michelin i4p-faixa">
    <div class="i4p-container">
      <span class="m-star" aria-hidden="true">✦</span>
      <p>O gelo oficial do <strong>Guia Michelin Brasil</strong> — pelo 3º ano consecutivo</p>
      <span class="m-star" aria-hidden="true">✦</span>
    </div>
  </section>

  {{-- SOBRE / HISTÓRIA --}}
  <section class="i4p-section i4p-sobre">
    <div class="i4p-container">
      <div>
        <span class="i4p-eyebrow">Sobre a marca</span>
        <h2 class="i4p-section-title">Inovação em estado sólido, desde 2017</h2>
        <p>Fundada em 2017, a Ice4Pros trouxe grandes inovações ao mercado de coquetelaria brasileiro através da confecção de gelos perfeitos, cristalinos e padronizados.</p>
        <p>Criamos um produto de classe mundial: inovamos no tratamento e na qualidade da água utilizada, na capacidade de gerar pedras translúcidas, na forma — e também na <strong>logotipagem</strong>, conceito pioneiro trazido ao Brasil pela Ice4Pros.</p>
        <div class="i4p-num-strip">
          <div><span class="num i4p-destaque">2017</span><span class="lbl">Fundação</span></div>
          <div><span class="num i4p-destaque">3×</span><span class="lbl">Guia Michelin</span></div>
          <div><span class="num i4p-destaque">4×</span><span class="lbl">Mais duração</span></div>
        </div>
      </div>
      {{-- Foto institucional — placeholder marcado (cliente enviará). --}}
      <div class="i4p-ph"><span>Foto — produção do gelo cristal<br>em breve</span></div>
    </div>
  </section>

  {{-- PRODUTOS — as 4 linhas de gelo --}}
  <section class="i4p-section i4p-section--superficie">
    <div class="i4p-container">
      <div class="i4p-section-head">
        <span class="i4p-eyebrow">Produtos</span>
        <h2 class="i4p-section-title">Uma pedra pra cada drink</h2>
        <p>Formatos padronizados, transparência inigualável e o serviço certo pra cada taça.</p>
      </div>
      <div class="gelo-grid">
        <div class="i4p-card gelo-card">
          <span class="g-icon i4p-destaque" aria-hidden="true">◻</span>
          <h3>Cubos</h3>
          <p>Ideais para Negroni, Boulevardier, Old Fashioned e variações.</p>
        </div>
        <div class="i4p-card gelo-card">
          <span class="g-icon i4p-destaque" aria-hidden="true">◯</span>
          <h3>Esferas</h3>
          <p>Ideais para Whiskey, Negroni, Boulevardier e variações.</p>
        </div>
        <div class="i4p-card gelo-card">
          <span class="g-icon i4p-destaque" aria-hidden="true">◇</span>
          <h3>Diamantes</h3>
          <p>Ideais para Mojitos, High Balls, Caipirinhas e variações.</p>
        </div>
        <div class="i4p-card gelo-card">
          <span class="g-icon i4p-destaque" aria-hidden="true">▭</span>
          <h3>Retangulares</h3>
          <p>Ideais para Whiskey, Champagne e drinks em taças Coupe e Martini.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- SOLUÇÕES --}}
  <section class="i4p-section">
    <div class="i4p-container">
      <div class="i4p-section-head">
        <span class="i4p-eyebrow">Soluções</span>
        <h2 class="i4p-section-title">Do balcão ao grande evento</h2>
      </div>
      <div class="sol-grid">
        <div class="i4p-card sol-card">
          <span class="s-kicker">Trade</span>
          <h3>Bares & restaurantes</h3>
          <p>Abastecimento recorrente com padrão de coquetelaria de assinatura — o mesmo gelo servido nas melhores casas do país.</p>
        </div>
        <div class="i4p-card sol-card">
          <span class="s-kicker">Marca</span>
          <h3>Gelo logotipado</h3>
          <p>Sua marca carimbada na pedra: o conceito pioneiro da Ice4Pros pra ativações, lançamentos e experiências memoráveis.</p>
        </div>
        <div class="i4p-card sol-card">
          <span class="s-kicker">Eventos</span>
          <h3>Eventos & corporativo</h3>
          <p>Volume, logística e consistência pra casamentos, festivais e eventos corporativos de qualquer escala.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- PRINCIPAIS CLIENTES --}}
  <section class="i4p-section i4p-section--superficie">
    <div class="i4p-container">
      <div class="i4p-section-head">
        <span class="i4p-eyebrow">Principais clientes</span>
        <h2 class="i4p-section-title">No copo de quem entende</h2>
      </div>
      <div class="cli-strip">
        <span class="cli-chip">Braz Pizzaria</span>
        <span class="cli-chip">Gurumê</span>
        <span class="cli-chip">Hotel Emiliano</span>
        <span class="cli-chip">Guilhotina</span>
        <span class="cli-chip">Astor</span>
        <span class="cli-chip">Hotel Unique</span>
        <span class="cli-chip">Hotel Pullman</span>
        <span class="cli-chip">Pirajá</span>
        <span class="cli-chip">Cacau Show</span>
      </div>
    </div>
  </section>

  {{-- DIFERENCIAIS --}}
  <section class="i4p-section">
    <div class="i4p-container">
      <div class="i4p-section-head">
        <span class="i4p-eyebrow">Diferenciais</span>
        <h2 class="i4p-section-title">Por que gelo cristal gourmet</h2>
      </div>
      <div class="sol-grid">
        <div class="i4p-card sol-card">
          <span class="s-num i4p-destaque">01</span>
          <h3>Qualidade incomparável</h3>
          <p>As melhores matérias-primas e finalização impecável — a qualidade dos gelos Ice4Pros é incomparável.</p>
        </div>
        <div class="i4p-card sol-card">
          <span class="s-num i4p-destaque">02</span>
          <h3>Derretimento 4× mais lento</h3>
          <p>Estrutura robusta e água hiperfiltrada: durabilidade 4 vezes maior em comparação aos gelos comuns — o drink não dilui.</p>
        </div>
        <div class="i4p-card sol-card">
          <span class="s-num i4p-destaque">03</span>
          <h3>Acabamento incrível</h3>
          <p>Técnicas de finalização inovadoras e transparência inigualável — o visual do drink em outro nível.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- CTA FINAL --}}
  <section class="i4p-section i4p-closing i4p-faixa">
    <div class="i4p-container">
      <x-ice4pros.caveira />
      <h2>Leve o gelo cristal pro seu negócio</h2>
      <p>Conte o seu formato — bar, restaurante, evento ou revenda — e a gente monta a melhor condição.</p>
      <div class="i4p-ctas">
        <a href="{{ aptk_route('pages.show', 'collabs') }}" class="i4p-btn i4p-btn--escuro">Falar com o comercial</a>
        <a href="{{ aptk_route('catalog', ['categoria' => 'ice4pros']) }}" class="i4p-btn i4p-btn--outline">Ver produtos</a>
      </div>
    </div>
  </section>

@endsection
