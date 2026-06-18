<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso · APTK Spirits</title>

    {{-- Tipografia oficial APTK (auto-hospedada em aptk-tokens.css) --}}
    <link rel="preload" href="{{ asset('fonts/PPRader-Bold.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/PPRader-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @include('partials.theme-init')
    <link href="{{ asset('css/aptk.css') }}" rel="stylesheet">

    <style>
        .auth-wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        .auth-card { width: 100%; max-width: 420px; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 40px 32px; box-shadow: var(--shadow-card); }
        .auth-logo { display: flex; justify-content: center; font-size: 22px; text-decoration: none; }
        .auth-logo .aptk-logo { color: var(--color-text); }
        .auth-kicker { display: block; text-align: center; margin: 14px 0 26px; }
        .auth-sub { text-align: center; color: var(--color-text-muted); font-size: var(--text-sm); margin-bottom: 26px; }
        .auth-card label { display: block; font-size: var(--text-sm); color: var(--color-text); margin-bottom: 6px; font-weight: 500; }
        .auth-card .form-control { background: var(--color-bg-elevated); border: 1px solid var(--color-border); color: var(--color-text); border-radius: var(--radius-md); padding: 11px 14px; }
        .auth-card .form-control:focus { background: var(--color-bg-elevated); border-color: var(--color-primary); box-shadow: none; color: var(--color-text); }
        .auth-card .form-control::placeholder { color: var(--color-text-muted); }
        .field-error { color: var(--color-danger); font-size: var(--text-xs); margin-top: 5px; }
        .form-check-input:checked { background-color: var(--color-primary); border-color: var(--color-primary); }
        .form-check-input:focus { border-color: var(--color-primary); box-shadow: none; }
        .auth-status { background: color-mix(in srgb, var(--color-success) 13%, transparent); border: 1px solid color-mix(in srgb, var(--color-success) 32%, transparent); color: var(--color-success); border-radius: var(--radius-md); padding: 10px 14px; font-size: var(--text-sm); margin-bottom: 18px; }
        .auth-foot { text-align: center; margin-top: 22px; font-size: var(--text-sm); color: var(--color-text-muted); }
        .auth-foot a { color: var(--color-primary); }
    </style>
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card">
            <a href="{{ url('/') }}" class="auth-logo" aria-label="APTK Spirits — início">
                <x-brand.logo :tag="true" />
            </a>
            <span class="eyebrow auth-kicker">Alquimia de histórias</span>
            {{ $slot }}
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @include('partials.theme-toggle')
</body>
</html>
