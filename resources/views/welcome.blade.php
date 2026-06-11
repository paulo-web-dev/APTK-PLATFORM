<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>APTK Spirits — Destilados autorais em pequenos lotes</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Dancing+Script:wght@600;700&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

  <style>
  /* ===================================================================
     APTK — IDENTIDADE VISUAL
     -------------------------------------------------------------------
     BLOCO A (TOKENS + COMPONENTES) → vira o public/css/aptk.css
     BLOCO B (LAYOUT DA HOMEPAGE)   → vira o CSS da view shop/dashboard
     Regra: nenhuma cor hardcoded fora do :root — tudo via var().
     =================================================================== */

  /* ---------- BLOCO A.1 — TOKENS (:root) ---------- */
  :root {
    /* Fundos */
    --color-bg:           #0D0D0D;
    --color-bg-card:      #161616;
    --color-bg-elevated:  #1F1F1F;
    --color-border:       #2A2A2A;

    /* Dourado — cor primária APTK */
    --color-primary:      #D4A017;
    --color-primary-hover:#E8B820;
    --color-primary-muted:#8B6914;

    /* Texto */
    --color-text:         #F0EAD6;
    --color-text-muted:   #9A9082;
    --color-text-inverse: #0D0D0D;

    /* Status */
    --color-success:      #4CAF7D;
    --color-warning:      #D4A017;
    --color-danger:       #E05252;
    --color-info:         #5B8FD4;

    /* Tipografia */
    --font-display:  'Playfair Display', Georgia, serif;
    --font-script:   'Dancing Script', cursive;
    --font-body:     'Inter', system-ui, sans-serif;
    --font-mono:     'JetBrains Mono', monospace;

    /* Escala tipográfica */
    --text-xs:   0.75rem;
    --text-sm:   0.875rem;
    --text-base: 1rem;
    --text-lg:   1.125rem;
    --text-xl:   1.25rem;
    --text-2xl:  1.5rem;
    --text-3xl:  1.875rem;
    --text-4xl:  2.25rem;
    --text-5xl:  3rem;

    /* Raios */
    --radius-sm:  4px;
    --radius-md:  8px;
    --radius-lg:  12px;
    --radius-xl:  20px;

    /* Sombras */
    --shadow-card:   0 4px 24px rgba(0,0,0,0.6);
    --shadow-glow:   0 0 20px rgba(212,160,23,0.15);

    /* Extras tokenizados (derivados da paleta — usados em hover/overlay) */
    --gold-faint:    rgba(212,160,23,0.08);
    --gold-line:     rgba(212,160,23,0.30);
    --overlay-dark:  rgba(0,0,0,0.55);

    /* Layout */
    --container:     1200px;
    --header-h:      76px;
  }

  /* ---------- BLOCO A.2 — RESET / BASE ---------- */
  *, *::before, *::after { box-sizing: border-box; }

  html { scroll-behavior: smooth; }

  body {
    margin: 0;
    background-color: var(--color-bg);
    color: var(--color-text);
    font-family: var(--font-body);
    font-weight: 400;
    font-size: var(--text-base);
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
  }

  h1, h2, h3, h4 { font-family: var(--font-display); font-weight: 700; line-height: 1.12; margin: 0; }

  a { color: inherit; text-decoration: none; }
  img { max-width: 100%; display: block; }
  ul { margin: 0; padding: 0; list-style: none; }
  button { font-family: inherit; cursor: pointer; }

  :focus-visible {
    outline: 2px solid var(--color-primary);
    outline-offset: 3px;
    border-radius: var(--radius-sm);
  }

  ::selection { background: var(--color-primary); color: var(--color-text-inverse); }

  /* ---------- BLOCO A.3 — COMPONENTES REUTILIZÁVEIS ---------- */

  .container-aptk {
    width: 100%;
    max-width: var(--container);
    margin-inline: auto;
    padding-inline: 24px;
  }

  /* Eyebrow / rótulo de seção (mono, caixa alta, espaçado) */
  .eyebrow {
    font-family: var(--font-mono);
    font-size: var(--text-xs);
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: var(--color-primary);
    display: inline-flex;
    align-items: center;
    gap: 10px;
  }
  .eyebrow::before {
    content: "";
    width: 26px;
    height: 1px;
    background: var(--color-primary);
    display: inline-block;
  }

  /* Título de seção com fio dourado embaixo */
  .section-title {
    font-family: var(--font-display);
    font-size: clamp(1.75rem, 4vw, var(--text-4xl));
    color: var(--color-text);
    margin-top: 14px;
    padding-bottom: 16px;
    position: relative;
  }
  .section-title::after {
    content: "";
    position: absolute;
    left: 0;
    bottom: 0;
    width: 64px;
    height: 2px;
    background: var(--color-primary);
  }

  /* Botão APTK — sólido dourado, texto escuro caixa alta */
  .btn-aptk {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px 28px;
    background: var(--color-primary);
    color: var(--color-text-inverse);
    font-family: var(--font-body);
    font-weight: 600;
    font-size: var(--text-sm);
    letter-spacing: 0.08em;
    text-transform: uppercase;
    border: 1px solid var(--color-primary);
    border-radius: var(--radius-md);
    transition: background-color .2s ease, box-shadow .2s ease, transform .2s ease;
  }
  .btn-aptk:hover {
    background: var(--color-primary-hover);
    border-color: var(--color-primary-hover);
    box-shadow: var(--shadow-glow);
    transform: translateY(-1px);
  }

  /* Variante contorno */
  .btn-aptk--outline {
    background: transparent;
    color: var(--color-primary);
    border-color: var(--color-primary-muted);
  }
  .btn-aptk--outline:hover {
    background: var(--gold-faint);
    color: var(--color-primary-hover);
    border-color: var(--color-primary);
    box-shadow: none;
  }

  .btn-aptk--block { width: 100%; }

  /* Card base */
  .card-aptk {
    background: var(--color-bg-card);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    transition: border-color .25s ease, box-shadow .25s ease, transform .25s ease;
  }
  .card-aptk:hover {
    border-color: var(--color-primary-muted);
    box-shadow: var(--shadow-card);
  }

  /* Badge */
  .badge-aptk {
    display: inline-block;
    font-family: var(--font-mono);
    font-size: var(--text-xs);
    letter-spacing: 0.12em;
    text-transform: uppercase;
    padding: 4px 10px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--color-primary-muted);
    color: var(--color-primary);
    background: var(--gold-faint);
  }

  /* Preço — fonte mono */
  .price-tag {
    font-family: var(--font-mono);
    font-weight: 500;
    color: var(--color-text);
  }
  .price-tag small { color: var(--color-text-muted); font-weight: 400; }

  /* Nome de produto em cursivo âmbar (assinatura da marca) */
  .product-name {
    font-family: var(--font-script);
    font-weight: 700;
    color: var(--color-primary);
    line-height: 1.1;
  }

  /* Placeholder de imagem (#1F1F1F) */
  .placeholder {
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    display: grid;
    place-items: center;
    color: var(--color-text-muted);
    font-family: var(--font-mono);
    font-size: var(--text-xs);
    letter-spacing: 0.18em;
    text-transform: uppercase;
    position: relative;
    overflow: hidden;
  }
  .placeholder::after {
    content: "APTK";
    font-family: var(--font-display);
    font-size: var(--text-3xl);
    color: var(--color-border);
    position: absolute;
    letter-spacing: 0.1em;
  }
  .placeholder span { position: relative; z-index: 1; align-self: end; margin-bottom: 14px; }

  /* Utilitários */
  .text-gold     { color: var(--color-primary); }
  .bg-elevated   { background: var(--color-bg-elevated); }
  .border-gold   { border: 1px solid var(--color-primary-muted); }

  /* Seção genérica — espaçamento vertical generoso */
  .section { padding-block: clamp(64px, 9vw, 120px); }

  .section-head { max-width: 640px; margin-bottom: 48px; }
  .section-head p { color: var(--color-text-muted); font-size: var(--text-lg); margin: 0; }


  /* ===================================================================
     BLOCO B — LAYOUT DA HOMEPAGE
     =================================================================== */

  /* ---------- Header ---------- */
  .site-header {
    position: sticky;
    top: 0;
    z-index: 100;
    background: color-mix(in srgb, var(--color-bg) 88%, transparent);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--color-border);
  }
  .site-header .container-aptk {
    height: var(--header-h);
    display: flex;
    align-items: center;
    gap: 32px;
  }

  .brand {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: var(--font-display);
    font-weight: 700;
    font-size: var(--text-xl);
    letter-spacing: 0.18em;
    color: var(--color-text);
  }
  .brand .brand-mark {
    width: 10px; height: 10px;
    background: var(--color-primary);
    border-radius: 2px;
    transform: rotate(45deg);
  }

  .site-nav { margin-left: auto; }
  .site-nav ul { display: flex; gap: 22px; }
  .site-nav a {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    font-weight: 500;
    padding: 6px 0;
    position: relative;
    transition: color .2s ease;
  }
  .site-nav a::after {
    content: "";
    position: absolute; left: 0; bottom: 0;
    width: 0; height: 1.5px;
    background: var(--color-primary);
    transition: width .25s ease;
  }
  .site-nav a:hover { color: var(--color-text); }
  .site-nav a:hover::after { width: 100%; }

  .header-actions { display: flex; align-items: center; gap: 18px; }
  .icon-btn {
    background: transparent;
    border: none;
    color: var(--color-text-muted);
    display: grid; place-items: center;
    width: 38px; height: 38px;
    border-radius: var(--radius-md);
    transition: color .2s ease, background-color .2s ease;
  }
  .icon-btn:hover { color: var(--color-primary); background: var(--gold-faint); }
  .icon-btn svg { width: 20px; height: 20px; }
  .cart-btn { position: relative; }
  .cart-count {
    position: absolute; top: 2px; right: 2px;
    background: var(--color-primary);
    color: var(--color-text-inverse);
    font-family: var(--font-mono);
    font-size: 10px;
    font-weight: 500;
    min-width: 16px; height: 16px;
    border-radius: 8px;
    display: grid; place-items: center;
    padding: 0 4px;
  }

  .nav-toggle { display: none; }

  /* ---------- Hero ---------- */
  .hero {
    position: relative;
    min-height: calc(90vh - var(--header-h));
    display: flex;
    align-items: center;
    overflow: hidden;
  }
  .hero::before {
    /* brilho dourado sutil — sem gradiente colorido */
    content: "";
    position: absolute;
    top: -10%; right: -5%;
    width: 620px; height: 620px;
    background: radial-gradient(circle, var(--gold-faint) 0%, transparent 65%);
    pointer-events: none;
  }
  .hero-inner { max-width: 760px; position: relative; z-index: 1; padding-block: 48px; }
  .hero .script-line {
    font-family: var(--font-script);
    font-size: clamp(1.6rem, 4vw, var(--text-3xl));
    color: var(--color-primary);
    margin-bottom: 12px;
  }
  .hero h1 {
    font-size: clamp(2.5rem, 7vw, 4.25rem);
    letter-spacing: -0.01em;
    margin-bottom: 24px;
  }
  .hero h1 em { font-style: italic; color: var(--color-text); }
  .hero p {
    font-size: clamp(var(--text-base), 2vw, var(--text-xl));
    color: var(--color-text-muted);
    max-width: 560px;
    margin: 0 0 36px;
  }
  .hero-ctas { display: flex; flex-wrap: wrap; gap: 14px; }

  .hero-meta {
    margin-top: 48px;
    display: flex;
    gap: 40px;
    flex-wrap: wrap;
  }
  .hero-meta div { display: flex; flex-direction: column; gap: 2px; }
  .hero-meta .num {
    font-family: var(--font-mono);
    font-size: var(--text-2xl);
    color: var(--color-primary);
  }
  .hero-meta .lbl {
    font-size: var(--text-xs);
    color: var(--color-text-muted);
    letter-spacing: 0.1em;
    text-transform: uppercase;
  }

  /* ---------- Categorias ---------- */
  .category-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
  }
  .category-card {
    padding: 28px 24px;
    display: flex;
    flex-direction: column;
    min-height: 200px;
    text-decoration: none;
  }
  .category-card .cat-img {
    height: 96px; margin-bottom: 20px;
  }
  .category-card h3 {
    font-family: var(--font-display);
    font-size: var(--text-xl);
    margin-bottom: 6px;
    transition: color .2s ease;
  }
  .category-card:hover h3 { color: var(--color-primary); }
  .category-card p { color: var(--color-text-muted); font-size: var(--text-sm); margin: 0; }
  .category-card .cat-link {
    margin-top: auto; padding-top: 16px;
    font-family: var(--font-mono);
    font-size: var(--text-xs);
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--color-primary);
    display: inline-flex; align-items: center; gap: 8px;
  }

  /* ---------- Produto em destaque ---------- */
  .featured-grid {
    display: grid;
    grid-template-columns: 1fr 1.1fr;
    gap: 56px;
    align-items: center;
  }
  .featured-img { aspect-ratio: 4 / 5; }
  .featured-body .badge-aptk { margin-bottom: 18px; }
  .featured-body .product-name { font-size: clamp(2.5rem, 6vw, 3.5rem); margin-bottom: 16px; }
  .featured-body .sub {
    font-family: var(--font-display);
    font-style: italic;
    font-size: var(--text-xl);
    color: var(--color-text);
    margin: 0 0 18px;
  }
  .featured-body p.desc { color: var(--color-text-muted); margin: 0 0 28px; max-width: 460px; }
  .featured-specs {
    display: flex; gap: 32px; margin-bottom: 32px; flex-wrap: wrap;
  }
  .featured-specs div { display: flex; flex-direction: column; gap: 3px; }
  .featured-specs .k { font-size: var(--text-xs); color: var(--color-text-muted); letter-spacing: 0.1em; text-transform: uppercase; }
  .featured-specs .v { font-family: var(--font-mono); color: var(--color-text); font-size: var(--text-base); }
  .featured-buy { display: flex; align-items: center; gap: 24px; flex-wrap: wrap; }
  .featured-buy .price-tag { font-size: var(--text-2xl); }

  /* ---------- Marcas ---------- */
  .brand-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
  }
  .brand-card {
    padding: 40px;
    display: flex;
    flex-direction: column;
    min-height: 280px;
  }
  .brand-card .brand-logo {
    height: 64px; width: 100%; margin-bottom: 28px;
  }
  .brand-card h3 { font-size: var(--text-2xl); margin-bottom: 10px; }
  .brand-card p { color: var(--color-text-muted); margin: 0 0 24px; flex-grow: 1; }
  .brand-card .brand-tag {
    font-family: var(--font-mono);
    font-size: var(--text-xs);
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--color-text-muted);
    margin-bottom: 18px;
  }

  /* ---------- Clube ---------- */
  .club { background: var(--color-bg-card); border-block: 1px solid var(--color-border); }
  .plan-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
    align-items: stretch;
  }
  .plan-card {
    background: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: 28px 22px;
    display: flex;
    flex-direction: column;
    transition: border-color .25s ease, transform .25s ease;
  }
  .plan-card:hover { border-color: var(--color-primary-muted); transform: translateY(-3px); }
  .plan-card.is-featured {
    border-color: var(--color-primary);
    box-shadow: var(--shadow-glow);
  }
  .plan-card .plan-name {
    font-family: var(--font-script);
    font-size: var(--text-2xl);
    color: var(--color-primary);
    margin-bottom: 4px;
  }
  .plan-card .plan-kicker {
    font-size: var(--text-xs);
    color: var(--color-text-muted);
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 20px;
    min-height: 30px;
  }
  .plan-card .plan-price {
    font-family: var(--font-mono);
    color: var(--color-text);
    font-size: var(--text-2xl);
    margin-bottom: 2px;
  }
  .plan-card .plan-price small { font-size: var(--text-sm); color: var(--color-text-muted); }
  .plan-card .plan-cycle { font-size: var(--text-xs); color: var(--color-text-muted); margin-bottom: 22px; }
  .plan-card .btn-aptk { margin-top: auto; }
  .plan-badge {
    position: absolute; top: -11px; left: 50%; transform: translateX(-50%);
    background: var(--color-primary); color: var(--color-text-inverse);
    font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.14em;
    text-transform: uppercase; padding: 3px 12px; border-radius: var(--radius-sm);
    white-space: nowrap;
  }
  .plan-card { position: relative; }

  /* ---------- Footer ---------- */
  .site-footer {
    background: var(--color-bg);
    border-top: 1px solid var(--color-border);
    padding-top: 72px;
  }
  .footer-grid {
    display: grid;
    grid-template-columns: 1.6fr 1fr 1fr 1.4fr;
    gap: 48px;
    padding-bottom: 56px;
  }
  .footer-brand .brand { margin-bottom: 18px; }
  .footer-brand p { color: var(--color-text-muted); font-size: var(--text-sm); max-width: 300px; margin: 0 0 24px; }
  .footer-social { display: flex; gap: 12px; }
  .footer-col h4 {
    font-family: var(--font-body); font-weight: 600;
    font-size: var(--text-xs); letter-spacing: 0.16em; text-transform: uppercase;
    color: var(--color-text); margin-bottom: 18px;
  }
  .footer-col ul { display: flex; flex-direction: column; gap: 11px; }
  .footer-col a { color: var(--color-text-muted); font-size: var(--text-sm); transition: color .2s ease; }
  .footer-col a:hover { color: var(--color-primary); }
  .footer-news p { color: var(--color-text-muted); font-size: var(--text-sm); margin: 0 0 16px; }
  .footer-news .news-form { display: flex; gap: 8px; }
  .footer-news input {
    flex: 1; min-width: 0;
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    color: var(--color-text);
    padding: 12px 14px;
    font-family: var(--font-body);
    font-size: var(--text-sm);
  }
  .footer-news input::placeholder { color: var(--color-text-muted); }
  .footer-news input:focus { outline: none; border-color: var(--color-primary); }
  .footer-bottom {
    border-top: 1px solid var(--color-border);
    padding-block: 26px;
    display: flex; justify-content: space-between; align-items: center; gap: 16px;
    flex-wrap: wrap;
  }
  .footer-bottom p { color: var(--color-text-muted); font-size: var(--text-xs); margin: 0; }
  .footer-bottom .pays { display: flex; gap: 10px; }
  .pay-chip {
    font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.08em;
    color: var(--color-text-muted); border: 1px solid var(--color-border);
    border-radius: var(--radius-sm); padding: 4px 9px;
  }

  /* ---------- Responsivo ---------- */
  @media (max-width: 980px) {
    .featured-grid { grid-template-columns: 1fr; gap: 32px; }
    .featured-img { aspect-ratio: 16 / 10; }
    .plan-grid { grid-template-columns: repeat(2, 1fr); }
    .footer-grid { grid-template-columns: 1fr 1fr; gap: 36px; }
    .footer-news { grid-column: 1 / -1; }
  }

  @media (max-width: 820px) {
    .site-nav, .header-actions .label-login { display: none; }
    .nav-toggle {
      display: grid; place-items: center;
      width: 40px; height: 40px; margin-left: auto;
      background: transparent; border: 1px solid var(--color-border);
      border-radius: var(--radius-md); color: var(--color-text);
    }
    .nav-toggle svg { width: 20px; height: 20px; }
    .header-actions { gap: 8px; }
    .category-grid { grid-template-columns: repeat(2, 1fr); }
    .brand-grid { grid-template-columns: 1fr; }
    .hero-meta { gap: 28px; }
  }

  @media (max-width: 520px) {
    .category-grid { grid-template-columns: 1fr; }
    .plan-grid { grid-template-columns: 1fr; }
    .footer-grid { grid-template-columns: 1fr; }
    .hero-ctas .btn-aptk { width: 100%; }
    .container-aptk { padding-inline: 18px; }
  }

  /* Movimento reduzido */
  @media (prefers-reduced-motion: reduce) {
    * { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
  }

  /* Entrada do hero — único momento orquestrado */
  @keyframes riseIn { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
  .hero-inner > * { animation: riseIn .7s ease both; }
  .hero-inner > *:nth-child(2) { animation-delay: .08s; }
  .hero-inner > *:nth-child(3) { animation-delay: .16s; }
  .hero-inner > *:nth-child(4) { animation-delay: .24s; }
  .hero-inner > *:nth-child(5) { animation-delay: .32s; }
  </style>
</head>

<body>

  <!-- ============ HEADER ============ -->
  <header class="site-header">
    <div class="container-aptk">
      <a href="#" class="brand"><span class="brand-mark"></span>APTK</a>

      <nav class="site-nav" aria-label="Principal">
        <ul>
          <li><a href="#">Loja</a></li>
          <li><a href="#">Customização</a></li>
          <li><a href="#">Clube</a></li>
          <li><a href="#">Assinantes</a></li>
          <li><a href="#">Parceiros</a></li>
          <li><a href="#">Eventos</a></li>
          <li><a href="#">Franquias</a></li>
          <li><a href="#">Marcas</a></li>
          <li><a href="#">Sobre</a></li>
        </ul>
      </nav>

      <div class="header-actions">
        <button class="icon-btn" aria-label="Buscar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        </button>
        <button class="icon-btn label-login" aria-label="Entrar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        </button>
        <button class="icon-btn cart-btn" aria-label="Carrinho">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h2l2.4 12.4a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.2L22 8H6"/><circle cx="10" cy="21" r="1"/><circle cx="18" cy="21" r="1"/></svg>
          <span class="cart-count">2</span>
        </button>
        <button class="nav-toggle" aria-label="Abrir menu">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
      </div>
    </div>
  </header>

  <main>

    <!-- ============ HERO ============ -->
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

    <!-- ============ CATEGORIAS ============ -->
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

    <!-- ============ PRODUTO EM DESTAQUE ============ -->
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

    <!-- ============ MARCAS ============ -->
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

    <!-- ============ CLUBE ============ -->
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

  </main>

  <!-- ============ FOOTER ============ -->
  <footer class="site-footer">
    <div class="container-aptk">
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="#" class="brand"><span class="brand-mark"></span>APTK</a>
          <p>Small Batches Holding — destilados autorais, clube de assinatura e as marcas BARIN e Ice4Pros.</p>
          <div class="footer-social">
            <button class="icon-btn border-gold" aria-label="Instagram">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
            </button>
            <button class="icon-btn border-gold" aria-label="WhatsApp">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 12a9 9 0 0 1-13.5 7.8L3 21l1.2-4.5A9 9 0 1 1 21 12Z"/><path d="M8.5 9.5c0 3 2 5 5 5"/></svg>
            </button>
          </div>
        </div>

        <div class="footer-col">
          <h4>Comprar</h4>
          <ul>
            <li><a href="#">Drinks prontos</a></li>
            <li><a href="#">Gin, Vodka & Whisky</a></li>
            <li><a href="#">Kits & presentes</a></li>
            <li><a href="#">Small batches</a></li>
            <li><a href="#">Personalizar rótulo</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Negócios</h4>
          <ul>
            <li><a href="#">Portal de parceiros</a></li>
            <li><a href="#">Seja franqueado</a></li>
            <li><a href="#">Eventos & corporativo</a></li>
            <li><a href="#">Ice4Pros B2B</a></li>
            <li><a href="#">Sobre a APTK</a></li>
          </ul>
        </div>

        <div class="footer-col footer-news">
          <h4>Entre para a lista</h4>
          <p>Lotes limitados avisam pouco. Receba os lançamentos antes de esgotarem.</p>
          <div class="news-form">
            <input type="email" placeholder="seu@email.com" aria-label="E-mail">
            <button class="btn-aptk">Assinar</button>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <p>© 2026 APTK · Small Batches Holding. Beba com moderação. Venda proibida para menores de 18 anos.</p>
        <div class="pays">
          <span class="pay-chip">PIX</span>
          <span class="pay-chip">CARTÃO</span>
          <span class="pay-chip">BOLETO</span>
        </div>
      </div>
    </div>
  </footer>

</body>
</html>