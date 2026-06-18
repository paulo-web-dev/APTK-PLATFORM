@extends('layouts.public')

@section('title', 'Clube APTK — Assinatura de drinks · APTK Spirits')
@section('meta_description', 'O clube de assinatura da APTK: receba drinks prontos e bases autorais em casa, todo mês. Cinco planos, do Descoberta ao Small Batch.')

@section('hide_feature_band', '1')

@push('styles')
<style>
  /* ---- Clube: hero split ---- */
  .clube-hero { position: relative; overflow: hidden; }
  .clube-hero::before { content: ""; position: absolute; top: -10%; right: -5%; width: 560px; height: 560px; background: radial-gradient(circle, var(--gold-faint) 0%, transparent 65%); pointer-events: none; }
  .clube-hero .container-aptk { position: relative; z-index: 1; padding-block: 64px; display: grid; grid-template-columns: 1.05fr .95fr; gap: 56px; align-items: center; }
  .clube-copy { max-width: 600px; }
  .clube-copy .script-line { font-family: var(--font-script); font-size: clamp(1.6rem, 4vw, var(--text-3xl)); color: var(--color-primary); margin-bottom: 12px; }
  .clube-copy h1 { font-size: clamp(2.4rem, 6vw, 3.75rem); letter-spacing: -0.01em; margin-bottom: 22px; }
  .clube-copy p { font-size: var(--text-lg); color: var(--color-text-muted); max-width: 520px; margin: 0 0 32px; }
  .clube-media { position: relative; }
  .clube-media img { display: block; width: 100%; aspect-ratio: 4 / 5; object-fit: cover; object-position: center; border-radius: var(--radius-lg); border: 1px solid var(--color-border); box-shadow: var(--shadow-card); }
  .clube-media .photo-tag { position: absolute; left: 16px; bottom: 16px; background: color-mix(in srgb, var(--color-ink) 70%, transparent); color: var(--color-cream); font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.14em; text-transform: uppercase; padding: 7px 12px; border-radius: var(--radius-sm); backdrop-filter: blur(4px); }

  /* ---- Clube: como funciona ---- */
  .how { background: var(--color-bg-card); border-block: 1px solid var(--color-border); }
  .how-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
  .how-step .how-num { font-family: var(--font-mono); font-size: var(--text-sm); letter-spacing: 0.18em; color: var(--color-primary); }
  .how-step h3 { font-family: var(--font-display); font-size: var(--text-xl); margin: 12px 0 8px; }
  .how-step p { color: var(--color-text-muted); font-size: var(--text-sm); margin: 0; line-height: 1.6; }

  /* ---- Clube: planos ---- */
  .plans-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; align-items: stretch; }
  .cl-plan { position: relative; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 30px 24px; display: flex; flex-direction: column; transition: border-color .25s ease, transform .25s ease; }
  .cl-plan:hover { border-color: var(--color-primary-muted); transform: translateY(-3px); }
  .cl-plan.is-featured { border-color: var(--color-primary); box-shadow: var(--shadow-glow); }
  .cl-plan .pl-name { font-family: var(--font-script); font-size: var(--text-2xl); color: var(--color-primary); margin-bottom: 4px; }
  .cl-plan .pl-kicker { font-size: var(--text-xs); color: var(--color-text-muted); letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 18px; min-height: 28px; }
  .cl-plan .pl-price { font-family: var(--font-mono); color: var(--color-text); font-size: var(--text-2xl); margin-bottom: 2px; }
  .cl-plan .pl-price small { font-size: var(--text-sm); color: var(--color-text-muted); }
  .cl-plan .pl-cycle { font-size: var(--text-xs); color: var(--color-text-muted); margin-bottom: 20px; }
  .cl-plan ul { list-style: none; margin: 0 0 24px; padding: 0; display: flex; flex-direction: column; gap: 10px; }
  .cl-plan li { color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.4; padding-left: 22px; position: relative; }
  .cl-plan li::before { content: ""; position: absolute; left: 0; top: 6px; width: 9px; height: 9px; border-radius: 50%; border: 2px solid var(--color-primary); }
  .cl-plan .btn-aptk { margin-top: auto; }
  .pl-badge { position: absolute; top: -11px; left: 50%; transform: translateX(-50%); background: var(--color-primary); color: var(--color-text-inverse); font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.14em; text-transform: uppercase; padding: 3px 12px; border-radius: var(--radius-sm); white-space: nowrap; }
  .plans-note { text-align: center; color: var(--color-text-muted); font-size: var(--text-sm); margin-top: 28px; }

  /* ---- Clube: FAQ ---- */
  .faq { max-width: 760px; margin-inline: auto; }
  .faq details { border-bottom: 1px solid var(--color-border); }
  .faq summary { cursor: pointer; list-style: none; padding: 20px 4px; display: flex; justify-content: space-between; align-items: center; gap: 16px; font-family: var(--font-display); font-size: var(--text-lg); color: var(--color-text); }
  .faq summary::-webkit-details-marker { display: none; }
  .faq summary::after { content: "+"; font-family: var(--font-mono); color: var(--color-primary); font-size: var(--text-xl); transition: transform .2s ease; }
  .faq details[open] summary::after { transform: rotate(45deg); }
  .faq .faq-a { color: var(--color-text-muted); font-size: var(--text-base); line-height: 1.7; padding: 0 4px 22px; margin: 0; }

  /* ---- Clube: fechamento ---- */
  .clube-cta { background: var(--color-bg); text-align: center; }
  .clube-cta .container-aptk { display: flex; flex-direction: column; align-items: center; }
  .clube-cta .sun { width: 120px; color: var(--color-primary); margin-bottom: 20px; }
  .clube-cta h2 { font-family: var(--font-display); font-size: clamp(2rem, 5vw, 3rem); color: var(--color-text); margin: 0 0 14px; }
  .clube-cta p { color: var(--color-text-muted); font-size: var(--text-lg); margin: 0 0 30px; max-width: 520px; }
  .clube-cta .cta-row { display: flex; gap: 14px; flex-wrap: wrap; justify-content: center; }

  /* ---- Clube: responsivo ---- */
  @media (max-width: 980px) {
    .clube-hero .container-aptk { grid-template-columns: 1fr; gap: 36px; }
    .clube-media { max-width: 440px; }
    .how-grid { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 520px) {
    .clube-media { max-width: none; }
    .how-grid { grid-template-columns: 1fr; }
    .clube-copy .btn-aptk, .clube-cta .cta-row .btn-aptk { width: 100%; }
  }
</style>
@endpush

@section('content')

  {{-- HERO --}}
  <section class="clube-hero">
    <div class="container-aptk">
      <div class="clube-copy">
        <p class="script-line">Clube APTK</p>
        <h1>Receba a APTK em casa, todo mês.</h1>
        <p>Drinks prontos e bases autorais, escolhidos pela casa e entregues na sua porta. Cinco planos, do primeiro gole ao colecionador de lotes.</p>
        <a href="#planos" class="btn-aptk">Ver os planos</a>
      </div>
      <div class="clube-media">
        <img src="{{ asset('img/aptk/clube-hero.jpg') }}" alt="Convidada brindando com um drink da APTK">
        <span class="photo-tag">Experiência APTK</span>
      </div>
    </div>
  </section>

  {{-- COMO FUNCIONA --}}
  <section class="section how">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Como funciona</span>
        <h2 class="section-title">Simples como um bom drink</h2>
      </div>
      <div class="how-grid">
        <div class="how-step">
          <span class="how-num">01</span>
          <h3>Escolha o plano</h3>
          <p>Do Descoberta ao Small Batch, tem um ritmo pra cada sede.</p>
        </div>
        <div class="how-step">
          <span class="how-num">02</span>
          <h3>Receba todo mês</h3>
          <p>A curadoria chega na sua porta, sem você sair do sofá.</p>
        </div>
        <div class="how-step">
          <span class="how-num">03</span>
          <h3>Beba com calma</h3>
          <p>Drinks prontos e bases autorais, no padrão do balcão.</p>
        </div>
        <div class="how-step">
          <span class="how-num">04</span>
          <h3>Mude quando quiser</h3>
          <p>Pause, troque de plano ou saia — sem burocracia.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- PLANOS --}}
  <section class="section" id="planos">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Assinaturas</span>
        <h2 class="section-title">Escolha o seu ritmo</h2>
        <p>Cinco planos pensados para diferentes momentos — e bolsos.</p>
      </div>

      <div class="plans-grid">
        @foreach ($plans as $plan)
          <div class="cl-plan {{ $plan->featured ? 'is-featured' : '' }}">
            @if ($plan->featured)
              <span class="pl-badge">Mais assinado</span>
            @endif
            <p class="pl-name">{{ $plan->name }}</p>
            <p class="pl-kicker">{{ $plan->kicker }}</p>
            <p class="pl-price">{{ $plan->priceDisplay() }}@if ($plan->isSelfServe())<small>{{ $plan->intervalLabel() }}</small>@endif</p>
            <p class="pl-cycle">{{ $plan->isSelfServe() ? 'cobrança mensal' : 'faturamento PJ' }}</p>
            <ul>
              @foreach ($plan->perks ?? [] as $perk)
                <li>{{ $perk }}</li>
              @endforeach
            </ul>
            @if ($plan->isSelfServe())
              <a href="{{ route('subscription.checkout', $plan->slug) }}" class="btn-aptk {{ $plan->featured ? '' : 'btn-aptk--outline' }} btn-aptk--block">Assinar</a>
            @else
              <a href="mailto:rafael@aptkspirits.com?subject={{ rawurlencode('Clube APTK — '.$plan->name) }}" class="btn-aptk btn-aptk--outline btn-aptk--block">Falar com o time</a>
            @endif
          </div>
        @endforeach
      </div>

      <p class="plans-note">Todos os planos são sem fidelidade — pause, troque ou cancele quando quiser.</p>
    </div>
  </section>

  {{-- FAQ --}}
  <section class="section" style="padding-top:0;">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Dúvidas</span>
        <h2 class="section-title">Perguntas frequentes</h2>
      </div>
      <div class="faq">
        <details open>
          <summary>Como funciona a cobrança?</summary>
          <p class="faq-a">É uma assinatura mensal. Você escolhe o plano e a gente combina a melhor forma de pagamento e a recorrência — sempre com aviso antes de cada renovação.</p>
        </details>
        <details>
          <summary>Posso pular um mês?</summary>
          <p class="faq-a">Pode. É só avisar a gente para pausar a assinatura e retomar quando quiser, sem perder o seu histórico.</p>
        </details>
        <details>
          <summary>Consigo trocar de plano?</summary>
          <p class="faq-a">A qualquer momento. Fale com a gente e ajustamos o seu plano — para cima, para baixo ou mudando de linha.</p>
        </details>
        <details>
          <summary>Como é a entrega?</summary>
          <p class="faq-a">Enviamos para todo o Brasil, com embalagem segura. O frete é grátis a partir do plano Clássicos.</p>
        </details>
      </div>
    </div>
  </section>

  {{-- FECHAMENTO --}}
  <section class="section section--dark clube-cta">
    <div class="container-aptk">
      <x-brand.sunburst class="sun" />
      <h2>Pronto para começar?</h2>
      <p>Escolha um plano e comece a receber a APTK em casa. Qualquer dúvida, a gente responde rápido.</p>
      <div class="cta-row">
        <a href="mailto:rafael@aptkspirits.com?subject=Clube%20APTK%20%E2%80%94%20Quero%20assinar" class="btn-aptk">Quero assinar</a>
        <a href="{{ route('catalog') }}" class="btn-aptk btn-aptk--outline">Ver o catálogo</a>
      </div>
    </div>
  </section>

@endsection
