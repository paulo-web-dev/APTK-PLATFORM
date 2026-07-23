{{-- CLUBE APTK (leva 05) — lançamento da mecânica oficial (doc "CLUBE APTK"):
     4 níveis (Explorer grátis · Collector · Connoisseur · Le Cercle por
     convite), vindos do banco (ClubePlanos2026Seeder). O formulário de
     novidades permanece no fim (segue alimentando o CRM de leads). --}}
@extends('layouts.public')

@section('title', 'Clube APTK — Um círculo privado · APTK Spirits')
@section('meta_description', 'O Clube APTK: um círculo privado para pessoas que compartilham do mesmo repertório — hospitalidade, cultura, gastronomia e coquetelaria. Conheça os níveis Explorer, Collector, Connoisseur e Le Cercle.')

@push('styles')
<style>
  /* ---- Clube (dark) ---- */
  .clb-hero { position: relative; overflow: hidden; border-bottom: 1px solid var(--color-border); }
  .clb-hero::before { content: ""; position: absolute; top: -22%; left: 50%; transform: translateX(-50%); width: 640px; height: 640px; background: radial-gradient(circle, var(--gold-faint) 0%, transparent 62%); pointer-events: none; }
  .clb-hero .container-aptk { position: relative; z-index: 1; text-align: center; max-width: 820px; padding-block: clamp(56px, 8vw, 88px); display: flex; flex-direction: column; align-items: center; }
  .clb-hero .script-line { font-family: var(--font-script); font-size: clamp(1.7rem, 4vw, 2.5rem); color: var(--color-primary); margin-bottom: 8px; }
  .clb-hero h1 { font-size: clamp(2rem, 5vw, 3.1rem); letter-spacing: -0.01em; margin: 0 0 18px; max-width: 22ch; }
  .clb-hero p.lead { font-size: var(--text-lg); color: var(--color-text-muted); line-height: 1.75; margin: 0; max-width: 640px; }

  /* Vantagens de quem entra (estrutura do clube) */
  .clb-base { border-bottom: 1px solid var(--color-border); background: var(--color-bg-card); }
  .clb-base .container-aptk { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; padding-block: clamp(36px, 5vw, 56px); }
  .clb-b-card { text-align: center; padding: 10px 18px; }
  .clb-b-card .bi { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.16em; text-transform: uppercase; color: var(--color-primary); display: block; margin-bottom: 10px; }
  .clb-b-card p { color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.7; margin: 0; }

  /* Grade de planos */
  .plans-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; align-items: stretch; }
  .plan-card { position: relative; display: flex; flex-direction: column; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 30px 26px; transition: border-color .25s ease, transform .25s ease; }
  .plan-card:hover { border-color: var(--color-primary-muted); transform: translateY(-3px); }
  .plan-card.is-featured { border-color: var(--color-primary); }
  .plan-card .p-flag { position: absolute; top: -11px; left: 50%; transform: translateX(-50%); background: var(--color-primary); color: var(--color-on-primary, #020203); font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.14em; text-transform: uppercase; border-radius: 100px; padding: 5px 14px; white-space: nowrap; }
  .plan-card .p-kicker { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.12em; text-transform: uppercase; color: var(--color-primary); }
  .plan-card h3 { font-family: var(--font-display); font-size: var(--text-2xl); margin: 10px 0 4px; }
  .plan-card .p-price { font-family: var(--font-mono); color: var(--color-text); font-size: var(--text-lg); margin-bottom: 18px; }
  .plan-card ul { list-style: none; margin: 0 0 24px; padding: 0; display: flex; flex-direction: column; gap: 10px; }
  .plan-card ul li { position: relative; padding-left: 22px; color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.55; }
  .plan-card ul li::before { content: "—"; position: absolute; left: 0; color: var(--color-primary); }
  .plan-card .p-cta { margin-top: auto; }

  /* Le Cercle — bloco aspiracional */
  .cercle { border-block: 1px solid var(--color-primary-muted); background: color-mix(in srgb, var(--color-primary) 4%, transparent); }
  .cercle .container-aptk { max-width: 760px; text-align: center; padding-block: clamp(48px, 7vw, 72px); display: flex; flex-direction: column; align-items: center; }
  .cercle .c-kicker { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.2em; text-transform: uppercase; color: var(--color-primary); margin-bottom: 14px; }
  .cercle h2 { font-family: var(--font-display); font-size: clamp(1.8rem, 4.5vw, 2.6rem); margin: 0 0 16px; }
  .cercle blockquote { font-family: var(--font-editorial, var(--font-display)); font-style: italic; font-size: var(--text-lg); color: var(--color-text); line-height: 1.7; margin: 0 0 18px; max-width: 560px; }
  .cercle p { color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.75; margin: 0; max-width: 560px; }

  /* Formulário de novidades (mantido) */
  .clb-form-band .container-aptk { max-width: 640px; padding-block: clamp(48px, 7vw, 72px); }
  .clb-form { background: var(--color-bg-elevated); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 34px; }
  .clb-form h2 { font-family: var(--font-display); font-size: var(--text-2xl); margin: 0 0 8px; text-align: center; }
  .clb-form > p { color: var(--color-text-muted); font-size: var(--text-sm); text-align: center; margin: 0 0 24px; }
  .clb-form label { display: block; font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-text-muted); margin-bottom: 8px; }
  .clb-form input { width: 100%; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text); padding: 12px 14px; font-family: var(--font-body); font-size: var(--text-sm); }
  .clb-form input:focus { outline: none; border-color: var(--color-primary); }
  .clb-form .f-row { margin-bottom: 18px; }
  .clb-form .f-err { font-size: var(--text-xs); color: var(--color-danger); margin-top: 6px; }
  .clb-ok { font-family: var(--font-mono); font-size: var(--text-sm); color: var(--color-success); border: 1px solid var(--color-border); border-left: 3px solid var(--color-success); border-radius: var(--radius-sm); padding: 14px 16px; margin-bottom: 22px; text-align: center; }
  .clb-privacy { font-size: var(--text-xs); color: var(--color-text-muted); text-align: center; margin-top: 14px; }

  @media (max-width: 980px) { .plans-grid { grid-template-columns: 1fr; } .clb-base .container-aptk { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')

  {{-- HERO — o conceito oficial --}}
  <section class="clb-hero">
    <div class="container-aptk">
      <p class="script-line">Clube APTK</p>
      <h1>Um círculo privado para quem compartilha do mesmo repertório</h1>
      <p class="lead">Hospitalidade, cultura, gastronomia e coquetelaria. O Clube APTK aproxima a casa de quem vive esse universo — com acesso, pontos e experiências que não chegam ao público.</p>
    </div>
  </section>

  {{-- ESTRUTURA — o que todo membro tem --}}
  <section class="clb-base">
    <div class="container-aptk">
      <div class="clb-b-card">
        <span class="bi">Boas-vindas</span>
        <p>Você já entra pontuando: ganhe pontos de boas-vindas ao se juntar ao clube.</p>
      </div>
      <div class="clb-b-card">
        <span class="bi">Pontos</span>
        <p>10% do valor das suas compras retorna em pontos, pra trocar por produtos da casa.</p>
      </div>
      <div class="clb-b-card">
        <span class="bi">Newsletter</span>
        <p>Dicas de drinks pelo Alê, direto no seu e-mail — receitas, técnica e repertório.</p>
      </div>
    </div>
  </section>

  {{-- PLANOS (Explorer · Collector · Connoisseur — Le Cercle tem bloco próprio) --}}
  <section class="section">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Os níveis</span>
        <h2 class="section-title">Do primeiro gole ao colecionador</h2>
      </div>
      <div class="plans-grid">
        @foreach ($plans->where('slug', '!=', 'le-cercle-aptk') as $plan)
          <div class="plan-card {{ $plan->featured ? 'is-featured' : '' }}">
            @if ($plan->featured)
              <span class="p-flag">Mais escolhido</span>
            @endif
            <span class="p-kicker">{{ $plan->kicker }}</span>
            <h3>{{ $plan->name }}</h3>
            <p class="p-price">{{ $plan->price_label }}</p>
            <ul>
              @foreach ($plan->perks ?? [] as $perk)
                <li>{{ $perk }}</li>
              @endforeach
            </ul>
            <div class="p-cta">
              @if ((float) $plan->price === 0.0)
                @auth
                  <span class="btn-aptk btn-aptk--outline btn-aptk--block" style="cursor:default;">Sua conta já é seu Explorer</span>
                @else
                  <a href="{{ route('register') }}" class="btn-aptk btn-aptk--block">Criar minha conta grátis</a>
                @endauth
              @else
                <a href="{{ route('subscription.checkout', $plan) }}" class="btn-aptk btn-aptk--block">Assinar {{ $plan->name }}</a>
              @endif
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- LE CERCLE — aspiracional, por convite --}}
  @php $cercle = $plans->firstWhere('slug', 'le-cercle-aptk'); @endphp
  @if ($cercle)
    <section class="cercle">
      <div class="container-aptk">
        <span class="c-kicker">Le Cercle APTK · somente por convite</span>
        <h2>Nem todos os produtos da APTK podem ser comprados.<br>Alguns precisam ser conquistados.</h2>
        <blockquote>Jantares privados, degustações na casa, acesso a protótipos, a chance de cocriar um líquido — e o seu nome gravado em uma edição anual.</blockquote>
        <p>O Le Cercle não está à venda. Os convites são raros, pessoais e chegam a quem já faz parte da história da casa.</p>
      </div>
    </section>
  @endif

  {{-- NOVIDADES (captação mantida — alimenta o CRM) --}}
  <section class="clb-form-band">
    <div class="container-aptk">
      <div class="clb-form">
        @if (session('clube_ok'))
          <div class="clb-ok">{{ session('clube_ok') }}</div>
        @endif

        <h2>Receba as novidades do Clube</h2>
        <p>Lançamentos, eventos e campanhas — em primeira mão, no seu e-mail.</p>

        <form method="POST" action="{{ route('clube.interesse') }}">
          @csrf
          <div class="f-row">
            <label for="clb-name">Seu nome</label>
            <input id="clb-name" type="text" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Nome completo">
            @error('name') <p class="f-err">{{ $message }}</p> @enderror
          </div>
          <div class="f-row">
            <label for="clb-email">E-mail</label>
            <input id="clb-email" type="email" name="email" value="{{ old('email') }}" required maxlength="255" placeholder="seu@email.com">
            @error('email') <p class="f-err">{{ $message }}</p> @enderror
          </div>
          <div class="f-row">
            <label for="clb-phone">WhatsApp (opcional)</label>
            <input id="clb-phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="20" placeholder="(11) 90000-0000">
            @error('phone') <p class="f-err">{{ $message }}</p> @enderror
          </div>
          <button type="submit" class="btn-aptk btn-aptk--block">Quero receber</button>
          <p class="clb-privacy">Usamos seu contato só pra falar do Clube APTK. Sem spam.</p>
        </form>
      </div>
    </div>
  </section>

@endsection
