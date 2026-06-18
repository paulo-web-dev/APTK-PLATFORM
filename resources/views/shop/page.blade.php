@extends('layouts.public')

@section('title', $page['title'] . ' · APTK Spirits')
@section('meta_description', $page['lead'])

{{-- Tem fechamento próprio — esconde a faixa institucional do layout. --}}
@section('hide_feature_band', '1')

@push('styles')
<style>
  /* ---- Hero editorial (sem foto) ---- */
  .pg-hero { position: relative; overflow: hidden; }
  .pg-hero::before { content: ""; position: absolute; top: -12%; right: -6%; width: 560px; height: 560px; background: radial-gradient(circle, var(--gold-faint) 0%, transparent 65%); pointer-events: none; }
  .pg-hero .container-aptk { position: relative; z-index: 1; padding-block: 72px 52px; }
  .pg-hero .eyebrow { margin-bottom: 16px; }
  .pg-hero h1 { font-size: clamp(2.4rem, 6vw, 3.75rem); letter-spacing: -0.01em; margin: 0 0 20px; max-width: 18ch; }
  .pg-hero p.lead { font-size: var(--text-lg); color: var(--color-text-muted); max-width: 600px; margin: 0 0 30px; line-height: 1.7; }

  /* ---- Hero com foto (band escuro) ---- */
  .pg-hero--band { min-height: 52vh; display: flex; align-items: flex-end; border-bottom: 1px solid var(--color-border); }
  .pg-hero--band::before { display: none; }
  .pg-hero--band img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
  .pg-hero--band::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, color-mix(in srgb, var(--color-ink) 26%, transparent) 0%, color-mix(in srgb, var(--color-ink) 82%, transparent) 100%), color-mix(in srgb, var(--color-scotch) 16%, transparent); }
  .pg-hero--band .container-aptk { padding-block: 52px; }
  .pg-hero--band .eyebrow, .pg-hero--band h1 { color: var(--color-cream); }
  .pg-hero--band .eyebrow::before { background: var(--color-cream); }
  .pg-hero--band p.lead { color: color-mix(in srgb, var(--color-cream) 84%, transparent); }

  /* ---- Blocos / features ---- */
  .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 22px; }
  .pg-feature { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 28px 24px; }
  .pg-feature .f-num { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.16em; text-transform: uppercase; color: var(--color-primary); }
  .pg-feature h3 { font-family: var(--font-display); font-size: var(--text-xl); margin: 12px 0 8px; }
  .pg-feature p { color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.6; margin: 0; }

  /* ---- Fechamento (band escuro) ---- */
  .pg-closing { background: var(--color-bg); text-align: center; }
  .pg-closing .container-aptk { display: flex; flex-direction: column; align-items: center; }
  .pg-closing .sun { width: 120px; color: var(--color-primary); margin-bottom: 20px; }
  .pg-closing h2 { font-family: var(--font-display); font-size: clamp(1.9rem, 5vw, 2.8rem); color: var(--color-text); margin: 0 0 14px; }
  .pg-closing p { color: var(--color-text-muted); font-size: var(--text-lg); margin: 0 0 30px; max-width: 520px; }
  .pg-closing .cta-row { display: flex; gap: 14px; flex-wrap: wrap; justify-content: center; }

  @media (max-width: 520px) {
    .pg-hero .container-aptk { padding-block: 52px 40px; }
    .pg-hero .btn-aptk, .pg-closing .cta-row .btn-aptk { width: 100%; }
  }
</style>
@endpush

@section('content')

  {{-- HERO --}}
  <section class="pg-hero @if ($page['image']) pg-hero--band @endif">
    @if ($page['image'])
      <img src="{{ asset($page['image']) }}" alt="">
    @endif
    <div class="container-aptk">
      <span class="eyebrow">{{ $page['eyebrow'] }}</span>
      <h1>{{ $page['title'] }}</h1>
      <p class="lead">{{ $page['lead'] }}</p>
      @if (! empty($page['hero_cta']))
        <a href="{{ $page['hero_cta']['href'] }}" class="btn-aptk">{{ $page['hero_cta']['label'] }}</a>
      @endif
    </div>
  </section>

  {{-- BLOCOS --}}
  <section class="section">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">{{ $page['features_eyebrow'] }}</span>
        <h2 class="section-title">{{ $page['features_title'] }}</h2>
      </div>
      <div class="features-grid">
        @foreach ($page['features'] as $f)
          <div class="pg-feature">
            <span class="f-num">{{ $f['n'] }}</span>
            <h3>{{ $f['h'] }}</h3>
            <p>{{ $f['p'] }}</p>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- FECHAMENTO --}}
  <section class="section section--dark pg-closing">
    <div class="container-aptk">
      <x-brand.sunburst class="sun" />
      <h2>{{ $page['closing_title'] }}</h2>
      <p>{{ $page['closing_text'] }}</p>
      <div class="cta-row">
        <a href="{{ $page['closing_cta']['href'] }}" class="btn-aptk">{{ $page['closing_cta']['label'] }}</a>
        @if (! empty($page['closing_cta2']))
          <a href="{{ $page['closing_cta2']['href'] }}" class="btn-aptk btn-aptk--outline">{{ $page['closing_cta2']['label'] }}</a>
        @endif
      </div>
    </div>
  </section>

@endsection
