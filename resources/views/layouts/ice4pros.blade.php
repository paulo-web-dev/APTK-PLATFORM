@php
    /* Layout da Ice4Pros: identidade própria (ice4pros-tokens.css), sem
       Bootstrap, sem aptk.css/aptk-tokens.css e sem as fontes da APTK.
       Serve a LP nos dois endereços: /ice4pros (domínio da APTK) e a raiz
       de ice4pros.com.br (MarcaHost). No domínio de marca a resposta sai
       antes da sessão: nada de form/CSRF aqui. Links pra APTK sempre via
       aptk_route(). Dark fixo pela própria marca: sem theme-init/toggle. */
    $i4p = config('marcas.marcas.ice4pros');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('brand_title', $i4p['nome'])</title>
  <meta name="description" content="@yield('meta_description')">
  <meta name="theme-color" content="#231F20">
  <meta name="color-scheme" content="dark">

  <link rel="icon" type="image/svg+xml" href="{{ asset('img/marcas/ice4pros/favicon.svg') }}">

  @hasSection('canonical')
    <link rel="canonical" href="@yield('canonical')">
  @endif
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="{{ $i4p['nome'] }}">
  <meta property="og:title" content="@yield('brand_title', $i4p['nome'])">
  <meta property="og:description" content="@yield('meta_description')">
  <meta property="og:url" content="{{ $i4p['url'] }}/">
  <meta property="og:image" content="{{ asset('img/aptk/home-ice4pros.jpg') }}">

  <link rel="preload" href="{{ asset('fonts/Oswald-Bold.woff2') }}" as="font" type="font/woff2" crossorigin>
  <link href="{{ asset('css/ice4pros-tokens.css') }}" rel="stylesheet">

  {{-- CSS específico da página (vem por último) --}}
  @stack('styles')
</head>

<body data-marca="ice4pros">

  <header class="i4p-header">
    <div class="i4p-container">
      <a href="{{ $i4p['url'] }}/" class="i4p-logo" aria-label="Ice4Pros — início">
        <img src="{{ asset('img/marcas/ice4pros/ice4pros-wordmark.svg') }}" alt="Ice4Pros" width="170" height="37">
      </a>
      <nav class="i4p-header-nav" aria-label="Ice4Pros">
        <a href="{{ aptk_route('home') }}" class="i4p-header-link i4p-header-link--opcional">APTK Spirits</a>
        <a href="{{ aptk_route('pages.show', 'collabs') }}" class="i4p-btn i4p-btn--outline i4p-btn--sm">Comercial</a>
      </nav>
    </div>
  </header>

  <main>
    @yield('content')
  </main>

  <footer class="i4p-footer">
    <div class="i4p-container">
      <p>{{ $i4p['nome'] }} · contato pelo <a href="{{ aptk_route('pages.show', 'collabs') }}">comercial</a></p>
      <p>uma marca <a href="{{ aptk_route('home') }}">Small Batches</a> · © {{ date('Y') }}</p>
    </div>
  </footer>

  @stack('scripts')
</body>
</html>
