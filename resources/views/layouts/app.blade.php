<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'APTK') }} · Minha conta</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/aptk.css') }}" rel="stylesheet">

    @stack('styles')

    <style>
        .app-nav { position: sticky; top: 0; z-index: 100; background: color-mix(in srgb, var(--color-bg) 88%, transparent); backdrop-filter: blur(12px); border-bottom: 1px solid var(--color-border); }
        .app-nav .container-aptk { height: var(--header-h); display: flex; align-items: center; gap: 22px; }
        .app-nav .brand { display: flex; align-items: center; gap: 10px; font-family: var(--font-display); font-weight: 700; font-size: var(--text-xl); letter-spacing: 0.18em; color: var(--color-text); }
        .app-nav .brand .brand-mark { width: 10px; height: 10px; background: var(--color-primary); border-radius: 2px; transform: rotate(45deg); }
        .app-nav .nav-links { display: flex; gap: 20px; }
        .app-nav .nav-links a { color: var(--color-text-muted); font-size: var(--text-sm); font-weight: 500; transition: color .2s ease; }
        .app-nav .nav-links a:hover { color: var(--color-text); }
        .app-nav .user-menu { margin-left: auto; }
        .user-btn { display: flex; align-items: center; gap: 9px; background: transparent; border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 7px 12px 7px 8px; color: var(--color-text); font-size: var(--text-sm); }
        .user-btn::after { margin-left: 2px; color: var(--color-text-muted); }
        .user-btn:hover { border-color: var(--color-primary-muted); }
        .user-btn .ava { width: 26px; height: 26px; border-radius: 50%; background: var(--gold-faint); border: 1px solid var(--color-primary-muted); color: var(--color-primary); display: grid; place-items: center; font-family: var(--font-mono); font-size: 11px; }
        .dropdown-menu { background: var(--color-bg-elevated); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 6px; }
        .dropdown-menu .dropdown-item { color: var(--color-text-muted); font-size: var(--text-sm); border-radius: var(--radius-sm); padding: 8px 12px; }
        .dropdown-menu .dropdown-item:hover, .dropdown-menu .dropdown-item:focus { background: var(--color-bg-card); color: var(--color-text); }
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
            <a href="{{ route('home') }}" class="brand"><span class="brand-mark"></span>APTK</a>
            <div class="nav-links">
                <a href="{{ route('home') }}">Loja</a>
                <a href="{{ route('dashboard') }}">Minha conta</a>
            </div>
            <div class="user-menu dropdown">
                <button class="user-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="ava">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    {{ auth()->user()->name }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Meu perfil</a></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">Sair</button>
                        </form>
                    </li>
                </ul>
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
</body>
</html>
