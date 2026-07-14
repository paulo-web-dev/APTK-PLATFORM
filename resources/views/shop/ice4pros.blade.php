@extends('layouts.public')

@section('title', 'Ice4Pros — O Melhor Gelo Cristal do Brasil · APTK Spirits')
@section('meta_description', 'Ice4Pros: gelo cristal gourmet, cristalino e padronizado. O gelo oficial do Guia Michelin Brasil. Cubos, esferas, diamantes e retangulares para bares, restaurantes e eventos.')

@push('styles')
<style>
  /* ---- LP Ice4Pros (one page, dark) ---- */
  .i4p-hero { position: relative; overflow: hidden; border-bottom: 1px solid var(--color-border); }
  .i4p-hero::before { content: ""; position: absolute; top: -20%; left: -8%; width: 520px; height: 520px; background: radial-gradient(circle, var(--gold-faint) 0%, transparent 65%); pointer-events: none; }
  .i4p-hero .container-aptk { position: relative; z-index: 1; display: grid; grid-template-columns: 1.15fr .85fr; gap: clamp(32px, 5vw, 64px); align-items: center; padding-block: clamp(48px, 7vw, 72px); }
  .i4p-hero .eyebrow { margin-bottom: 14px; }
  .i4p-hero h1 { font-size: clamp(2.2rem, 5.5vw, 3.5rem); letter-spacing: -0.01em; margin: 0 0 16px; max-width: 15ch; }
  .i4p-hero p.lead { font-size: var(--text-lg); color: var(--color-text-muted); max-width: 540px; line-height: 1.7; margin: 0 0 28px; }
  .i4p-hero .ctas { display: flex; gap: 14px; flex-wrap: wrap; }
  .i4p-video { position: relative; justify-self: center; width: min(100%, 320px); }
  .i4p-video video { display: block; width: 100%; aspect-ratio: 9 / 16; max-height: 540px; object-fit: cover; border-radius: var(--radius-lg); border: 1px solid var(--color-border); box-shadow: var(--shadow-card); background: #0D0A06; }

  /* Selo Michelin */
  .i4p-michelin { border-block: 1px solid var(--color-primary-muted); background: color-mix(in srgb, var(--color-primary) 5%, transparent); }
  .i4p-michelin .container-aptk { display: flex; align-items: center; justify-content: center; gap: 18px; padding-block: 22px; flex-wrap: wrap; text-align: center; }
  .i4p-michelin .m-star { font-size: var(--text-2xl); color: var(--color-primary); }
  .i4p-michelin p { margin: 0; font-family: var(--font-mono); font-size: var(--text-sm); letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-text); }
  .i4p-michelin p strong { color: var(--color-primary); }

  /* Sobre / história */
  .i4p-sobre .container-aptk { display: grid; grid-template-columns: 1fr 1fr; gap: clamp(32px, 5vw, 64px); align-items: center; }
  .i4p-sobre p { color: var(--color-text-muted); line-height: 1.8; margin: 0 0 16px; }
  .i4p-sobre .num-strip { display: flex; gap: 36px; margin-top: 26px; flex-wrap: wrap; }
  .i4p-sobre .num-strip .num { font-family: var(--font-mono); font-size: var(--text-2xl); color: var(--color-primary); display: block; }
  .i4p-sobre .num-strip .lbl { font-size: var(--text-xs); color: var(--color-text-muted); letter-spacing: 0.1em; text-transform: uppercase; }
  .i4p-ph { aspect-ratio: 4 / 3; border: 1px solid var(--color-border); border-radius: var(--radius-lg); display: grid; place-items: center; background: repeating-linear-gradient(45deg, var(--color-bg-card), var(--color-bg-card) 12px, var(--color-bg) 12px, var(--color-bg) 24px); color: var(--color-text-muted); font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.14em; text-transform: uppercase; text-align: center; padding: 10px; }

  /* Produtos (linhas de gelo) */
  .gelo-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
  .gelo-card { background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 26px 22px; transition: border-color .25s ease, transform .25s ease; }
  .gelo-card:hover { border-color: var(--color-primary-muted); transform: translateY(-3px); }
  .gelo-card .g-icon { font-size: 30px; color: var(--color-primary); margin-bottom: 14px; display: block; line-height: 1; }
  .gelo-card h3 { font-family: var(--font-display); font-size: var(--text-xl); margin: 0 0 8px; }
  .gelo-card p { color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.6; margin: 0; }

  /* Soluções + diferenciais */
  .band-card { background: var(--color-bg-card); border-block: 1px solid var(--color-border); }
  .sol-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
  .sol-card { border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 28px 24px; background: var(--color-bg); }
  .sol-card .s-kicker { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.14em; text-transform: uppercase; color: var(--color-primary); }
  .sol-card h3 { font-family: var(--font-display); font-size: var(--text-xl); margin: 12px 0 8px; }
  .sol-card p { color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.7; margin: 0; }

  /* Clientes */
  .cli-strip { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
  .cli-chip { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-text-muted); border: 1px solid var(--color-border); border-radius: 100px; padding: 9px 18px; }

  /* Fechamento */
  .i4p-closing { text-align: center; border-top: 1px solid var(--color-border); }
  .i4p-closing .container-aptk { max-width: 720px; display: flex; flex-direction: column; align-items: center; padding-block: clamp(48px, 7vw, 72px); }
  .i4p-closing h2 { font-family: var(--font-display); font-size: clamp(1.9rem, 5vw, 2.8rem); margin: 0 0 14px; }
  .i4p-closing p { color: var(--color-text-muted); font-size: var(--text-lg); margin: 0 0 28px; max-width: 520px; }
  .i4p-closing .ctas { display: flex; gap: 14px; flex-wrap: wrap; justify-content: center; }

  @media (max-width: 980px) {
    .i4p-hero .container-aptk, .i4p-sobre .container-aptk { grid-template-columns: 1fr; }
    .gelo-grid { grid-template-columns: repeat(2, 1fr); }
    .sol-grid { grid-template-columns: 1fr; }
  }
  @media (max-width: 560px) { .gelo-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')

  {{-- HERO — vídeo vertical do cubo (mesmo arquivo da Collab) --}}
  <section class="i4p-hero">
    <div class="container-aptk">
      <div>
        <span class="eyebrow">Ice4Pros · marca da holding</span>
        <h1>O melhor gelo cristal do Brasil</h1>
        <p class="lead">Gelos perfeitos, cristalinos e padronizados — de classe mundial. Água hiperfiltrada, pedras translúcidas e a logotipagem pioneira no país, servindo a alta coquetelaria desde 2017.</p>
        <div class="ctas">
          <a href="{{ route('catalog', ['categoria' => 'ice4pros']) }}" class="btn-aptk">Conheça os produtos</a>
          <a href="{{ route('pages.show', 'collabs') }}" class="btn-aptk btn-aptk--outline">Falar com o comercial</a>
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
  <section class="i4p-michelin">
    <div class="container-aptk">
      <span class="m-star">✦</span>
      <p>O gelo oficial do <strong>Guia Michelin Brasil</strong> — pelo 3º ano consecutivo</p>
      <span class="m-star">✦</span>
    </div>
  </section>

  {{-- SOBRE / HISTÓRIA --}}
  <section class="section i4p-sobre">
    <div class="container-aptk">
      <div>
        <span class="eyebrow">Sobre a marca</span>
        <h2 class="section-title">Inovação em estado sólido, desde 2017</h2>
        <p>Fundada em 2017, a Ice4Pros trouxe grandes inovações ao mercado de coquetelaria brasileiro através da confecção de gelos perfeitos, cristalinos e padronizados.</p>
        <p>Criamos um produto de classe mundial: inovamos no tratamento e na qualidade da água utilizada, na capacidade de gerar pedras translúcidas, na forma — e também na <strong>logotipagem</strong>, conceito pioneiro trazido ao Brasil pela Ice4Pros.</p>
        <div class="num-strip">
          <div><span class="num">2017</span><span class="lbl">Fundação</span></div>
          <div><span class="num">3×</span><span class="lbl">Guia Michelin</span></div>
          <div><span class="num">4×</span><span class="lbl">Mais duração</span></div>
        </div>
      </div>
      {{-- Foto institucional — placeholder marcado (cliente enviará). --}}
      <div class="i4p-ph"><span>Foto — produção do gelo cristal<br>em breve</span></div>
    </div>
  </section>

  {{-- PRODUTOS — as 4 linhas de gelo --}}
  <section class="section band-card">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Produtos</span>
        <h2 class="section-title">Uma pedra pra cada drink</h2>
        <p>Formatos padronizados, transparência inigualável e o serviço certo pra cada taça.</p>
      </div>
      <div class="gelo-grid">
        <div class="gelo-card">
          <span class="g-icon">◻</span>
          <h3>Cubos</h3>
          <p>Ideais para Negroni, Boulevardier, Old Fashioned e variações.</p>
        </div>
        <div class="gelo-card">
          <span class="g-icon">◯</span>
          <h3>Esferas</h3>
          <p>Ideais para Whiskey, Negroni, Boulevardier e variações.</p>
        </div>
        <div class="gelo-card">
          <span class="g-icon">◇</span>
          <h3>Diamantes</h3>
          <p>Ideais para Mojitos, High Balls, Caipirinhas e variações.</p>
        </div>
        <div class="gelo-card">
          <span class="g-icon">▭</span>
          <h3>Retangulares</h3>
          <p>Ideais para Whiskey, Champagne e drinks em taças Coupe e Martini.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- SOLUÇÕES --}}
  <section class="section">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Soluções</span>
        <h2 class="section-title">Do balcão ao grande evento</h2>
      </div>
      <div class="sol-grid">
        <div class="sol-card">
          <span class="s-kicker">Trade</span>
          <h3>Bares & restaurantes</h3>
          <p>Abastecimento recorrente com padrão de coquetelaria de assinatura — o mesmo gelo servido nas melhores casas do país.</p>
        </div>
        <div class="sol-card">
          <span class="s-kicker">Marca</span>
          <h3>Gelo logotipado</h3>
          <p>Sua marca carimbada na pedra: o conceito pioneiro da Ice4Pros pra ativações, lançamentos e experiências memoráveis.</p>
        </div>
        <div class="sol-card">
          <span class="s-kicker">Eventos</span>
          <h3>Eventos & corporativo</h3>
          <p>Volume, logística e consistência pra casamentos, festivais e eventos corporativos de qualquer escala.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- PRINCIPAIS CLIENTES --}}
  <section class="section band-card">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Principais clientes</span>
        <h2 class="section-title">No copo de quem entende</h2>
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
  <section class="section">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Diferenciais</span>
        <h2 class="section-title">Por que gelo cristal gourmet</h2>
      </div>
      <div class="sol-grid">
        <div class="sol-card">
          <span class="s-kicker">01</span>
          <h3>Qualidade incomparável</h3>
          <p>As melhores matérias-primas e finalização impecável — a qualidade dos gelos Ice4Pros é incomparável.</p>
        </div>
        <div class="sol-card">
          <span class="s-kicker">02</span>
          <h3>Derretimento 4× mais lento</h3>
          <p>Estrutura robusta e água hiperfiltrada: durabilidade 4 vezes maior em comparação aos gelos comuns — o drink não dilui.</p>
        </div>
        <div class="sol-card">
          <span class="s-kicker">03</span>
          <h3>Acabamento incrível</h3>
          <p>Técnicas de finalização inovadoras e transparência inigualável — o visual do drink em outro nível.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- CTA FINAL --}}
  <section class="i4p-closing">
    <div class="container-aptk">
      <x-brand.sunburst style="width:110px; color:var(--color-primary); margin-bottom:18px;" />
      <h2>Leve o gelo cristal pro seu negócio</h2>
      <p>Conte o seu formato — bar, restaurante, evento ou revenda — e a gente monta a melhor condição.</p>
      <div class="ctas">
        <a href="{{ route('pages.show', 'collabs') }}" class="btn-aptk">Falar com o comercial</a>
        <a href="{{ route('catalog', ['categoria' => 'ice4pros']) }}" class="btn-aptk btn-aptk--outline">Ver produtos</a>
      </div>
    </div>
  </section>

@endsection
