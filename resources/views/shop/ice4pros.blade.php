{{-- Identidade da Ice4Pros (layouts.ice4pros + ice4pros-tokens.css) nos dois
     endereços: /ice4pros na APTK e a raiz de ice4pros.com.br (MarcaHost).
     Leva 21: textos do briefing (docs/ice4pros-alteracoes.md) — literais.
     Títulos em caixa de frase no fonte: o CSS da marca põe em caixa alta. --}}
@extends('layouts.ice4pros')

@section('brand_title', 'Ice4Pros — Gelo em estado de arte')
@section('meta_description', 'Ice4Pros: gelo cristal profissional, padronizado e personalizável. O gelo oficial do Guia Michelin Brasil. Cubos, esferas, diamantes e retangulares para bares, restaurantes, hotéis e eventos.')
{{-- Mesma página nos dois contextos; o domínio próprio é o preferido. --}}
@section('canonical', config('marcas.marcas.ice4pros.url').'/')

@php
    $i4pWhats = whatsapp_link(config('marcas.marcas.ice4pros.whatsapp_mensagem'), 'ice4pros');
    $i4pProdutos = marca_url('ice4pros', 'produtos');
    $i4pFotos = 'img/marcas/ice4pros/fotos/';
@endphp

@push('styles')
<style>
  /* ---- LP Ice4Pros: componentes base em ice4pros-tokens.css ---- */
  .i4p-hero { border-bottom: 1px solid var(--i4p-borda); }
  .i4p-hero .i4p-container { display: grid; grid-template-columns: 1.15fr .85fr; gap: clamp(32px, 5vw, 64px); align-items: center; padding-block: clamp(48px, 7vw, 88px); }
  .i4p-hero h1 { font-size: clamp(2.6rem, 7vw, 4.6rem); margin-bottom: 20px; max-width: 13ch; }
  .i4p-hero .lead { font-size: 1.125rem; max-width: 560px; margin-bottom: 30px; }
  .i4p-video { justify-self: center; width: min(100%, 320px); }
  .i4p-video video { width: 100%; aspect-ratio: 9 / 16; max-height: 560px; object-fit: cover; border: 1px solid var(--i4p-borda); border-radius: var(--i4p-radius); background: var(--i4p-superficie); }

  /* Selo Michelin: faixa turquesa */
  .i4p-michelin .i4p-container { display: flex; align-items: center; justify-content: center; gap: 18px; padding-block: 20px; flex-wrap: wrap; text-align: center; }
  .i4p-michelin p { margin: 0; font-family: var(--i4p-font-display); font-size: 1.05rem; letter-spacing: .12em; text-transform: uppercase; }
  .i4p-michelin .m-star { font-size: 1.3rem; line-height: 1; }

  /* Sobre */
  .i4p-sobre .i4p-container { display: grid; grid-template-columns: 1fr 1fr; gap: clamp(32px, 5vw, 64px); align-items: center; }
  .i4p-num-strip { display: grid; grid-template-columns: repeat(3, auto); justify-content: start; gap: 12px 48px; margin-top: 30px; }
  .i4p-num-strip .num { display: block; font-family: var(--i4p-font-display); font-weight: 700; font-size: 2.6rem; line-height: 1; text-transform: uppercase; margin-bottom: 10px; }
  .i4p-num-strip .lbl { font-family: var(--i4p-font-display); font-size: 12px; letter-spacing: .16em; text-transform: uppercase; color: var(--i4p-texto-suave); }
  .i4p-foto { margin: 0; border-radius: var(--i4p-radius); overflow: hidden; background: var(--i4p-superficie); }
  .i4p-foto img { width: 100%; height: 100%; object-fit: cover; }
  .i4p-sobre .i4p-foto { aspect-ratio: 4 / 5; max-height: 620px; }

  /* Formatos (home) */
  .gelo-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
  .gelo-card { padding: 0; overflow: hidden; display: flex; flex-direction: column; }
  .gelo-card .i4p-foto { aspect-ratio: 4 / 5; border-radius: 0; }
  .gelo-card .g-corpo { padding: 22px 20px 26px; }

  /* Soluções e diferenciais */
  .sol-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
  .sol-card .s-kicker { display: block; font-family: var(--i4p-font-display); font-size: 12px; letter-spacing: .18em; text-transform: uppercase; color: var(--i4p-texto-suave); margin-bottom: 12px; }
  .dif-icone { display: block; width: 40px; height: 40px; color: var(--i4p-turquesa); margin-bottom: 18px; }

  /* Clientes: nome em caixa com contorno + foto 3:4 (ou placeholder) */
  .cli-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 28px 16px; list-style: none; margin: 0; padding: 0; }
  .cli-nome { display: grid; place-items: center; min-height: 44px; padding: 8px 10px; margin-bottom: 8px; border: 1px solid var(--i4p-borda-forte); font-family: var(--i4p-font-display); font-size: 14px; letter-spacing: .1em; text-transform: uppercase; text-align: center; line-height: 1.2; }
  .cli-foto { aspect-ratio: 3 / 4; }
  .cli-ph { aspect-ratio: 3 / 4; display: grid; place-content: center; justify-items: center; gap: 12px; background: var(--i4p-borda); color: var(--i4p-texto-suave); font-size: 14px; }
  .cli-ph svg { width: 44px; height: 44px; }

  @media (max-width: 980px) {
    .i4p-hero .i4p-container, .i4p-sobre .i4p-container { grid-template-columns: 1fr; }
    .gelo-grid { grid-template-columns: repeat(2, 1fr); }
    .sol-grid { grid-template-columns: 1fr; }
    .cli-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  }
  @media (max-width: 640px) { .cli-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 560px) {
    .i4p-num-strip { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px 16px; }
    .i4p-num-strip .num { font-size: 1.7rem; }
  }
  @media (max-width: 400px) { .gelo-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')

  {{-- 1. HERO — vídeo vertical do cubo (mesmo arquivo da Collab) --}}
  <section class="i4p-hero">
    <div class="i4p-container">
      <div>
        <span class="i4p-eyebrow">Ice4Pros · marca da holding Small Batches</span>
        <h1>Gelo em estado de arte.</h1>
        <p class="lead">Gelo cristal profissional, padronizado e personalizável para quem cria, serve e celebra. Desde 2017, unimos água, hiperfiltração, precisão técnica e logotipagem pioneira no país para ampliar possibilidades criativas e elevar a experiência da alta coquetelaria.</p>
        <div class="i4p-ctas">
          <a href="{{ $i4pProdutos }}" class="i4p-btn">Conheça os produtos</a>
          <a href="{{ $i4pWhats }}" target="_blank" rel="noopener" class="i4p-btn i4p-btn--outline">Falar com o comercial</a>
        </div>
      </div>
      <div class="i4p-video">
        <video autoplay muted loop playsinline poster="{{ asset('img/aptk/parceiros-hero.jpg') }}">
          <source src="{{ asset('video/ice4pros-cubo.mp4') }}" type="video/mp4">
        </video>
      </div>
    </div>
  </section>

  {{-- SELO MICHELIN --}}
  <section class="i4p-michelin i4p-faixa">
    <div class="i4p-container">
      <span class="m-star" aria-hidden="true">✦</span>
      <p>O gelo oficial do <strong>Guia Michelin Brasil</strong> — pelo 3º ano consecutivo</p>
      <span class="m-star" aria-hidden="true">✦</span>
    </div>
  </section>

  {{-- 3. SOBRE A MARCA --}}
  <section class="i4p-section i4p-sobre">
    <div class="i4p-container">
      <div>
        <span class="i4p-eyebrow">Sobre a marca</span>
        <h2 class="i4p-section-title">Inovação em estado sólido</h2>
        <p>Há quase uma década, a Ice4Pros contribui para transformar a percepção do gelo na coquetelaria brasileira: de um simples insumo a um elemento fundamental na construção de cada bebida.</p>
        <p>Com estrutura fabril própria e tecnologia especializada de corte, desenvolvemos soluções que combinam excelência técnica e liberdade criativa. Da diversidade de formatos às possibilidades de personalização, nosso compromisso é respeitar a intenção de cada receita e potencializar o trabalho dos profissionais da coquetelaria.</p>
        <div class="i4p-num-strip">
          <div><span class="num i4p-destaque">Fábrica</span><span class="lbl">Própria</span></div>
          <div><span class="num i4p-destaque">3×</span><span class="lbl">Guia Michelin</span></div>
          <div><span class="num i4p-destaque">4×</span><span class="lbl">Mais duração</span></div>
        </div>
      </div>
      <figure class="i4p-foto">
        <img src="{{ asset($i4pFotos.'producao-gelo.jpg') }}" alt="Produção do gelo cristal Ice4Pros" loading="lazy" width="720" height="900">
      </figure>
    </div>
  </section>

  {{-- 4. PRODUTOS — os formatos --}}
  <section class="i4p-section i4p-section--superficie">
    <div class="i4p-container">
      <div class="i4p-section-head">
        <span class="i4p-eyebrow">Produtos</span>
        <h2 class="i4p-section-title">Cada formato, uma intenção.</h2>
        <p>Formatos padronizados, transparência excepcional e precisão para elevar a experiência de cada coquetel.</p>
      </div>
      <div class="gelo-grid">
        @foreach ([
            ['Cubos', 'cubo50.jpg', 'Ideais para Negroni, Old Fashioned, Penicillin, Boulevardier e suas variações.'],
            ['Esfera', 'formato-esfera.jpg', 'Ideais para Whisky on the Rocks, Godfather, Rusty Nail e coquetéis autorais que valorizam a apresentação e o controle da diluição.'],
            ['Diamantes', 'formato-diamante.jpg', 'Ideais para releituras de clássicos servidos em taças coupe, como Manhattan, Daiquiri, Aviation e Sidecar, além de criações autorais.'],
            ['Retangulares', 'retangular.jpg', 'Ideais para Paloma, Highball, Tom Collins e demais long drinks.'],
        ] as [$nome, $foto, $texto])
          <div class="i4p-card gelo-card">
            <figure class="i4p-foto">
              <img src="{{ asset($i4pFotos.$foto) }}" alt="Gelo Ice4Pros no formato {{ mb_strtolower($nome) }}" loading="lazy">
            </figure>
            <div class="g-corpo">
              <h3>{{ $nome }}</h3>
              <p>{{ $texto }}</p>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- 5. SOLUÇÕES --}}
  <section class="i4p-section">
    <div class="i4p-container">
      <div class="i4p-section-head">
        <span class="i4p-eyebrow">Soluções</span>
        <h2 class="i4p-section-title">Soluções à altura de cada experiência.</h2>
      </div>
      <div class="sol-grid">
        <div class="i4p-card sol-card">
          <span class="s-kicker">Trade</span>
          <h3>Bares, restaurantes &amp; hospitalidade</h3>
          <p>Abastecimento recorrente com qualidade, precisão e consistência. Soluções desenvolvidas para atender às necessidades de cada operação, respeitando a identidade de suas criações e a excelência do serviço.</p>
        </div>
        <div class="i4p-card sol-card">
          <span class="s-kicker">Marcas</span>
          <h3>Gelo personalizado</h3>
          <p>Transformamos o gelo em uma extensão da identidade de cada marca. Personalizações, formatos exclusivos e soluções criativas para ativações, lançamentos e experiências que valorizam cada detalhe.</p>
        </div>
        <div class="i4p-card sol-card">
          <span class="s-kicker">Eventos</span>
          <h3>Eventos e celebrações</h3>
          <p>Soluções em gelo profissional que aliam capacidade produtiva, planejamento e consistência para casamentos, celebrações, festivais e eventos corporativos.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- 6. PRINCIPAIS CLIENTES — config/ice4pros.php (foto null = placeholder) --}}
  <section class="i4p-section i4p-section--superficie">
    <div class="i4p-container">
      <div class="i4p-section-head">
        <span class="i4p-eyebrow">Principais clientes</span>
        <h2 class="i4p-section-title">Ao lado de quem eleva a coquetelaria.</h2>
      </div>
      <ul class="cli-grid">
        @foreach (config('ice4pros.clientes', []) as $cliente)
          <li class="cli-item">
            <span class="cli-nome">{{ $cliente['nome'] }}</span>
            @if (! empty($cliente['foto']))
              <figure class="i4p-foto cli-foto">
                <img src="{{ asset($i4pFotos.$cliente['foto']) }}" alt="Coquetel com gelo Ice4Pros no {{ $cliente['nome'] }}" loading="lazy">
              </figure>
            @else
              {{-- FOTO: coquetel do parceiro ({{ $cliente['nome'] }}), pendente da marca --}}
              <div class="cli-ph" data-foto-pendente>
                <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 8h32L24 26Z"/><path d="M24 26v14"/><path d="M15 40h18"/></svg>
                <span>Foto do drink</span>
              </div>
            @endif
          </li>
        @endforeach
      </ul>
    </div>
  </section>

  {{-- 7. DIFERENCIAIS — ícones de traço fino em turquesa (refs 07-icone-card*) --}}
  <section class="i4p-section">
    <div class="i4p-container">
      <div class="i4p-section-head">
        <span class="i4p-eyebrow">Diferenciais</span>
        <h2 class="i4p-section-title">A excelência está nos detalhes.</h2>
      </div>
      <div class="sol-grid">
        <div class="i4p-card sol-card">
          <svg class="dif-icone" viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 4 7 9v9c0 8.5 5.5 15 13 18 7.5-3 13-9.5 13-18V9Z"/><path d="m14 20 4.5 4.5L27 16"/></svg>
          <h3>Qualidade e precisão</h3>
          <p>Produzidos com água hiperfiltrada e rigorosos processos de tratamento, corte e padronização, os gelos Ice4Pros unem pureza, consistência e desempenho em cada formato.</p>
        </div>
        <div class="i4p-card sol-card">
          <svg class="dif-icone" viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 5h20M10 35h20"/><path d="M12 5c0 7 8 9 8 15s-8 8-8 15"/><path d="M28 5c0 7-8 9-8 15s8 8 8 15"/></svg>
          <h3>Controle da diluição</h3>
          <p>Estrutura compacta e processos especializados de produção proporcionam um derretimento mais lento e controlado, preservando o equilíbrio, a intensidade e o perfil sensorial de cada coquetel por mais tempo.</p>
        </div>
        <div class="i4p-card sol-card">
          <svg class="dif-icone" viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 6h18l7 9-16 20L4 15Z"/><path d="M4 15h32"/><path d="m15 6-3 9 8 20 8-20-3-9"/></svg>
          <h3>Transparência e acabamento</h3>
          <p>Técnicas especializadas de produção e lapidação resultam em gelos cristalinos, com geometria precisa e acabamento refinado, valorizando a apresentação e a experiência de cada coquetel.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- 8. ENCERRAMENTO — mesmo banner do portfólio --}}
  <x-ice4pros.encerramento />

@endsection
