@extends('layouts.public')

@section('title', 'APTK Spirits — Destilados autorais em pequenos lotes')

@push('styles')
<style>
  /* ---- Home: hero ---- */
  .hero { position: relative; min-height: calc(90vh - var(--header-h)); display: flex; align-items: center; overflow: hidden; }
  .hero::before { content: ""; position: absolute; top: -10%; right: -5%; width: 620px; height: 620px; background: radial-gradient(circle, var(--gold-faint) 0%, transparent 65%); pointer-events: none; }
  .hero-inner { max-width: 760px; position: relative; z-index: 1; padding-block: 48px; }
  .hero .script-line { font-family: var(--font-script); font-size: clamp(1.6rem, 4vw, var(--text-3xl)); color: var(--color-primary); margin-bottom: 12px; }
  .hero h1 { font-size: clamp(2.5rem, 7vw, 4.25rem); letter-spacing: -0.01em; margin-bottom: 24px; }
  .hero h1 em { font-style: italic; color: var(--color-text); }
  .hero p { font-size: clamp(var(--text-base), 2vw, var(--text-xl)); color: var(--color-text-muted); max-width: 560px; margin: 0 0 36px; }
  .hero-ctas { display: flex; flex-wrap: wrap; gap: 14px; }
  .hero-meta { margin-top: 48px; display: flex; gap: 40px; flex-wrap: wrap; }
  .hero-meta div { display: flex; flex-direction: column; gap: 2px; }
  .hero-meta .num { font-family: var(--font-mono); font-size: var(--text-2xl); color: var(--color-primary); }
  .hero-meta .lbl { font-size: var(--text-xs); color: var(--color-text-muted); letter-spacing: 0.1em; text-transform: uppercase; }

  /* ---- Home: categorias ---- */
  .category-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
  .category-card { padding: 28px 24px; display: flex; flex-direction: column; min-height: 200px; text-decoration: none; }
  .category-card .cat-img { height: 96px; margin-bottom: 20px; }
  .category-card h3 { font-family: var(--font-display); font-size: var(--text-xl); margin-bottom: 6px; transition: color .2s ease; }
  .category-card:hover h3 { color: var(--color-primary); }
  .category-card p { color: var(--color-text-muted); font-size: var(--text-sm); margin: 0; }
  .category-card .cat-link { margin-top: auto; padding-top: 16px; font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.14em; text-transform: uppercase; color: var(--color-primary); display: inline-flex; align-items: center; gap: 8px; }

  /* ---- Home: produto em destaque ---- */
  .featured-grid { display: grid; grid-template-columns: 1fr 1.1fr; gap: 56px; align-items: center; }
  .featured-img { aspect-ratio: 4 / 5; }
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
  .hero-inner > * { animation: riseIn .7s ease both; }
  .hero-inner > *:nth-child(2) { animation-delay: .08s; }
  .hero-inner > *:nth-child(3) { animation-delay: .16s; }
  .hero-inner > *:nth-child(4) { animation-delay: .24s; }
  .hero-inner > *:nth-child(5) { animation-delay: .32s; }

  /* ---- Home: responsivo ---- */
  @media (max-width: 980px) {
    .featured-grid { grid-template-columns: 1fr; gap: 32px; }
    .featured-img { aspect-ratio: 16 / 10; }
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
  }
</style>
@endpush

@section('content')

  {{-- HERO --}}
  <section class="hero">
    <div class="container-aptk">
      <div class="hero-inner">
        <p class="script-line">Da destilaria para a sua mesa</p>
        <h1>Destilados autorais,<br>feitos em <em>pequenos lotes</em>.</h1>
        <p>Gin, vodka, whisky e drinks prontos da APTK. Edições limitadas, rótulos personalizados e um clube para quem leva a bebida a sério.</p>
        <div class="hero-ctas">
          <a href="#" class="btn-aptk">Comprar agora</a>
          <a href="#" class="btn-aptk btn-aptk--outline">Conhecer o clube</a>
        </div>
        <div class="hero-meta">
          <div><span class="num">3</span><span class="lbl">Marcas na holding</span></div>
          <div><span class="num">100%</span><span class="lbl">Produção artesanal</span></div>
          <div><span class="num">24h</span><span class="lbl">Entrega via iFood Turbo</span></div>
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
        <a href="#" class="card-aptk category-card">
          <div class="placeholder cat-img"><span>Drinks</span></div>
          <h3>Drinks prontos</h3>
          <p>Negroni, gin tônica e clássicos, prontos para servir.</p>
          <span class="cat-link">Ver categoria →</span>
        </a>
        <a href="#" class="card-aptk category-card">
          <div class="placeholder cat-img"><span>Destilados</span></div>
          <h3>Gin, Vodka & Whisky</h3>
          <p>Nossas bases autorais para criar em casa.</p>
          <span class="cat-link">Ver categoria →</span>
        </a>
        <a href="#" class="card-aptk category-card">
          <div class="placeholder cat-img"><span>Kits</span></div>
          <h3>Kits & presentes</h3>
          <p>Combos curados com embalagem especial.</p>
          <span class="cat-link">Ver categoria →</span>
        </a>
        <a href="#" class="card-aptk category-card">
          <div class="placeholder cat-img"><span>Limitadas</span></div>
          <h3>Small batches</h3>
          <p>Edições limitadas e lotes que não se repetem.</p>
          <span class="cat-link">Ver categoria →</span>
        </a>
        <a href="#" class="card-aptk category-card">
          <div class="placeholder cat-img"><span>Marcas</span></div>
          <h3>BARIN · Ice4Pros</h3>
          <p>As marcas da holding, num só lugar.</p>
          <span class="cat-link">Ver categoria →</span>
        </a>
        <a href="#" class="card-aptk category-card">
          <div class="placeholder cat-img"><span>Acessórios</span></div>
          <h3>Acessórios & corporativo</h3>
          <p>Coqueteleira, copos e linha para empresas.</p>
          <span class="cat-link">Ver categoria →</span>
        </a>
      </div>
    </div>
  </section>

  {{-- PRODUTO EM DESTAQUE --}}
  <section class="section" style="padding-top:0;">
    <div class="container-aptk">
      <div class="featured-grid">
        <div class="placeholder featured-img"><span>Garrafa 750ml</span></div>
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
            <a href="#" class="btn-aptk">Adicionar ao carrinho</a>
          </div>
        </div>
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
          <a href="#" class="btn-aptk btn-aptk--outline">Ver a loja BARIN</a>
        </div>
        <div class="card-aptk brand-card">
          <div class="placeholder brand-logo"><span>ICE4PROS</span></div>
          <span class="brand-tag">B2B · Gelo e insumos para o trade</span>
          <h3>Ice4Pros</h3>
          <p>Capacidade produtiva e qualidade certificada para bares, eventos e distribuidores.</p>
          <a href="#" class="btn-aptk btn-aptk--outline">Pedir orçamento</a>
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
          <a href="#" class="btn-aptk btn-aptk--outline btn-aptk--block">Assinar</a>
        </div>
        <div class="plan-card is-featured">
          <span class="plan-badge">Mais assinado</span>
          <p class="plan-name">Clássicos</p>
          <p class="plan-kicker">Curadoria mensal</p>
          <p class="plan-price">R$ 149<small>/mês</small></p>
          <p class="plan-cycle">cobrança mensal</p>
          <a href="#" class="btn-aptk btn-aptk--block">Assinar agora</a>
        </div>
        <div class="plan-card">
          <p class="plan-name">Premium</p>
          <p class="plan-kicker">Acesso especial</p>
          <p class="plan-price">R$ 249<small>/mês</small></p>
          <p class="plan-cycle">cobrança mensal</p>
          <a href="#" class="btn-aptk btn-aptk--outline btn-aptk--block">Assinar</a>
        </div>
        <div class="plan-card">
          <p class="plan-name">Small Batch</p>
          <p class="plan-kicker">Edições artesanais</p>
          <p class="plan-price">R$ 329<small>/mês</small></p>
          <p class="plan-cycle">cobrança mensal</p>
          <a href="#" class="btn-aptk btn-aptk--outline btn-aptk--block">Assinar</a>
        </div>
        <div class="plan-card">
          <p class="plan-name">Corporativo</p>
          <p class="plan-kicker">Empresas e equipes</p>
          <p class="plan-price">Sob<small> consulta</small></p>
          <p class="plan-cycle">faturamento PJ</p>
          <a href="#" class="btn-aptk btn-aptk--outline btn-aptk--block">Falar com time</a>
        </div>
      </div>
    </div>
  </section>

@endsection