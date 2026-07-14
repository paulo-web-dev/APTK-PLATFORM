@php
    /* Carrinho ao vivo no header — uma query só, via CartService da loja. */
    $aptkCartCount = app(\App\Services\CartService::class)->count();

    /* Menu leva 01: Quem Somos · Produtos · Custom · Clube · Eventos · Franquias · Collabs.
       Itens institucionais → rota pages.show (slug => rótulo).
       Custom tem rota própria (route('custom')) e Assinantes saiu da nav
       (a página /assinantes continua existindo para links diretos). */
    $aptkNav = [
        'clube'     => 'Clube',
        'eventos'   => 'Eventos',
        'franquias' => 'Franquias',
        'collabs'   => 'Collabs',
    ];
    $aptkLojaAtiva = request()->routeIs('catalog') || request()->routeIs('product');
@endphp
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'APTK Spirits — Drinks & histórias pra contar')</title>
  <meta name="description" content="@yield('meta_description', 'Alquimia de histórias: drinks engarrafados, criados em pequenos lotes por humanos inquietos. Da nossa loja para a sua mesa.')">

  {{-- Tipografia oficial APTK: PP Rader + Neue World, auto-hospedadas (definidas em aptk-tokens.css). --}}
  <link rel="preload" href="{{ asset('fonts/PPRader-Bold.woff2') }}" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="{{ asset('fonts/PPRader-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>

  {{-- Bootstrap 5 (antes do aptk.css, pra ser tematizado por cima) --}}
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  {{-- Identidade APTK --}}
  @include('partials.theme-init')
  <link href="{{ asset('css/aptk.css') }}" rel="stylesheet">

  <style>
  /* ---- Layout público: header ---- */
  .site-header { position: sticky; top: 0; z-index: 100; background: color-mix(in srgb, var(--color-bg) 88%, transparent); backdrop-filter: blur(12px); border-bottom: 1px solid var(--color-border); }
  .site-header .container-aptk { height: var(--header-h); display: flex; align-items: center; gap: 32px; }

  /* Logo de marca (coroa + APTK) */
  .site-logo { display: inline-flex; align-items: center; font-size: 19px; text-decoration: none; }
  .site-logo .aptk-logo { color: var(--color-text); }

  .site-nav { margin-left: auto; }
  .site-nav ul { display: flex; gap: 20px; }
  .site-nav a { font-size: var(--text-sm); color: var(--color-text-muted); font-weight: 500; padding: 6px 0; position: relative; transition: color .2s ease; white-space: nowrap; }
  .site-nav a::after { content: ""; position: absolute; left: 0; bottom: 0; width: 0; height: 1.5px; background: var(--color-primary); transition: width .25s ease; }
  .site-nav a:hover { color: var(--color-text); }
  .site-nav a:hover::after,
  .site-nav a.is-active::after { width: 100%; }
  .site-nav a.is-active { color: var(--color-text); }

  .header-actions { display: flex; align-items: center; gap: 14px; }
  .icon-btn { background: transparent; border: none; color: var(--color-text-muted); display: grid; place-items: center; width: 38px; height: 38px; border-radius: var(--radius-md); transition: color .2s ease, background-color .2s ease; cursor: pointer; }
  .icon-btn:hover { color: var(--color-primary); background: var(--gold-faint); }
  .icon-btn svg { width: 20px; height: 20px; }

  /* Entrar (deslogado) — ícone + rótulo */
  .icon-btn.label-login { width: auto; gap: 7px; padding: 0 10px; }
  .login-text { font-size: var(--text-sm); font-weight: 500; }

  /* Conta (logado) — avatar + dropdown */
  .account-btn { padding: 0; }
  .account-btn.dropdown-toggle::after { display: none; }
  .account-btn .ava { width: 30px; height: 30px; border-radius: 50%; background: var(--gold-faint); border: 1px solid var(--color-primary-muted); color: var(--color-primary); display: grid; place-items: center; font-family: var(--font-mono); font-size: 12px; }
  .account-dropdown { background: var(--color-bg-elevated); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 6px; min-width: 224px; box-shadow: var(--shadow-card); }
  .account-dropdown .acct-head { padding: 8px 12px 10px; }
  .account-dropdown .acct-head strong { display: block; color: var(--color-text); font-size: var(--text-sm); }
  .account-dropdown .acct-head span { display: block; color: var(--color-text-muted); font-size: var(--text-xs); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .account-dropdown .dropdown-item { color: var(--color-text-muted); font-size: var(--text-sm); border-radius: var(--radius-sm); padding: 8px 12px; }
  .account-dropdown .dropdown-item:hover, .account-dropdown .dropdown-item:focus { background: var(--color-bg-card); color: var(--color-text); }
  .account-dropdown .dropdown-divider { border-color: var(--color-border); margin: 6px 4px; }
  .account-dropdown form { margin: 0; }

  .cart-btn { position: relative; }
  .cart-count { position: absolute; top: 2px; right: 2px; background: var(--color-primary); color: var(--color-text-inverse); font-family: var(--font-mono); font-size: 10px; font-weight: 500; min-width: 16px; height: 16px; border-radius: 8px; display: grid; place-items: center; padding: 0 4px; }
  .nav-toggle { display: none; }

  /* ---- Banda escura institucional (feature) — Cuba Libre + Scotch ---- */
  .feature-band { background: var(--color-bg); color: var(--color-text); text-align: center; border-top: 1px solid var(--color-border); }
  .feature-band .container-aptk { max-width: 760px; display: flex; flex-direction: column; align-items: center; }
  .feature-band .feature-sun { width: 132px; color: var(--color-primary); margin-bottom: 22px; }
  .feature-band .eyebrow { margin-bottom: 18px; }
  .feature-band h2 { font-family: var(--font-display); font-size: clamp(2rem, 5vw, 3.2rem); color: var(--color-text); margin: 0 0 18px; }
  .feature-band p { font-family: var(--font-editorial); font-size: var(--text-lg); line-height: 1.7; color: var(--color-text-muted); margin: 0 0 30px; max-width: 560px; }
  .feature-band .feature-div { width: 220px; color: var(--color-primary); margin-bottom: 30px; }

  /* ---- Layout público: footer ---- */
  .site-footer { background: var(--color-bg); border-top: 1px solid var(--color-border); padding-top: 72px; }
  .footer-grid { display: grid; grid-template-columns: 1.6fr 1fr 1fr 1.4fr; gap: 48px; padding-bottom: 56px; }
  .footer-brand .footer-logo { display: inline-flex; font-size: 17px; margin-bottom: 18px; }
  .footer-brand p { color: var(--color-text-muted); font-size: var(--text-sm); max-width: 300px; margin: 0 0 24px; }
  .footer-social { display: flex; gap: 12px; }
  .footer-social a { background: transparent; border: 1px solid var(--color-primary-muted); color: var(--color-text-muted); display: grid; place-items: center; width: 38px; height: 38px; border-radius: var(--radius-md); transition: color .2s ease, background-color .2s ease; }
  .footer-social a:hover { color: var(--color-primary); background: var(--gold-faint); }
  .footer-social svg { width: 20px; height: 20px; }
  .footer-col h4 { font-family: var(--font-body); font-weight: 600; font-size: var(--text-xs); letter-spacing: 0.16em; text-transform: uppercase; color: var(--color-text); margin-bottom: 18px; }
  .footer-col ul { display: flex; flex-direction: column; gap: 11px; }
  .footer-col a { color: var(--color-text-muted); font-size: var(--text-sm); transition: color .2s ease; }
  .footer-col a:hover { color: var(--color-primary); }
  .footer-news p { color: var(--color-text-muted); font-size: var(--text-sm); margin: 0 0 16px; }
  .footer-news .news-form { display: flex; gap: 8px; }
  .footer-news input { flex: 1; min-width: 0; background: var(--color-bg-elevated); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text); padding: 12px 14px; font-family: var(--font-body); font-size: var(--text-sm); }
  .footer-news input::placeholder { color: var(--color-text-muted); }
  .footer-news input:focus { outline: none; border-color: var(--color-primary); }
  .footer-news .news-ok { color: var(--color-success); font-size: var(--text-xs); margin: 10px 0 0; }
  .footer-news .news-err { color: var(--color-danger); font-size: var(--text-xs); margin: 10px 0 0; }
  .footer-bottom { border-top: 1px solid var(--color-border); padding-block: 26px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
  .footer-bottom p { color: var(--color-text-muted); font-size: var(--text-xs); margin: 0; }
  .footer-bottom .pays { display: flex; gap: 10px; }
  .pay-chip { font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.08em; color: var(--color-text-muted); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 4px 9px; }

  /* ---- Responsivo: header + footer ---- */
  @media (max-width: 1040px) {
    .site-header .container-aptk { gap: 20px; }
    .site-nav ul { gap: 15px; }
  }
  @media (max-width: 980px) {
    .footer-grid { grid-template-columns: 1fr 1fr; gap: 36px; }
    .footer-news { grid-column: 1 / -1; }
  }
  @media (max-width: 880px) {
    .site-nav { display: none; }
    .nav-toggle { display: grid; place-items: center; width: 40px; height: 40px; background: transparent; border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text); cursor: pointer; }
    .nav-toggle svg { width: 20px; height: 20px; }
    .header-actions { gap: 8px; }
    .site-header.nav-open .site-nav { display: block; position: absolute; top: var(--header-h); left: 0; right: 0; background: var(--color-bg-elevated); border-bottom: 1px solid var(--color-border); padding: 16px 24px; }
    .site-header.nav-open .site-nav ul { flex-direction: column; gap: 2px; }
    .site-header.nav-open .site-nav a { display: block; padding: 10px 0; }
  }
  @media (max-width: 520px) {
    .login-text { display: none; }
    .icon-btn.label-login { width: 38px; padding: 0; }
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
      <a href="{{ route('home') }}" class="site-logo" aria-label="APTK Spirits — início">
        <x-brand.logo />
      </a>

      <nav class="site-nav" aria-label="Principal">
        <ul class="list-clean">
          <li><a href="{{ route('pages.show', 'quem-somos') }}" @class(['is-active' => request()->is('quem-somos')])>Quem Somos</a></li>
          <li><a href="{{ route('catalog') }}" @class(['is-active' => $aptkLojaAtiva])>Produtos</a></li>
          <li><a href="{{ route('custom') }}" @class(['is-active' => request()->is('custom')])>Custom</a></li>
          @foreach ($aptkNav as $slug => $label)
            <li><a href="{{ route('pages.show', $slug) }}" @class(['is-active' => request()->is($slug)])>{{ $label }}</a></li>
          @endforeach
        </ul>
      </nav>

      <div class="header-actions">
        <a href="{{ route('catalog') }}" class="icon-btn" aria-label="Buscar na loja">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        </a>

        @auth
          <div class="dropdown">
            <button class="icon-btn account-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Minha conta">
              <span class="ava">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end account-dropdown">
              <li class="acct-head">
                <strong>{{ auth()->user()->name }}</strong>
                <span>{{ auth()->user()->email }}</span>
              </li>
              <li><a class="dropdown-item" href="{{ route('dashboard') }}">Minha conta</a></li>
              <li><a class="dropdown-item" href="{{ route('orders.index') }}">Meus pedidos</a></li>
              <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Meu perfil</a></li>
              @if (auth()->user()->isAdmin())
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Painel administrativo</a></li>
              @elseif (auth()->user()->isB2b())
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route('b2b.ping') }}">Portal de parceiros</a></li>
              @endif
              <li><hr class="dropdown-divider"></li>
              <li>
                <form method="POST" action="{{ route('logout') }}">
                  @csrf
                  <button type="submit" class="dropdown-item">Sair</button>
                </form>
              </li>
            </ul>
          </div>
        @else
          <a href="{{ route('login') }}" class="icon-btn label-login" aria-label="Entrar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            <span class="login-text">Entrar</span>
          </a>
        @endauth

        <a href="{{ route('cart.index') }}" class="icon-btn cart-btn" aria-label="Carrinho com {{ $aptkCartCount }} {{ $aptkCartCount == 1 ? 'item' : 'itens' }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h2l2.4 12.4a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.2L22 8H6"/><circle cx="10" cy="21" r="1"/><circle cx="18" cy="21" r="1"/></svg>
          @if ($aptkCartCount > 0)
            <span class="cart-count">{{ $aptkCartCount }}</span>
          @endif
        </a>

        <button class="nav-toggle" aria-label="Abrir menu" id="navToggle">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
      </div>
    </div>
  </header>

  <main>
    @yield('content')
  </main>

  {{-- Banda institucional escura (feature). Uma página pode escondê-la com:
       @section('hide_feature_band') 1 @endsection --}}
  @hasSection('hide_feature_band')
  @else
  <section class="section section--dark feature-band">
    <div class="container-aptk">
      <x-brand.sunburst class="feature-sun" />
      <span class="eyebrow">Alquimia de histórias</span>
      <h2>Feito por humanos inquietos</h2>
      <p>Drinks engarrafados em pequenos lotes, criados para despertar sentidos e abrir novas histórias. Da nossa loja para a sua mesa — sem pressa, sem atalho.</p>
      <x-brand.divider class="feature-div" />
      <a href="{{ route('catalog') }}" class="btn-aptk">Conhecer os rótulos</a>
    </div>
  </section>
  @endif

  <footer class="site-footer">
    <div class="container-aptk">
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="{{ route('home') }}" class="footer-logo" aria-label="APTK Spirits — início">
            <x-brand.logo />
          </a>
          <p>Drinks &amp; histórias pra contar. Destilados e drinks autorais da APTK, ao lado das marcas BARIN e Ice4Pros.</p>
          <div class="footer-social">
            <a href="https://www.instagram.com/aptkspirits/" target="_blank" rel="noopener noreferrer" aria-label="Instagram da APTK">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
            </a>
            <a href="https://www.linkedin.com/company/aptk-spirits/" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn da APTK">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M7 10.5v6"/><circle cx="7" cy="7.7" r="0.6" fill="currentColor" stroke="none"/><path d="M11 16.5v-6"/><path d="M11 13.3a2.3 2.3 0 0 1 4.6 0v3.2"/></svg>
            </a>
            <a href="#" aria-label="WhatsApp da APTK">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 12a9 9 0 0 1-13.5 7.8L3 21l1.2-4.5A9 9 0 1 1 21 12Z"/><path d="M8.5 9.5c0 3 2 5 5 5"/></svg>
            </a>
          </div>
        </div>

        <div class="footer-col">
          <h4>Comprar</h4>
          <ul class="list-clean">
            <li><a href="{{ route('catalog') }}">Drinks prontos</a></li>
            <li><a href="{{ route('catalog') }}">Bases &amp; destilados</a></li>
            <li><a href="{{ route('catalog') }}">Kits &amp; presentes</a></li>
            <li><a href="{{ route('catalog') }}">Edições especiais</a></li>
            <li><a href="{{ route('custom') }}">Personalizar rótulo</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Negócios</h4>
          <ul class="list-clean">
            <li><a href="{{ route('pages.show', 'collabs') }}">Collabs</a></li>
            <li><a href="{{ route('pages.show', 'franquias') }}">Seja franqueado</a></li>
            <li><a href="{{ route('pages.show', 'eventos') }}">Eventos &amp; corporativo</a></li>
            <li><a href="{{ route('barin') }}">Barín</a></li>
            <li><a href="{{ route('ice4pros') }}">Ice4Pros</a></li>
            <li><a href="{{ route('pages.show', 'quem-somos') }}">Quem Somos</a></li>
          </ul>
        </div>

        <div class="footer-col footer-news">
          <h4>Entre para a lista</h4>
          <p>Lote pequeno avisa pouco. Receba os lançamentos antes de esgotarem.</p>
          <form class="news-form" method="POST" action="{{ route('newsletter') }}">
            @csrf
            <input type="email" name="email" placeholder="seu@email.com" aria-label="E-mail" value="{{ old('email') }}" required>
            <button type="submit" class="btn-aptk">Assinar</button>
          </form>
          @if (session('newsletter_ok'))
            <p class="news-ok">{{ session('newsletter_ok') }}</p>
          @endif
          @error('email')
            <p class="news-err">{{ $message }}</p>
          @enderror
        </div>
      </div>

      <div class="footer-bottom">
        <p>© {{ date('Y') }} APTK Spirits · EST. 2020. Beba com moderação. Venda proibida para menores de 18 anos.</p>
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
  {{-- toggle de tema removido (leva 03: dark fixo) --}}
</body>
</html>
