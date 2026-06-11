<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'APTK Spirits — Destilados autorais em pequenos lotes')</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Dancing+Script:wght@600;700&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

  {{-- Bootstrap 5 (antes do aptk.css, pra ser tematizado por cima) --}}
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  {{-- Identidade APTK --}}
  <link href="{{ asset('css/aptk.css') }}" rel="stylesheet">

  <style>
  /* ---- Layout público: header + footer ---- */
  .site-header { position: sticky; top: 0; z-index: 100; background: color-mix(in srgb, var(--color-bg) 88%, transparent); backdrop-filter: blur(12px); border-bottom: 1px solid var(--color-border); }
  .site-header .container-aptk { height: var(--header-h); display: flex; align-items: center; gap: 32px; }
  .brand { display: flex; align-items: center; gap: 10px; font-family: var(--font-display); font-weight: 700; font-size: var(--text-xl); letter-spacing: 0.18em; color: var(--color-text); }
  .brand .brand-mark { width: 10px; height: 10px; background: var(--color-primary); border-radius: 2px; transform: rotate(45deg); }
  .site-nav { margin-left: auto; }
  .site-nav ul { display: flex; gap: 22px; }
  .site-nav a { font-size: var(--text-sm); color: var(--color-text-muted); font-weight: 500; padding: 6px 0; position: relative; transition: color .2s ease; }
  .site-nav a::after { content: ""; position: absolute; left: 0; bottom: 0; width: 0; height: 1.5px; background: var(--color-primary); transition: width .25s ease; }
  .site-nav a:hover { color: var(--color-text); }
  .site-nav a:hover::after { width: 100%; }
  .header-actions { display: flex; align-items: center; gap: 18px; }
  .icon-btn { background: transparent; border: none; color: var(--color-text-muted); display: grid; place-items: center; width: 38px; height: 38px; border-radius: var(--radius-md); transition: color .2s ease, background-color .2s ease; }
  .icon-btn:hover { color: var(--color-primary); background: var(--gold-faint); }
  .icon-btn svg { width: 20px; height: 20px; }
  .cart-btn { position: relative; }
  .cart-count { position: absolute; top: 2px; right: 2px; background: var(--color-primary); color: var(--color-text-inverse); font-family: var(--font-mono); font-size: 10px; font-weight: 500; min-width: 16px; height: 16px; border-radius: 8px; display: grid; place-items: center; padding: 0 4px; }
  .nav-toggle { display: none; }

  .site-footer { background: var(--color-bg); border-top: 1px solid var(--color-border); padding-top: 72px; }
  .footer-grid { display: grid; grid-template-columns: 1.6fr 1fr 1fr 1.4fr; gap: 48px; padding-bottom: 56px; }
  .footer-brand .brand { margin-bottom: 18px; }
  .footer-brand p { color: var(--color-text-muted); font-size: var(--text-sm); max-width: 300px; margin: 0 0 24px; }
  .footer-social { display: flex; gap: 12px; }
  .footer-col h4 { font-family: var(--font-body); font-weight: 600; font-size: var(--text-xs); letter-spacing: 0.16em; text-transform: uppercase; color: var(--color-text); margin-bottom: 18px; }
  .footer-col ul { display: flex; flex-direction: column; gap: 11px; }
  .footer-col a { color: var(--color-text-muted); font-size: var(--text-sm); transition: color .2s ease; }
  .footer-col a:hover { color: var(--color-primary); }
  .footer-news p { color: var(--color-text-muted); font-size: var(--text-sm); margin: 0 0 16px; }
  .footer-news .news-form { display: flex; gap: 8px; }
  .footer-news input { flex: 1; min-width: 0; background: var(--color-bg-elevated); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text); padding: 12px 14px; font-family: var(--font-body); font-size: var(--text-sm); }
  .footer-news input::placeholder { color: var(--color-text-muted); }
  .footer-news input:focus { outline: none; border-color: var(--color-primary); }
  .footer-bottom { border-top: 1px solid var(--color-border); padding-block: 26px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
  .footer-bottom p { color: var(--color-text-muted); font-size: var(--text-xs); margin: 0; }
  .footer-bottom .pays { display: flex; gap: 10px; }
  .pay-chip { font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.08em; color: var(--color-text-muted); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 4px 9px; }

  /* ---- Responsivo: header + footer ---- */
  @media (max-width: 980px) {
    .footer-grid { grid-template-columns: 1fr 1fr; gap: 36px; }
    .footer-news { grid-column: 1 / -1; }
  }
  @media (max-width: 820px) {
    .site-nav { display: none; }
    .nav-toggle { display: grid; place-items: center; width: 40px; height: 40px; margin-left: auto; background: transparent; border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text); }
    .nav-toggle svg { width: 20px; height: 20px; }
    .header-actions { gap: 8px; }
    .site-header.nav-open .site-nav { display: block; position: absolute; top: var(--header-h); left: 0; right: 0; background: var(--color-bg-elevated); border-bottom: 1px solid var(--color-border); padding: 16px 24px; }
    .site-header.nav-open .site-nav ul { flex-direction: column; gap: 2px; }
    .site-header.nav-open .site-nav a { display: block; padding: 10px 0; }
  }
  @media (max-width: 520px) {
    .footer-grid { grid-template-columns: 1fr; }
    .container-aptk { padding-inline: 18px; }
  }
  </style>

  {{-- CSS específico da página (vem por último, pode sobrescrever o layout) --}}
  @stack('styles')
</head>

<body>

  <header class="site-header">
    <div class="container-aptk">
      <a href="{{ route('home') }}" class="brand"><span class="brand-mark"></span>APTK</a>

      <nav class="site-nav" aria-label="Principal">
        <ul class="list-clean">
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
        <button class="nav-toggle" aria-label="Abrir menu" id="navToggle">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
      </div>
    </div>
  </header>

  <main>
    @yield('content')
  </main>

  <footer class="site-footer">
    <div class="container-aptk">
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="{{ route('home') }}" class="brand"><span class="brand-mark"></span>APTK</a>
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
          <ul class="list-clean">
            <li><a href="#">Drinks prontos</a></li>
            <li><a href="#">Gin, Vodka & Whisky</a></li>
            <li><a href="#">Kits & presentes</a></li>
            <li><a href="#">Small batches</a></li>
            <li><a href="#">Personalizar rótulo</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Negócios</h4>
          <ul class="list-clean">
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
        <p>© {{ date('Y') }} APTK · Small Batches Holding. Beba com moderação. Venda proibida para menores de 18 anos.</p>
        <div class="pays">
          <span class="pay-chip">PIX</span>
          <span class="pay-chip">CARTÃO</span>
          <span class="pay-chip">BOLETO</span>
        </div>
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.getElementById('navToggle')?.addEventListener('click', function () {
      document.querySelector('.site-header').classList.toggle('nav-open');
    });
  </script>
  @stack('scripts')
</body>
</html>