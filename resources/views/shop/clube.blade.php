{{-- CLUBE APTK (leva 05) — mecânica oficial (doc "CLUBE APTK"): 4 níveis no
     banco (ClubePlanos2026Seeder). Desde a leva 14 a página não lista os
     planos: o CTA leva ao formulário de interesse (alimenta o CRM de leads);
     Collector e Connoisseur só por URL direta (/clube/assinar/{plan}).
     O bloco do Le Cercle aparece enquanto o plano estiver ativo. --}}
@extends('layouts.public')

@section('title', 'Clube APTK — Um círculo privado · APTK Spirits')
@section('meta_description', 'Clube APTK: mais do que benefícios, um universo para pertencer — coquetelaria, cultura, gastronomia e boas histórias.')

@push('styles')
<style>
  /* ---- Clube: hero com foto (leva 10) ---- */
  .clb-hero { position: relative; overflow: hidden; border-bottom: 1px solid var(--color-border); min-height: min(78vh, 720px); display: flex; align-items: center; background: var(--color-ink); }
  .clb-hero-img { position: absolute; top: 0; right: 0; width: 62%; height: 100%; object-fit: cover; object-position: center 4%; }
  .clb-hero-shade { position: absolute; inset: 0; background: linear-gradient(90deg, var(--color-ink) 0%, var(--color-ink) 38%, color-mix(in srgb, var(--color-ink) 55%, transparent) 62%, color-mix(in srgb, var(--color-ink) 10%, transparent) 100%); }
  .clb-hero .container-aptk { position: relative; z-index: 1; padding-block: clamp(56px, 8vw, 96px); }
  .clb-hero-copy { max-width: 620px; }
  .clb-hero .eyebrow { margin-bottom: 18px; }
  .clb-hero h1 { font-size: clamp(2.4rem, 5.4vw, 4rem); color: var(--color-cream); margin: 0 0 22px; }
  .clb-hero p.lead { font-size: var(--text-lg); color: color-mix(in srgb, var(--color-cream) 80%, transparent); line-height: 1.75; margin: 0 0 14px; max-width: 560px; }
  .clb-hero .btn-aptk { margin-top: 16px; }

  /* Como funciona (01/02/03) */
  .clb-base { border-bottom: 1px solid var(--color-border); background: var(--color-bg-card); }
  .clb-base .container-aptk { padding-block: clamp(40px, 6vw, 64px); }

  /* Le Cercle — bloco aspiracional */
  .cercle { border-block: 1px solid var(--color-primary-muted); background: color-mix(in srgb, var(--color-primary) 4%, transparent); }
  .cercle .container-aptk { max-width: 760px; text-align: center; padding-block: clamp(48px, 7vw, 72px); display: flex; flex-direction: column; align-items: center; }
  .cercle .c-kicker { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.2em; text-transform: uppercase; color: var(--color-primary); margin-bottom: 14px; }
  .cercle h2 { font-family: var(--font-display); font-size: clamp(1.8rem, 4.5vw, 2.6rem); margin: 0 0 16px; }
  .cercle blockquote { font-family: var(--font-editorial, var(--font-display)); font-style: italic; font-size: var(--text-lg); color: var(--color-text); line-height: 1.7; margin: 0 0 18px; max-width: 560px; }
  .cercle p { color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.75; margin: 0; max-width: 560px; }

  /* Faça parte (leva 14): foto ao lado do formulário de interesse */
  .clb-form-band .container-aptk { padding-block: clamp(48px, 7vw, 72px); display: grid; grid-template-columns: 1fr 1fr; gap: clamp(28px, 5vw, 64px); align-items: center; max-width: 1080px; }
  .clb-form-img { display: block; width: 100%; aspect-ratio: 4 / 5; object-fit: cover; object-position: 50% 60%; border-radius: var(--radius-lg); background: var(--color-ink); }
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

  @media (max-width: 760px) {
    .clb-form-band .container-aptk { grid-template-columns: 1fr; }
    .clb-form-img { aspect-ratio: 4 / 3; }
    .clb-hero { align-items: flex-end; }
    .clb-hero-img { width: 100%; }
    .clb-hero-shade { background: linear-gradient(0deg, var(--color-ink) 0%, color-mix(in srgb, var(--color-ink) 82%, transparent) 50%, color-mix(in srgb, var(--color-ink) 30%, transparent) 100%); }
  }
</style>
@endpush

@section('content')

  {{-- HERO (leva 10, briefing p.23–24) --}}
  <section class="clb-hero">
    <img class="clb-hero-img" src="{{ asset('img/aptk/clube-hero-2.jpg') }}" alt="Dois amigos brindando com drinks da APTK">
    <div class="clb-hero-shade"></div>
    <div class="container-aptk">
      <div class="clb-hero-copy">
        <span class="eyebrow">Clube APTK</span>
        <h1 class="title-editorial">Mais do que benefícios. Um universo para pertencer.</h1>
        <p class="lead">Um espaço para quem compartilha nossa paixão pela coquetelaria, cultura, gastronomia e boas histórias.</p>
        <p class="lead">No Clube APTK, cada escolha aproxima você de novas descobertas, benefícios exclusivos e oportunidades de viver o universo da marca de uma forma única.</p>
        <a href="#clube-form" class="btn-aptk">Faça parte do clube <span class="btn-arrow">→</span></a>
      </div>
    </div>
  </section>

  {{-- COMO FUNCIONA (briefing p.24). Pontos AINDA NÃO EXISTEM no sistema:
       onde o briefing promete pontos/10%, texto "em breve" sem número até a
       gestão decidir (leva 10). --}}
  <section class="clb-base">
    <div class="container-aptk">
      <ol class="num-steps">
        <li>
          <span class="ns-num">01</span>
          <h3>Faça parte</h3>
          <p>Entre para o Clube APTK e, em breve, comece a acumular pontos desde a sua primeira compra.</p>
        </li>
        <li>
          <span class="ns-num">02</span>
          <h3>Brinde às vantagens</h3>
          <p>Em breve: acumule pontos nas suas compras e troque por produtos e experiências da casa.</p>
        </li>
        <li>
          <span class="ns-num">03</span>
          <h3>Descubra mais</h3>
          <p>Tenha acesso a conteúdos especiais, receitas, novidades e inspirações do universo da coquetelaria, diretamente no seu e-mail.</p>
        </li>
      </ol>
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

  {{-- FAÇA PARTE: captação de interesse (alimenta o CRM). Âncora do CTA do hero. --}}
  <section class="clb-form-band" id="clube-form">
    <div class="container-aptk">
      <img class="clb-form-img" src="{{ asset('img/aptk/clube-faca-parte.jpg') }}" alt="Rótulo do Manhattan Aged da APTK" loading="lazy">
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
