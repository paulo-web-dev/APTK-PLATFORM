@extends('layouts.public')

@section('title', 'Sobre · APTK Spirits')
@section('meta_description', 'A história da APTK Spirits: de Apothek Cocktails & Co. (2016) à APTK (2021). Alta coquetelaria engarrafada, feita por humanos inquietos.')

{{-- A página já tem manifesto e CTA próprios — esconde a faixa global do layout. --}}
@section('hide_feature_band', '1')

@push('styles')
<style>
  /* ---- Sobre: hero ---- */
  .ab-hero { position: relative; overflow: hidden; min-height: 56vh; display: flex; align-items: flex-end; }
  .ab-hero .ab-hero-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
  .ab-hero .ab-hero-ov { position: absolute; inset: 0; background: linear-gradient(180deg, color-mix(in srgb, var(--color-ink) 28%, transparent) 0%, color-mix(in srgb, var(--color-ink) 86%, transparent) 100%); }
  .ab-hero .ab-hero-in { position: relative; z-index: 1; padding-block: clamp(48px, 9vw, 96px); }
  .ab-hero .eyebrow { margin-bottom: 16px; }
  .ab-hero h1 { font-family: var(--font-display); font-size: clamp(2.6rem, 8vw, 5rem); color: var(--color-cream); margin: 0 0 16px; letter-spacing: -0.01em; }
  .ab-hero p { color: color-mix(in srgb, var(--color-cream) 84%, transparent); font-size: clamp(var(--text-base), 2vw, var(--text-xl)); max-width: 560px; margin: 0; }

  /* ---- Sobre: história ---- */
  .ab-story { display: grid; grid-template-columns: 1.1fr .9fr; gap: 56px; align-items: center; }
  .ab-story-copy p { color: var(--color-text-muted); line-height: 1.8; margin: 0 0 16px; font-size: var(--text-lg); }
  .ab-story-copy p strong { color: var(--color-text); font-weight: 700; }
  .ab-story-media img { width: 100%; aspect-ratio: 4 / 5; object-fit: cover; border-radius: var(--radius-lg); border: 1px solid var(--color-border); box-shadow: var(--shadow-card); }
  .ab-timeline { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 56px; border-top: 1px solid var(--color-border); padding-top: 36px; }
  .ab-step { display: flex; flex-direction: column; gap: 6px; }
  .ab-step .ab-year { font-family: var(--font-mono); font-size: var(--text-2xl); color: var(--color-primary); }
  .ab-step .ab-lbl { color: var(--color-text-muted); font-size: var(--text-sm); }

  /* ---- Sobre: manifesto (faixa escura) ---- */
  .ab-manifesto { background: var(--color-bg); text-align: center; border-block: 1px solid var(--color-border); }
  .ab-manifesto .ab-sun { width: 120px; color: var(--color-primary); margin: 0 auto 20px; display: block; }
  .ab-manifesto .eyebrow { margin-bottom: 14px; }
  .ab-manifesto h2 { font-family: var(--font-display); font-size: clamp(2rem, 5vw, 3.2rem); color: var(--color-text); margin: 0 0 16px; }
  .ab-manifesto .ab-lead { color: var(--color-text-muted); font-size: var(--text-lg); line-height: 1.7; max-width: 600px; margin: 0 auto; }
  .ab-manifesto .ab-div { width: 220px; color: var(--color-primary); margin: 30px auto; display: block; }
  .ab-values { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; max-width: 860px; margin: 0 auto; text-align: left; }
  .ab-values > div { padding: 24px; border: 1px solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-bg-card); }
  .ab-values .k { font-family: var(--font-mono); font-size: var(--text-xs); color: var(--color-primary); letter-spacing: 0.14em; }
  .ab-values .v { display: block; font-family: var(--font-display); font-size: var(--text-xl); color: var(--color-text); margin: 8px 0 6px; }
  .ab-values .d { color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.6; }

  /* ---- Sobre: galeria da loja ---- */
  .ab-gallery { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
  .ab-gallery img { width: 100%; aspect-ratio: 3 / 4; object-fit: cover; border-radius: var(--radius-md); border: 1px solid var(--color-border); }
  .ab-malls { margin-top: 34px; text-align: center; color: var(--color-text); font-family: var(--font-mono); font-size: var(--text-sm); letter-spacing: 0.06em; }
  .ab-malls .eyebrow { display: inline-flex; margin-bottom: 12px; }

  /* ---- Sobre: CTA ---- */
  .ab-cta { text-align: center; }
  .ab-cta h2 { font-family: var(--font-display); font-size: clamp(2rem, 5vw, 3rem); color: var(--color-text); margin: 0 0 12px; }
  .ab-cta p { color: var(--color-text-muted); font-size: var(--text-lg); margin: 0 0 28px; }
  .ab-cta-btns { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }

  /* ---- Sobre: responsivo ---- */
  @media (max-width: 900px) {
    .ab-story { grid-template-columns: 1fr; gap: 32px; }
    .ab-timeline { grid-template-columns: 1fr; gap: 18px; }
    .ab-values { grid-template-columns: 1fr; }
    .ab-gallery { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 520px) {
    .ab-gallery { grid-template-columns: 1fr; }
    .ab-cta-btns .btn-aptk { width: 100%; }
  }
</style>
@endpush

@section('content')

  {{-- HERO --}}
  <section class="ab-hero on-dark">
    <img class="ab-hero-bg" src="{{ asset('img/aptk/loja-aptk.jpg') }}" alt="Loja física da APTK Spirits no shopping">
    <div class="ab-hero-ov"></div>
    <div class="container-aptk ab-hero-in">
      <span class="eyebrow">Quem somos</span>
      <h1>Alquimia de histórias</h1>
      <p>Alta coquetelaria engarrafada, feita por humanos inquietos — para despertar sentidos e abrir novas histórias, do nosso balcão à sua mesa.</p>
    </div>
  </section>

  {{-- HISTÓRIA --}}
  <section class="section">
    <div class="container-aptk">
      <div class="ab-story">
        <div class="ab-story-copy">
          <span class="eyebrow">Nossa história</span>
          <h2 class="section-title">De 2016 a hoje</h2>
          <p>Tudo começou em 2016 como <strong>Apothek Cocktails &amp; Co.</strong>, uma casa paulistana pioneira na alta coquetelaria engarrafada — drinks de bar levados a sério, prontos para servir.</p>
          <p>Em 2021 nasce a <strong>APTK Spirits</strong>: a mesma obsessão pelo detalhe, agora com alma brasileira, dialogando com todos os sotaques e culturas do país.</p>
          <p>Hoje somos três mundos em uma só casa — alta coquetelaria, drinks prontos para consumo e uma linha de bases para quem cria a própria coquetelaria.</p>
        </div>
        <div class="ab-story-media">
          <img src="{{ asset('img/aptk/hero-bartender.jpg') }}" alt="Bartender da APTK no preparo de um drink">
        </div>
      </div>

      <div class="ab-timeline">
        <div class="ab-step"><span class="ab-year">2016</span><span class="ab-lbl">Nasce a Apothek Cocktails &amp; Co., pioneira em coquetelaria engarrafada.</span></div>
        <div class="ab-step"><span class="ab-year">2021</span><span class="ab-lbl">A Apothek vira APTK Spirits, com alma brasileira.</span></div>
        <div class="ab-step"><span class="ab-year">Hoje</span><span class="ab-lbl">Clássicos, Autorais e Bases — uma holding, três mundos.</span></div>
      </div>
    </div>
  </section>

  {{-- MANIFESTO --}}
  <section class="section section--dark ab-manifesto">
    <div class="container-aptk">
      <x-brand.sunburst class="ab-sun" />
      <span class="eyebrow">Manifesto</span>
      <h2>Feito por humanos inquietos</h2>
      <p class="ab-lead">Somos amantes e criadores: apaixonados pelos detalhes, inconformados com o óbvio e comprometidos com o legado de cada história que ajudamos a começar.</p>
      <x-brand.divider class="ab-div" />
      <div class="ab-values">
        <div>
          <span class="k">01</span>
          <span class="v">Qualidade</span>
          <span class="d">Cuidado em cada etapa, do destilado ao rótulo.</span>
        </div>
        <div>
          <span class="k">02</span>
          <span class="v">Autenticidade</span>
          <span class="d">Pontos de vista próprios — a gente não segue manual.</span>
        </div>
        <div>
          <span class="k">03</span>
          <span class="v">Excelência</span>
          <span class="d">O padrão de bar, engarrafado para durar.</span>
        </div>
      </div>
    </div>
  </section>

  {{-- A CASA / LOJA --}}
  <section class="section">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">A casa</span>
        <h2 class="section-title">Onde a alquimia acontece</h2>
        <p>Nossa loja física é balcão, laboratório e ponto de encontro: drinks engarrafados, edições limitadas e a curadoria completa da casa.</p>
      </div>
      <div class="ab-gallery">
        <img src="{{ asset('img/aptk/cat-loja.jpg') }}" alt="Balcão da loja APTK com o menu de coquetéis" loading="lazy">
        <img src="{{ asset('img/aptk/about-loja-2.jpg') }}" alt="Interior da loja física da APTK" loading="lazy">
        <img src="{{ asset('img/aptk/about-loja-3.jpg') }}" alt="Prateleiras de garrafas da loja APTK" loading="lazy">
        <img src="{{ asset('img/aptk/cat-pessoas.jpg') }}" alt="Pessoas brindando com drinks da APTK" loading="lazy">
      </div>
      <p class="ab-malls">
        <span class="eyebrow">Onde encontrar</span><br>
        São Paulo · Belo Horizonte · Rio de Janeiro · Curitiba · Brasília
      </p>
    </div>
  </section>

  {{-- CTA --}}
  <section class="section ab-cta">
    <div class="container-aptk">
      <h2>Drinks &amp; histórias pra contar</h2>
      <p>Conheça o portfólio 2026 e leve a APTK para a sua próxima história.</p>
      <div class="ab-cta-btns">
        <a href="{{ route('catalog') }}" class="btn-aptk">Conhecer o portfólio</a>
        <a href="{{ route('pages.show', 'clube') }}" class="btn-aptk btn-aptk--outline">Entrar no clube</a>
      </div>
    </div>
  </section>

@endsection
