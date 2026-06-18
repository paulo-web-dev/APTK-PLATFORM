@extends('layouts.public')

@section('title', 'APTK Spirits — Drinks & histórias pra contar')

@push('styles')
<style>
  /* ---- Home: hero (split: texto + foto) ---- */
  .hero { position: relative; min-height: calc(92vh - var(--header-h)); display: flex; align-items: center; overflow: hidden; }
  .hero::before { content: ""; position: absolute; top: -10%; right: -5%; width: 620px; height: 620px; background: radial-gradient(circle, var(--gold-faint) 0%, transparent 65%); pointer-events: none; }
  .hero-inner { width: 100%; position: relative; z-index: 1; padding-block: 56px; display: grid; grid-template-columns: 1.05fr .95fr; gap: 56px; align-items: center; }
  .hero-copy { max-width: 620px; }
  .hero .script-line { font-family: var(--font-script); font-size: clamp(1.6rem, 4vw, var(--text-3xl)); color: var(--color-primary); margin-bottom: 12px; }
  .hero h1 { font-size: clamp(2.5rem, 6vw, 4rem); letter-spacing: -0.01em; margin-bottom: 24px; }
  .hero h1 em { font-style: italic; color: var(--color-text); }
  .hero p { font-size: clamp(var(--text-base), 2vw, var(--text-xl)); color: var(--color-text-muted); max-width: 540px; margin: 0 0 36px; }
  .hero-ctas { display: flex; flex-wrap: wrap; gap: 14px; }
  .hero-meta { margin-top: 48px; display: flex; gap: 40px; flex-wrap: wrap; }
  .hero-meta div { display: flex; flex-direction: column; gap: 2px; }
  .hero-meta .num { font-family: var(--font-mono); font-size: var(--text-2xl); color: var(--color-primary); }
  .hero-meta .lbl { font-size: var(--text-xs); color: var(--color-text-muted); letter-spacing: 0.1em; text-transform: uppercase; }

  .hero-media { position: relative; }
  .hero-media img { display: block; width: 100%; aspect-ratio: 4 / 5; object-fit: cover; object-position: center top; border-radius: var(--radius-lg); border: 1px solid var(--color-border); box-shadow: var(--shadow-card); }
  .hero-media .photo-tag { position: absolute; left: 16px; bottom: 16px; background: color-mix(in srgb, var(--color-ink) 70%, transparent); color: var(--color-cream); font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.14em; text-transform: uppercase; padding: 7px 12px; border-radius: var(--radius-sm); backdrop-filter: blur(4px); }

  /* ---- Home: categorias ---- */
  .category-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
  .category-card { padding: 0; display: flex; flex-direction: column; min-height: 200px; text-decoration: none; overflow: hidden; }
  .category-card .cat-img { display: block; width: 100%; height: 180px; object-fit: cover; }
  .category-card .cat-body { padding: 22px 24px 26px; display: flex; flex-direction: column; flex: 1; }
  .category-card h3 { font-family: var(--font-display); font-size: var(--text-xl); margin-bottom: 6px; transition: color .2s ease; }
  .category-card:hover h3 { color: var(--color-primary); }
  .category-card p { color: var(--color-text-muted); font-size: var(--text-sm); margin: 0; }
  .category-card .cat-link { margin-top: auto; padding-top: 16px; font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.14em; text-transform: uppercase; color: var(--color-primary); display: inline-flex; align-items: center; gap: 8px; }

  /* ---- Home: produto em destaque ---- */
  .featured-grid { display: grid; grid-template-columns: 1fr 1.1fr; gap: 56px; align-items: center; }
  .featured-img { display: block; width: 100%; aspect-ratio: 4 / 5; object-fit: cover; object-position: center; border-radius: var(--radius-lg); border: 1px solid var(--color-border); box-shadow: var(--shadow-card); }
  .featured-body .badge-aptk { margin-bottom: 18px; }
  .featured-body .product-name { font-size: clamp(2.5rem, 6vw, 3.5rem); margin-bottom: 16px; }
  .featured-body .sub { font-family: var(--font-display); font-style: italic; font-size: var(--text-xl); color: var(--color-text); margin: 0 0 18px; }
  .featured-body p.desc { color: var(--color-text-muted); margin: 0 0 28px; max-width: 460px; }
  .featured-specs { display: flex; gap: 32px; margin-bottom: 32px; flex-wrap: wrap; }
  .featured-specs div { display: flex; flex-direction: column; gap: 3px; }
  .featured-specs .k { font-size: var(--text-xs); color: var(--color-text-muted); letter-spacing: 0.1em; text-transform: uppercase; }
  .featured-specs .v { font-family: var(--font-mono); color: var(--color-text); font-size: var(--text-base); }
  .featured-buy { display: flex; align-items: center; gap: 24px; flex-wrap: wrap; }
  .featured-buy .price-tag { font-size: var(--text-2xl); }

  /* ---- Home: faixa da loja física (escura, full-bleed) ---- */
  .loja-band { position: relative; overflow: hidden; border-block: 1px solid var(--color-border); }
  .loja-band .loja-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
  .loja-band .loja-overlay { position: absolute; inset: 0; background: linear-gradient(90deg, color-mix(in srgb, var(--color-ink) 88%, transparent) 0%, color-mix(in srgb, var(--color-ink) 62%, transparent) 55%, color-mix(in srgb, var(--color-ink) 26%, transparent) 100%); }
  .loja-band .container-aptk { position: relative; z-index: 1; }
  .loja-band .loja-copy { max-width: 540px; }
  .loja-band .eyebrow { margin-bottom: 16px; }
  .loja-band h2 { font-family: var(--font-display); font-size: clamp(2rem, 5vw, 3rem); color: var(--color-cream); margin: 0 0 16px; }
  .loja-band p { color: color-mix(in srgb, var(--color-cream) 82%, transparent); font-size: var(--text-lg); line-height: 1.7; margin: 0 0 28px; }

  /* ---- Home: marcas ---- */
  .brand-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }
  .brand-card { padding: 40px; display: flex; flex-direction: column; min-height: 280px; }
  .brand-card .brand-logo { height: 64px; width: 100%; margin-bottom: 28px; }
  .brand-card h3 { font-size: var(--text-2xl); margin-bottom: 10px; }
  .brand-card p { color: var(--color-text-muted); margin: 0 0 24px; flex-grow: 1; }
  .brand-card .brand-tag { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.12em; text-transform: uppercase; color: var(--color-text-muted); margin-bottom: 18px; }

  /* ---- Home: clube ---- */
  .club { background: var(--color-bg-card); border-block: 1px solid var(--color-border); }
  .plan-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; align-items: stretch; }
  .plan-card { position: relative; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 28px 22px; display: flex; flex-direction: column; transition: border-color .25s ease, transform .25s ease; }
  .plan-card:hover { border-color: var(--color-primary-muted); transform: translateY(-3px); }
  .plan-card.is-featured { border-color: var(--color-primary); box-shadow: var(--shadow-glow); }
  .plan-card .plan-name { font-family: var(--font-script); font-size: var(--text-2xl); color: var(--color-primary); margin-bottom: 4px; }
  .plan-card .plan-kicker { font-size: var(--text-xs); color: var(--color-text-muted); letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 20px; min-height: 30px; }
  .plan-card .plan-price { font-family: var(--font-mono); color: var(--color-text); font-size: var(--text-2xl); margin-bottom: 2px; }
  .plan-card .plan-price small { font-size: var(--text-sm); color: var(--color-text-muted); }
  .plan-card .plan-cycle { font-size: var(--text-xs); color: var(--color-text-muted); margin-bottom: 22px; }
  .plan-card .btn-aptk { margin-top: auto; }
  .plan-badge { position: absolute; top: -11px; left: 50%; transform: translateX(-50%); background: var(--color-primary); color: var(--color-text-inverse); font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.14em; text-transform: uppercase; padding: 3px 12px; border-radius: var(--radius-sm); white-space: nowrap; }

  /* ---- Home: animação de entrada do hero ---- */
  @keyframes riseIn { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
  .hero-copy > *, .hero-media { animation: riseIn .7s ease both; }
  .hero-copy > *:nth-child(2) { animation-delay: .08s; }
  .hero-copy > *:nth-child(3) { animation-delay: .16s; }
  .hero-copy > *:nth-child(4) { animation-delay: .24s; }
  .hero-media { animation-delay: .18s; }

  /* ---- Home: responsivo ---- */
  @media (max-width: 980px) {
    .hero { min-height: auto; }
    .hero-inner { grid-template-columns: 1fr; gap: 36px; }
    .hero-media { max-width: 440px; }
    .featured-grid { grid-template-columns: 1fr; gap: 32px; }
    .featured-img { aspect-ratio: 16 / 11; }
    .plan-grid { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 820px) {
    .category-grid { grid-template-columns: repeat(2, 1fr); }
    .brand-grid { grid-template-columns: 1fr; }
    .hero-meta { gap: 28px; }
  }
  @media (max-width: 520px) {
    .category-grid { grid-template-columns: 1fr; }
    .plan-grid { grid-template-columns: 1fr; }
    .hero-ctas .btn-aptk { width: 100%; }
    .hero-media { max-width: none; }
  }
</style>
@endpush

@section('content')

  {{-- HERO --}}
  <section class="hero">
    <div class="container-aptk">
      <div class="hero-inner">
        <div class="hero-copy">
          <p class="script-line">Drinks &amp; histórias pra contar</p>
          <h1>Drinks autorais,<br>feitos em <em>pequenos lotes</em>.</h1>
          <p>Gin, vodka, whisky e drinks engarrafados da APTK. Edições limitadas, rótulos personalizados e um clube para quem leva a bebida a sério.</p>
          <div class="hero-ctas">
            <a href="{{ route('catalog') }}" class="btn-aptk">Comprar agora</a>
            <a href="{{ route('pages.show', 'clube') }}" class="btn-aptk btn-aptk--outline">Conhecer o clube</a>
          </div>
          <div class="hero-meta">
            <div><span class="num">3</span><span class="lbl">Marcas na holding</span></div>
            <div><span class="num">100%</span><span class="lbl">Produção artesanal</span></div>
            <div><span class="num">24h</span><span class="lbl">Entrega via iFood Turbo</span></div>
          </div>
        </div>
        <div class="hero-media">
          <img src="{{ asset('img/aptk/hero-bartender.jpg') }}" alt="Bartender da APTK preparando um Negroni no balcão" fetchpriority="high">
          <span class="photo-tag">No balcão APTK</span>
        </div>
      </div>
    </div>
  </section>

  {{-- CATEGORIAS --}}
  <section class="section">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Catálogo</span>
        <h2 class="section-title">O que você procura</h2>
        <p>Do drink pronto para servir aos lotes limitados que saem só uma vez.</p>
      </div>
      <div class="category-grid">
        <a href="{{ route('catalog') }}" class="card-aptk category-card">
          <img src="{{ asset('img/aptk/cat-dry-martini.jpg') }}" class="cat-img" alt="Dry Martini servido" loading="lazy">
          <div class="cat-body">
            <h3>Drinks prontos</h3>
            <p>Negroni, dry martini e clássicos, prontos para servir.</p>
            <span class="cat-link">Ver categoria →</span>
          </div>
        </a>
        <a href="{{ route('catalog') }}" class="card-aptk category-card">
          <img src="{{ asset('img/aptk/cat-fitzgerald.jpg') }}" class="cat-img" alt="Garrafa de gin Fitzgerald da APTK" loading="lazy">
          <div class="cat-body">
            <h3>Gin, Vodka &amp; Whisky</h3>
            <p>Nossas bases autorais para criar em casa.</p>
            <span class="cat-link">Ver categoria →</span>
          </div>
        </a>
        <a href="{{ route('catalog') }}" class="card-aptk category-card">
          <img src="{{ asset('img/aptk/cat-evento.jpg') }}" class="cat-img" alt="Mesa posta com drinks em um evento APTK" loading="lazy">
          <div class="cat-body">
            <h3>Kits &amp; presentes</h3>
            <p>Combos curados com embalagem especial.</p>
            <span class="cat-link">Ver categoria →</span>
          </div>
        </a>
        <a href="{{ route('catalog') }}" class="card-aptk category-card">
          <img src="{{ asset('img/aptk/cat-cosmopolitan.jpg') }}" class="cat-img" alt="Drink autoral servido ao lado da garrafa" loading="lazy">
          <div class="cat-body">
            <h3>Small batches</h3>
            <p>Edições limitadas e lotes que não se repetem.</p>
            <span class="cat-link">Ver categoria →</span>
          </div>
        </a>
        <a href="{{ route('catalog') }}" class="card-aptk category-card">
          <img src="{{ asset('img/aptk/cat-pessoas.jpg') }}" class="cat-img" alt="Pessoas brindando com drinks da APTK" loading="lazy">
          <div class="cat-body">
            <h3>BARIN · Ice4Pros</h3>
            <p>As marcas da holding, num só lugar.</p>
            <span class="cat-link">Ver categoria →</span>
          </div>
        </a>
        <a href="{{ route('catalog') }}" class="card-aptk category-card">
          <img src="{{ asset('img/aptk/cat-loja.jpg') }}" class="cat-img" alt="Balcão da loja física da APTK" loading="lazy">
          <div class="cat-body">
            <h3>Acessórios &amp; corporativo</h3>
            <p>Coqueteleira, copos e linha para empresas.</p>
            <span class="cat-link">Ver categoria →</span>
          </div>
        </a>
      </div>
    </div>
  </section>

  {{-- PRODUTO EM DESTAQUE --}}
  <section class="section" style="padding-top:0;">
    <div class="container-aptk">
      <div class="featured-grid">
        <img src="{{ asset('img/aptk/featured-negroni.jpg') }}" class="featured-img" alt="Negroni Clássico da APTK servido com laranja" loading="lazy">
        <div class="featured-body">
          <span class="badge-aptk">Edição limitada</span>
          <p class="product-name">Negroni Clássico</p>
          <p class="sub">Amargo na medida, redondo no final.</p>
          <p class="desc">Engarrafado em lotes pequenos com gin autoral APTK, vermute e bitter de laranja. Pronto para servir com gelo — sem pressa, sem atalho.</p>
          <div class="featured-specs">
            <div><span class="k">Teor</span><span class="v">28% vol.</span></div>
            <div><span class="k">Volume</span><span class="v">750 ml</span></div>
            <div><span class="k">Lote</span><span class="v">#014</span></div>
          </div>
          <div class="featured-buy">
            <span class="price-tag">R$ 189,<small>90</small></span>
            <a href="{{ route('catalog') }}" class="btn-aptk">Comprar na loja</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- FAIXA DA LOJA FÍSICA --}}
  <section class="section loja-band on-dark">
    <img src="{{ asset('img/aptk/loja-aptk.jpg') }}" class="loja-bg" alt="Loja física da APTK Spirits no shopping" loading="lazy">
    <div class="loja-overlay"></div>
    <div class="container-aptk">
      <div class="loja-copy">
        <span class="eyebrow">Loja física</span>
        <h2>Onde a alquimia acontece</h2>
        <p>Visite o balcão APTK: drinks engarrafados, edições limitadas e a curadoria completa da casa — para levar para casa ou viver ali mesmo.</p>
        <a href="{{ route('pages.show', 'sobre') }}" class="btn-aptk">Conhecer a APTK</a>
      </div>
    </div>
  </section>

  {{-- MARCAS --}}
  <section class="section" style="padding-top:0;">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Nossas marcas</span>
        <h2 class="section-title">Uma holding, três mundos</h2>
      </div>
      <div class="brand-grid">
        <div class="card-aptk brand-card">
          <div class="placeholder brand-logo"><span>BARIN</span></div>
          <span class="brand-tag">Bebidas artesanais</span>
          <h3>BARIN</h3>
          <p>A linha artesanal da holding, com receitas próprias e identidade de bar.</p>
          <a href="{{ route('catalog') }}" class="btn-aptk btn-aptk--outline">Ver a loja BARIN</a>
        </div>
        <div class="card-aptk brand-card">
          <div class="placeholder brand-logo"><span>ICE4PROS</span></div>
          <span class="brand-tag">B2B · Gelo e insumos para o trade</span>
          <h3>Ice4Pros</h3>
          <p>Capacidade produtiva e qualidade certificada para bares, eventos e distribuidores.</p>
          <a href="{{ route('pages.show', 'marcas') }}" class="btn-aptk btn-aptk--outline">Pedir orçamento</a>
        </div>
      </div>
    </div>
  </section>

  {{-- CLUBE --}}
  <section class="section club">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Clube APTK</span>
        <h2 class="section-title">Receba em casa, todo mês</h2>
        <p>Cinco planos de assinatura. Recorrência simples, brindes e acesso antecipado aos lotes.</p>
      </div>
      <div class="plan-grid">
        <div class="plan-card">
          <p class="plan-name">Descoberta</p>
          <p class="plan-kicker">Entrada no universo APTK</p>
          <p class="plan-price">R$ 89<small>/mês</small></p>
          <p class="plan-cycle">cobrança mensal</p>
          <a href="{{ route('pages.show', 'clube') }}" class="btn-aptk btn-aptk--outline btn-aptk--block">Assinar</a>
        </div>
        <div class="plan-card is-featured">
          <span class="plan-badge">Mais assinado</span>
          <p class="plan-name">Clássicos</p>
          <p class="plan-kicker">Curadoria mensal</p>
          <p class="plan-price">R$ 149<small>/mês</small></p>
          <p class="plan-cycle">cobrança mensal</p>
          <a href="{{ route('pages.show', 'clube') }}" class="btn-aptk btn-aptk--block">Assinar agora</a>
        </div>
        <div class="plan-card">
          <p class="plan-name">Premium</p>
          <p class="plan-kicker">Acesso especial</p>
          <p class="plan-price">R$ 249<small>/mês</small></p>
          <p class="plan-cycle">cobrança mensal</p>
          <a href="{{ route('pages.show', 'clube') }}" class="btn-aptk btn-aptk--outline btn-aptk--block">Assinar</a>
        </div>
        <div class="plan-card">
          <p class="plan-name">Small Batch</p>
          <p class="plan-kicker">Edições artesanais</p>
          <p class="plan-price">R$ 329<small>/mês</small></p>
          <p class="plan-cycle">cobrança mensal</p>
          <a href="{{ route('pages.show', 'clube') }}" class="btn-aptk btn-aptk--outline btn-aptk--block">Assinar</a>
        </div>
        <div class="plan-card">
          <p class="plan-name">Corporativo</p>
          <p class="plan-kicker">Empresas e equipes</p>
          <p class="plan-price">Sob<small> consulta</small></p>
          <p class="plan-cycle">faturamento PJ</p>
          <a href="{{ route('pages.show', 'eventos') }}" class="btn-aptk btn-aptk--outline btn-aptk--block">Falar com time</a>
        </div>
      </div>
    </div>
  </section>

@endsection
