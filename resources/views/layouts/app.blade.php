@php
    $aptkCartCount = app(\App\Services\CartService::class)->count();
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'APTK Spirits') }} · Minha conta</title>

    {{-- Tipografia oficial APTK (auto-hospedada em aptk-tokens.css) --}}
    <link rel="preload" href="{{ asset('fonts/PPRader-Bold.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/PPRader-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @include('partials.theme-init')
    <link href="{{ asset('css/aptk.css') }}" rel="stylesheet">

    @stack('styles')

    <style>
        .app-nav { position: sticky; top: 0; z-index: 100; background: color-mix(in srgb, var(--color-bg) 88%, transparent); backdrop-filter: blur(12px); border-bottom: 1px solid var(--color-border); }
        .app-nav .container-aptk { height: var(--header-h); display: flex; align-items: center; gap: 22px; }
        .app-nav .app-logo { display: inline-flex; align-items: center; font-size: 18px; text-decoration: none; }
        .app-nav .app-logo .aptk-logo { color: var(--color-text); }
        .app-nav .nav-links { display: flex; gap: 20px; }
        .app-nav .nav-links a { color: var(--color-text-muted); font-size: var(--text-sm); font-weight: 500; transition: color .2s ease; position: relative; }
        .app-nav .nav-links a:hover { color: var(--color-text); }
        .app-nav .nav-links a.is-active { color: var(--color-text); }
        .app-nav .nav-right { margin-left: auto; display: flex; align-items: center; gap: 14px; }

        .app-cart { position: relative; background: transparent; border: none; color: var(--color-text-muted); display: grid; place-items: center; width: 38px; height: 38px; border-radius: var(--radius-md); transition: color .2s ease, background-color .2s ease; }
        .app-cart:hover { color: var(--color-primary); background: var(--gold-faint); }
        .app-cart svg { width: 20px; height: 20px; }
        .app-cart .cart-count { position: absolute; top: 2px; right: 2px; background: var(--color-primary); color: var(--color-text-inverse); font-family: var(--font-mono); font-size: 10px; font-weight: 500; min-width: 16px; height: 16px; border-radius: 8px; display: grid; place-items: center; padding: 0 4px; }

        .user-btn { display: flex; align-items: center; gap: 9px; background: transparent; border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 7px 12px 7px 8px; color: var(--color-text); font-size: var(--text-sm); }
        .user-btn::after { margin-left: 2px; color: var(--color-text-muted); }
        .user-btn:hover { border-color: var(--color-primary-muted); }
        .user-btn .ava { width: 26px; height: 26px; border-radius: 50%; background: var(--gold-faint); border: 1px solid var(--color-primary-muted); color: var(--color-primary); display: grid; place-items: center; font-family: var(--font-mono); font-size: 11px; }
        .dropdown-menu { background: var(--color-bg-elevated); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 6px; }
        .dropdown-menu .dropdown-item { color: var(--color-text-muted); font-size: var(--text-sm); border-radius: var(--radius-sm); padding: 8px 12px; }
        .dropdown-menu .dropdown-item:hover, .dropdown-menu .dropdown-item:focus { background: var(--color-bg-card); color: var(--color-text); }
        .dropdown-menu .dropdown-divider { border-color: var(--color-border); margin: 6px 4px; }
        .dropdown-menu form { margin: 0; }
        .app-header { border-bottom: 1px solid var(--color-border); }
        .app-header .container-aptk { padding-block: 30px; }
        .app-header h1 { font-family: var(--font-display); font-size: var(--text-2xl); margin: 0; }
        .app-main { padding-block: 40px; }
        @media (max-width: 600px) { .app-nav .nav-links { display: none; } }
    </style>
</head>
<body>
    <nav class="app-nav">
        <div class="container-aptk">
            <a href="{{ route('home') }}" class="app-logo" aria-label="APTK Spirits — início">
                <x-brand.logo :tag="true" />
            </a>
            <div class="nav-links">
                <a href="{{ route('home') }}">Loja</a>
                <a href="{{ route('orders.index') }}" @class(['is-active' => request()->routeIs('orders.*')])>Meus pedidos</a>
                <a href="{{ route('dashboard') }}" @class(['is-active' => request()->routeIs('dashboard')])>Minha conta</a>
            </div>

            <div class="nav-right">
                <a href="{{ route('cart.index') }}" class="app-cart" aria-label="Carrinho com {{ $aptkCartCount }} {{ $aptkCartCount == 1 ? 'item' : 'itens' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h2l2.4 12.4a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.2L22 8H6"/><circle cx="10" cy="21" r="1"/><circle cx="18" cy="21" r="1"/></svg>
                    @if ($aptkCartCount > 0)
                        <span class="cart-count">{{ $aptkCartCount }}</span>
                    @endif
                </a>

                <div class="dropdown">
                    <button class="user-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="ava">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        {{ auth()->user()->name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
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
            </div>
        </div>
    </nav>

    @isset($header)
        <header class="app-header">
            <div class="container-aptk">{{ $header }}</div>
        </header>
    @endisset

    <main class="app-main">
        <div class="container-aptk">
            {{ $slot }}
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
    @include('partials.theme-toggle')
</body>
</html>
