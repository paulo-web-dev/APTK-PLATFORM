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

  /* Hero com vídeo vertical (leva 02 — Collab/Ice4Pros): texto à esquerda,
     vídeo retrato 9:16 à direita, altura limitada pra não dominar a dobra. */
  .pg-hero--split { border-bottom: 1px solid var(--color-border); }
  .pg-hero--split .container-aptk { display: grid; grid-template-columns: 1.15fr .85fr; gap: clamp(32px, 5vw, 64px); align-items: center; padding-block: 56px 48px; }
  .hero-vvideo { position: relative; justify-self: center; width: min(100%, 340px); }
  .hero-vvideo video { display: block; width: 100%; aspect-ratio: 9 / 16; max-height: 560px; object-fit: cover; border-radius: var(--radius-lg); border: 1px solid var(--color-border); box-shadow: var(--shadow-card); background: #0D0A06; }
  .hero-vvideo .photo-tag { position: absolute; left: 12px; bottom: 12px; background: color-mix(in srgb, var(--color-ink) 70%, transparent); color: var(--color-cream); font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.14em; text-transform: uppercase; padding: 6px 10px; border-radius: var(--radius-sm); backdrop-filter: blur(4px); }
  @media (max-width: 860px) { .pg-hero--split .container-aptk { grid-template-columns: 1fr; } .hero-vvideo { justify-self: start; } }

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

  /* ---- Cases / portfólio (grade de collabs — usado na Collab) ---- */
  .cases-band { background: var(--color-bg-card); border-block: 1px solid var(--color-border); }
  @media (max-width: 520px) {
    .pg-hero .container-aptk { padding-block: 52px 40px; }
    .pg-hero .btn-aptk, .pg-closing .cta-row .btn-aptk { width: 100%; }
  }

  /* ---- Chips (ocasiões) — leva 05 ---- */
  .pg-chips { border-bottom: 1px solid var(--color-border); }
  .pg-chips .container-aptk { padding-block: clamp(28px, 4vw, 40px); display: flex; flex-direction: column; gap: 16px; align-items: center; }
  .pg-chips .eyebrow { margin: 0; }
  .pg-chips .chips-row { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
  .pg-chips .chip { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-text-muted); border: 1px solid var(--color-border); border-radius: 100px; padding: 9px 18px; }

  /* ---- Passos numerados (como funciona / processo) — leva 05 ---- */
  .pg-steps { background: var(--color-bg-card); border-block: 1px solid var(--color-border); }
  .steps-list { max-width: 720px; margin-inline: auto; display: flex; flex-direction: column; }
  .step-item { display: grid; grid-template-columns: 56px 1fr; gap: 18px; padding: 20px 0; border-bottom: 1px solid var(--color-border); }
  .step-item:last-child { border-bottom: 0; }
  .step-item .st-num { font-family: var(--font-mono); font-size: var(--text-xl); color: var(--color-primary); }
  .step-item h3 { font-family: var(--font-display); font-size: var(--text-lg); margin: 0 0 4px; }
  .step-item p { color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.7; margin: 0; }
  .steps-note { max-width: 720px; margin: 22px auto 0; text-align: center; font-size: var(--text-xs); color: var(--color-text-muted); font-family: var(--font-mono); letter-spacing: 0.04em; }
  .cases-footer { max-width: 760px; margin: 26px auto 0; text-align: center; color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.8; }
</style>
@endpush

@section('content')

  {{-- HERO — três variantes: com vídeo vertical ('video'), com imagem de
       fundo ('image') ou simples. --}}
  @if (! empty($page['video']))
  <section class="pg-hero pg-hero--split">
    <div class="container-aptk">
      <div>
        <span class="eyebrow">{{ $page['eyebrow'] }}</span>
        <h1>{{ $page['title'] }}</h1>
        <p class="lead">{{ $page['lead'] }}</p>
        @if (! empty($page['hero_cta']))
          <a href="{{ $page['hero_cta']['href'] }}" class="btn-aptk">{{ $page['hero_cta']['label'] }}</a>
        @endif
      </div>
      <div class="hero-vvideo">
        <video autoplay muted loop playsinline
               @if (! empty($page['image'])) poster="{{ asset($page['image']) }}" @endif>
          <source src="{{ asset($page['video']) }}" type="video/mp4">
        </video>
        @if (! empty($page['video_tag']))
          <span class="photo-tag">{{ $page['video_tag'] }}</span>
        @endif
      </div>
    </div>
  </section>
  @else
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
  @endif

  {{-- BLOCOS --}}
  {{-- OCASIÕES (chips) — só quando a página define 'chips' (leva 05). --}}
  @if (! empty($page['chips']))
  <section class="pg-chips">
    <div class="container-aptk">
      @if (! empty($page['chips_eyebrow']))<span class="eyebrow">{{ $page['chips_eyebrow'] }}</span>@endif
      <div class="chips-row">
        @foreach ($page['chips'] as $chip)
          <span class="chip">{{ $chip }}</span>
        @endforeach
      </div>
    </div>
  </section>
  @endif

  @if (! empty($page['features']))
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

  @endif

  {{-- CASES / PORTFÓLIO (grade de collabs, leva 14) — só quando a página
       define 'cases' (itens de config/collabs.php, PageController::collabs). --}}
  @if (! empty($page['cases']))
  <section class="section cases-band">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">{{ $page['cases_eyebrow'] ?? 'Projetos feitos' }}</span>
        <h2 class="section-title">{{ $page['cases_title'] ?? 'Quem já criou com a gente' }}</h2>
        @if (! empty($page['cases_lead']))
          <p>{{ $page['cases_lead'] }}</p>
        @endif
      </div>

      <x-portfolio-grid :itens="$page['cases']" />
      @if (! empty($page['cases_footer']))
        <p class="cases-footer">{{ $page['cases_footer'] }}</p>
      @endif
    </div>
  </section>
  @endif

  {{-- PASSOS (como funciona / processo) — só quando a página define 'steps' (leva 05). --}}
  @if (! empty($page['steps']))
  <section class="section pg-steps">
    <div class="container-aptk">
      <div class="section-head">
        @if (! empty($page['steps_eyebrow']))<span class="eyebrow">{{ $page['steps_eyebrow'] }}</span>@endif
        @if (! empty($page['steps_title']))<h2 class="section-title">{{ $page['steps_title'] }}</h2>@endif
      </div>
      <div class="steps-list">
        @foreach ($page['steps'] as $i => $step)
          <div class="step-item">
            <span class="st-num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
            <div>
              <h3>{{ $step['h'] }}</h3>
              <p>{{ $step['p'] }}</p>
            </div>
          </div>
        @endforeach
      </div>
      @if (! empty($page['steps_note']))
        <p class="steps-note">{{ $page['steps_note'] }}</p>
      @endif
    </div>
  </section>
  @endif

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
