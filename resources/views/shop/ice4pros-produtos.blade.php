{{-- Portfólio Ice4Pros (leva 21): destino de "Conheça os produtos" e "Ver
     produtos". Identidade da Ice4Pros nos dois endereços: /ice4pros/produtos
     na APTK e ice4pros.com.br/produtos (MarcaHost, config marcas.paginas).
     Cards de config/ice4pros.php, no layout das referências
     docs/ice4pros-ref/02-portfolio-layout-ref-*.png: formatos de gelo e
     depois Custom Ice. Sem form/sessão (o domínio de marca não tem). --}}
@extends('layouts.ice4pros')

@section('brand_title', 'Produtos Ice4Pros — Formatos de gelo cristal')
@section('meta_description', 'Diferentes formatos, infinitas possibilidades. Conheça o portfólio de gelo cristal profissional da Ice4Pros: Super30, Cinquenta, 50 × 65, Collins, Esfera, Diamante e Custom Ice.')
@section('canonical', config('marcas.marcas.ice4pros.url').'/produtos')

@php $i4pFotos = 'img/marcas/ice4pros/fotos/'; @endphp

@push('styles')
<style>
  /* Topo: wordmark + texto sobre a foto dos formatos (bg-produtos.jpg) */
  .port-hero { position: relative; isolation: isolate; overflow: hidden; border-bottom: 1px solid var(--i4p-borda); background: var(--i4p-preto); }
  .port-hero__bg { position: absolute; inset: 0; z-index: -2; width: 100%; height: 100%; object-fit: cover; object-position: 70% center; }
  .port-hero::before { content: ""; position: absolute; inset: 0; z-index: -1; background: linear-gradient(90deg, var(--i4p-preto) 0%, color-mix(in srgb, var(--i4p-preto) 82%, transparent) 42%, color-mix(in srgb, var(--i4p-preto) 10%, transparent) 100%); }
  .port-hero .i4p-container { padding-block: clamp(64px, 10vw, 128px); }
  .port-hero h1 { margin: 0 0 26px; }
  .port-hero h1 .i4p-wordmark { width: clamp(220px, 34vw, 380px); height: auto; }
  .port-hero .port-sub { font-family: var(--i4p-font-display); font-weight: 700; font-size: clamp(1.5rem, 3.4vw, 2.2rem); letter-spacing: var(--i4p-tracking); text-transform: uppercase; line-height: 1.1; margin: 0 0 14px; }
  .port-hero p:last-child { max-width: 520px; font-size: 1.125rem; margin: 0; }

  /* Cabeçalho dos blocos com fio turquesa embaixo (refs) */
  .i4p-section-head.port-head { border-bottom: 1px solid var(--i4p-borda-escura); padding-bottom: 18px; margin-bottom: clamp(24px, 3vw, 36px); max-width: none; }
  .port-head .i4p-eyebrow { color: var(--i4p-turquesa); }
  .port-head .i4p-eyebrow::before { display: none; }
  .port-head h2 { font-size: clamp(1.9rem, 4.5vw, 3rem); margin: 0; }
  .port-head p { margin: 10px 0 0; font-size: 1.125rem; }

  /* Cards de formato: foto grande arredondada, nome, especificação turquesa */
  .port-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; list-style: none; margin: 0; padding: 0; }
  .port-card { background: var(--i4p-superficie); border: 1px solid var(--i4p-borda); border-radius: 10px; padding: 8px 8px 22px; height: 100%; }
  .port-card__foto { margin: 0 0 18px; aspect-ratio: 4 / 3; border-radius: 18px; overflow: hidden; background: var(--i4p-preto); }
  .port-card__foto img { width: 100%; height: 100%; object-fit: cover; object-position: center 70%; }
  .port-card h3 { font-size: 1.5rem; margin: 0 12px 8px; }
  .port-card__spec { margin: 0 12px 14px; font-family: var(--i4p-font-display); font-size: 14px; letter-spacing: .04em; color: var(--i4p-turquesa); }
  .port-card__spec span { margin-inline: 6px; }
  .port-card__drinks, .port-card__pack { margin: 0 12px; font-size: 15px; line-height: 1.45; }
  .port-card__pack { text-transform: uppercase; letter-spacing: .04em; }

  /* Custom Ice: foto + legenda com fio turquesa à esquerda (ref c) */
  .ci-card { margin: 0; height: 100%; display: flex; flex-direction: column; }
  .ci-card__foto { aspect-ratio: 1 / 1; overflow: hidden; background: var(--i4p-superficie); }
  .ci-card__foto img { width: 100%; height: 100%; object-fit: cover; }
  .ci-card figcaption { flex: 1; background: var(--i4p-superficie); border-left: 4px solid var(--i4p-turquesa); padding: 14px 16px 18px; }
  .ci-card h3 { font-size: 1.3rem; margin: 0 0 6px; }
  .ci-card p { margin: 0; font-size: 15px; line-height: 1.45; }

  @media (max-width: 980px) { .port-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 600px) {
    .port-grid { grid-template-columns: 1fr; }
    .port-hero::before { background: color-mix(in srgb, var(--i4p-preto) 72%, transparent); }
  }
</style>
@endpush

@section('content')

  {{-- TOPO --}}
  <section class="port-hero">
    <img class="port-hero__bg" src="{{ asset($i4pFotos.'bg-produtos.jpg') }}" alt="" width="1600" height="533" fetchpriority="high">
    <div class="i4p-container">
      <h1><x-ice4pros.wordmark /></h1>
      <p class="port-sub">Explore nossos formatos</p>
      <p>Diferentes formatos, infinitas possibilidades. Conheça nosso portfólio de gelo cristal profissional, desenvolvido para valorizar cada criação, serviço e experiência.</p>
    </div>
  </section>

  {{-- FORMATOS DE GELO --}}
  <section class="i4p-section" id="formatos">
    <div class="i4p-container">
      <div class="i4p-section-head port-head">
        <span class="i4p-eyebrow">Portfólio de gelo profissional</span>
        <h2>Um formato para cada ritual.</h2>
      </div>
      <ul class="port-grid">
        @foreach (config('ice4pros.formatos', []) as $slug => $f)
          <li>
            <article class="port-card" data-formato="{{ $slug }}">
              <figure class="port-card__foto">
                <img src="{{ asset($i4pFotos.$f['foto']) }}" alt="Gelo Ice4Pros {{ $f['nome'] }}" loading="lazy">
              </figure>
              <h3>{{ $f['nome'] }}</h3>
              <p class="port-card__spec">{{ $f['medida'] }}<span aria-hidden="true">•</span>{{ $f['nota'] }}</p>
              <p class="port-card__drinks">{{ $f['drinks'] }}</p>
              <p class="port-card__pack">{{ $f['pack'] }}</p>
            </article>
          </li>
        @endforeach
      </ul>
    </div>
  </section>

  {{-- CUSTOM ICE --}}
  <section class="i4p-section i4p-section--superficie" id="custom-ice">
    <div class="i4p-container">
      <div class="i4p-section-head port-head">
        <span class="i4p-eyebrow">Custom Ice</span>
        <h2>Quando o gelo se transforma em experiência.</h2>
        <p>Personalização abre conversas que o gelo comum não abre.</p>
      </div>
      <ul class="port-grid">
        @foreach (config('ice4pros.custom', []) as $slug => $c)
          <li>
            <figure class="ci-card" data-custom="{{ $slug }}">
              <div class="ci-card__foto">
                <img src="{{ asset($i4pFotos.$c['foto']) }}" alt="{{ $c['nome'] }} — Custom Ice Ice4Pros" loading="lazy">
              </div>
              <figcaption>
                <h3>{{ $c['nome'] }}</h3>
                <p>{{ $c['texto'] }}</p>
              </figcaption>
            </figure>
          </li>
        @endforeach
      </ul>
    </div>
  </section>

  {{-- ENCERRAMENTO — o mesmo banner da LP, especificando os produtos --}}
  <x-ice4pros.encerramento eyebrow="Produtos Ice4Pros" produtos="#formatos" />

@endsection
