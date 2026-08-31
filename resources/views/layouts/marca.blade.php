@php
    /* Layout isolado pra domínio próprio de marca (config/marcas.php).
       Recebe $marca do middleware MarcaHost. Mesmos tokens da APTK
       (aptk.css em cima do Bootstrap), dark fixo — mas sem header/nav/
       busca/login/carrinho/feature band da loja, e sem nenhum form:
       a resposta sai antes da sessão, CSRF não existe neste domínio. */
@endphp
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('brand_title', $marca['nome'])</title>
  <meta name="description" content="@yield('meta_description')">

  @hasSection('canonical')
    <link rel="canonical" href="@yield('canonical')">
  @endif
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="{{ $marca['nome'] }}">
  <meta property="og:title" content="@yield('brand_title', $marca['nome'])">
  <meta property="og:description" content="@yield('meta_description')">
  <meta property="og:url" content="{{ $marca['url'] }}/">

  {{-- Tipografia oficial: PP Rader + Neue World, auto-hospedadas (aptk-tokens.css). --}}
  <link rel="preload" href="{{ asset('fonts/PPRader-Bold.woff2') }}" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="{{ asset('fonts/PPRader-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>

  {{-- Bootstrap 5 antes do aptk.css (tematizado por cima), como no layout público. --}}
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  @include('partials.theme-init')
  <link href="{{ asset('css/aptk.css') }}" rel="stylesheet">

  <style>
  /* ---- Layout de marca: header e footer mínimos ---- */
  .marca-header { border-bottom: 1px solid var(--color-border); }
  .marca-header .container-aptk { height: var(--header-h); display: flex; align-items: center; }
  .marca-header .marca-nome { font-family: var(--font-display); font-size: 19px; letter-spacing: 0.02em; color: var(--color-text); }

  .marca-footer { border-top: 1px solid var(--color-border); padding-block: 30px; }
  .marca-footer .container-aptk { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
  .marca-footer p { margin: 0; color: var(--color-text-muted); font-size: var(--text-xs); }
  .marca-footer a { color: var(--color-text-muted); text-decoration: underline; text-underline-offset: 3px; }
  .marca-footer a:hover { color: var(--color-primary); }
  </style>

  {{-- CSS específico da página (vem por último, pode sobrescrever o layout) --}}
  @stack('styles')
</head>

<body>

  <header class="marca-header">
    <div class="container-aptk">
      <span class="marca-nome">{{ $marca['nome'] }}</span>
    </div>
  </header>

  <main>
    @yield('content')
  </main>

  <footer class="marca-footer">
    <div class="container-aptk">
      <p>{{ $marca['nome'] }} · contato pelo <a href="{{ aptk_route('pages.show', 'collabs') }}">comercial</a></p>
      <p>uma marca <a href="{{ aptk_route('home') }}">Small Batches</a> · © {{ date('Y') }}</p>
    </div>
  </footer>

  @stack('scripts')
</body>
</html>
