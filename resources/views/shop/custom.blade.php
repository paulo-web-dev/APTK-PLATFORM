@extends('layouts.public')

@section('title', 'Custom — Rótulo personalizado · APTK Spirits')
@section('meta_description', 'Personalize o rótulo da sua garrafa APTK: escolha o coquetel, a fonte e a mensagem — ou traga a sua própria arte no Full Custom.')

@section('hide_feature_band', '1')

@push('styles')
<style>
  /* ---- Custom: hero + modelos de referência ---- */
  .cst-hero { position: relative; overflow: hidden; }
  .cst-hero::before { content: ""; position: absolute; top: -12%; right: -6%; width: 560px; height: 560px; background: radial-gradient(circle, var(--gold-faint) 0%, transparent 65%); pointer-events: none; }
  .cst-hero .container-aptk { position: relative; z-index: 1; padding-block: 64px 52px; display: grid; grid-template-columns: 1.1fr .9fr; gap: 56px; align-items: center; }
  .cst-hero .eyebrow { margin-bottom: 16px; }
  .cst-hero h1 { font-size: clamp(2.4rem, 6vw, 3.75rem); letter-spacing: -0.01em; margin: 0 0 20px; max-width: 16ch; }
  .cst-hero p.lead { font-size: var(--text-lg); color: var(--color-text-muted); max-width: 560px; margin: 0 0 30px; line-height: 1.7; }

  /* Modelos para referência — 3 custom simples + 1 full custom */
  .models-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
  .model-card { position: relative; background: #fff; border: 1px solid var(--color-border); border-radius: var(--radius-md); aspect-ratio: 1 / 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px 14px 34px; text-align: center; overflow: hidden; box-shadow: var(--shadow-card); }
  .model-card .m-brand { position: absolute; top: 12px; font-family: var(--font-body); font-weight: 600; font-size: 11px; letter-spacing: 0.14em; color: #111; display: inline-flex; align-items: center; gap: 5px; }
  .model-card .m-brand svg { width: 14px; height: 14px; }
  .model-card .m-text { color: #111; line-height: 1.15; }
  .model-card .m-font { position: absolute; bottom: 0; left: 0; right: 0; background: #111; color: var(--color-primary); font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.14em; text-transform: uppercase; padding: 6px 8px; }
  .model-card .m-kind { position: absolute; left: -28px; top: 50%; transform: rotate(-90deg) translateX(50%); transform-origin: center; font-family: var(--font-mono); font-size: 9px; letter-spacing: 0.16em; text-transform: uppercase; color: var(--color-text-muted); }
  /* Fontes de exemplo. PP Rader é a oficial (auto-hospedada);
     Amithen e Blackword AINDA NÃO estão no projeto — abaixo, fallbacks
     script aproximados. Trocar pelos @font-face quando os WOFF2 chegarem. */
  .m-text--rader { font-family: var(--font-display); font-weight: 700; font-size: clamp(1.3rem, 2.4vw, 1.8rem); }
  .m-text--amithen { font-family: var(--font-script), 'Segoe Script', cursive; font-size: clamp(1.2rem, 2.2vw, 1.6rem); }
  .m-text--blackword { font-family: var(--font-script), 'Brush Script MT', cursive; font-size: clamp(1.5rem, 2.8vw, 2rem); font-style: italic; }
  .model-card--full { background: var(--color-ink); border-color: var(--color-primary-muted); }
  .model-card--full .m-text { color: var(--color-primary); font-family: var(--font-display); font-weight: 700; font-size: clamp(1.1rem, 2vw, 1.5rem); letter-spacing: 0.04em; }
  .model-card--full .m-font { background: var(--color-primary); color: var(--color-text-inverse); }

  /* ---- Custom: como funciona (3 passos — leva 01 removeu "Defina o lote") ---- */
  .how-band { background: var(--color-bg-card); border-block: 1px solid var(--color-border); }
  .steps-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }
  .step-card { background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 28px 24px; }
  .step-card .s-num { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.16em; text-transform: uppercase; color: var(--color-primary); }
  .step-card h3 { font-family: var(--font-display); font-size: var(--text-xl); margin: 12px 0 8px; }
  .step-card p { color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.6; margin: 0; }

  /* ---- Custom Simples: formulário ---- */
  .cst-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 56px; align-items: start; }
  .cst-form { background: var(--color-bg-elevated); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 32px; }
  .cst-form .f-row { margin-bottom: 18px; }
  .cst-form label { display: block; font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-text-muted); margin-bottom: 8px; }
  .cst-form input, .cst-form select { width: 100%; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text); padding: 12px 14px; font-family: var(--font-body); font-size: var(--text-sm); }
  .cst-form input:focus, .cst-form select:focus { outline: none; border-color: var(--color-primary); }
  .cst-form .f-hint { font-size: var(--text-xs); color: var(--color-text-muted); margin-top: 6px; }
  .cst-form .f-err { font-size: var(--text-xs); color: var(--color-danger); margin-top: 6px; }
  .cst-ok { font-family: var(--font-mono); font-size: var(--text-sm); color: var(--color-success); border: 1px solid var(--color-border); border-left: 3px solid var(--color-success); border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 24px; }
  .cst-side h2 { font-family: var(--font-display); font-size: clamp(1.8rem, 4vw, 2.4rem); margin: 0 0 14px; }
  .cst-side p { color: var(--color-text-muted); line-height: 1.7; margin: 0 0 18px; max-width: 460px; }
  .cst-side ul { list-style: none; margin: 0 0 8px; padding: 0; display: flex; flex-direction: column; gap: 10px; }
  .cst-side li { color: var(--color-text-muted); font-size: var(--text-sm); padding-left: 22px; position: relative; }
  .cst-side li::before { content: ""; position: absolute; left: 0; top: 5px; width: 9px; height: 9px; border-radius: 50%; border: 2px solid var(--color-primary); }

  /* ---- Full Custom: bloco WhatsApp ---- */
  .full-band { background: var(--color-ink); border-block: 1px solid var(--color-border); text-align: center; }
  .full-band .container-aptk { max-width: 720px; display: flex; flex-direction: column; align-items: center; padding-block: clamp(48px, 7vw, 72px); }
  .full-band .eyebrow { margin-bottom: 16px; }
  .full-band h2 { font-family: var(--font-display); font-size: clamp(1.9rem, 5vw, 2.8rem); color: var(--color-cream); margin: 0 0 14px; }
  .full-band p { color: color-mix(in srgb, var(--color-cream) 78%, transparent); font-size: var(--text-lg); margin: 0 0 30px; max-width: 520px; }
  .btn-whats { display: inline-flex; align-items: center; gap: 10px; }
  .btn-whats svg { width: 20px; height: 20px; }
</style>
@endpush

@section('content')

  {{-- HERO + MODELOS PARA REFERÊNCIA --}}
  <section class="cst-hero">
    <div class="container-aptk">
      <div>
        <span class="eyebrow">Rótulo personalizado</span>
        <h1>Sua marca na nossa garrafa</h1>
        <p class="lead">Presentes corporativos, casamentos, aniversários ou a sua própria marca: personalizamos o rótulo e a curadoria do drink, do lote pequeno ao grande volume.</p>
        <a href="#pedido" class="btn-aptk">Pedir orçamento</a>
      </div>

      <div>
        <div class="models-grid" aria-label="Modelos para referência">
          <div class="model-card">
            <span class="m-kind">Custom simples</span>
            <span class="m-brand"><x-brand.symbol style="width:14px;" /> APTK</span>
            <span class="m-text m-text--rader">Bia &amp;<br>Eduardo</span>
            <span class="m-font">Font PP Rader</span>
          </div>
          <div class="model-card">
            <span class="m-kind">Custom simples</span>
            <span class="m-brand"><x-brand.symbol style="width:14px;" /> APTK</span>
            <span class="m-text m-text--amithen">para a melhor<br>mãe do mundo!</span>
            <span class="m-font">Font Amithen</span>
          </div>
          <div class="model-card">
            <span class="m-kind">Custom simples</span>
            <span class="m-brand"><x-brand.symbol style="width:14px;" /> APTK</span>
            <span class="m-text m-text--blackword">Feliz<br>50</span>
            <span class="m-font">Font Blackword</span>
          </div>
          <div class="model-card model-card--full">
            <span class="m-kind">Full custom</span>
            <span class="m-text">SUA<br>ARTE/<br>LOGO<br>AQUI</span>
            <a href="#full-custom" class="m-font" style="text-decoration:none; display:block;">Pedir orçamento</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- COMO FUNCIONA — 3 passos (leva 01: "Defina o lote" removido) --}}
  <section class="section how-band">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Como funciona</span>
        <h2 class="section-title">Do conceito à garrafa</h2>
      </div>
      <div class="steps-grid">
        <div class="step-card">
          <span class="s-num">01</span>
          <h3>Escolha o drink</h3>
          <p>Comece por um clássico ou um autoral da casa como base.</p>
        </div>
        <div class="step-card">
          <span class="s-num">02</span>
          <h3>Crie o rótulo</h3>
          <p>A gente desenha — ou aplica a sua arte — dentro da identidade APTK.</p>
        </div>
        <div class="step-card">
          <span class="s-num">03</span>
          <h3>Receba pronto</h3>
          <p>Engarrafado, rotulado e embalado para presentear ou vender.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- CUSTOM SIMPLES — formulário do pedido --}}
  <section class="section" id="pedido">
    <div class="container-aptk">
      <div class="cst-form-grid">
        <div class="cst-side">
          <span class="eyebrow">Custom Simples</span>
          <h2>Monte o seu rótulo em três escolhas</h2>
          <p>Escolha a fonte, o coquetel e escreva a mensagem que vai na garrafa. A APTK recebe o pedido e devolve com proposta e prazo.</p>
          <ul>
            <li>Fonte entre os três modelos de referência ao lado</li>
            <li>10 coquetéis da casa disponíveis para personalizar</li>
            <li>Mensagem de até 8 palavras impressa no rótulo</li>
          </ul>
        </div>

        <div class="cst-form">
          @if (session('custom_ok'))
            <div class="cst-ok">{{ session('custom_ok') }}</div>
          @endif

          <form method="POST" action="{{ route('custom.store') }}">
            @csrf
            <div class="f-row">
              <label for="cst-name">Seu nome</label>
              <input id="cst-name" type="text" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Nome completo">
              @error('name') <p class="f-err">{{ $message }}</p> @enderror
            </div>
            <div class="f-row">
              <label for="cst-email">E-mail</label>
              <input id="cst-email" type="email" name="email" value="{{ old('email') }}" required maxlength="255" placeholder="seu@email.com">
              @error('email') <p class="f-err">{{ $message }}</p> @enderror
            </div>
            <div class="f-row">
              <label for="cst-phone">Telefone / WhatsApp (opcional)</label>
              <input id="cst-phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="20" placeholder="(11) 90000-0000">
              @error('phone') <p class="f-err">{{ $message }}</p> @enderror
            </div>
            <div class="f-row">
              <label for="cst-fonte">Fonte</label>
              <select id="cst-fonte" name="fonte" required>
                <option value="" disabled {{ old('fonte') ? '' : 'selected' }}>Escolha a fonte do rótulo</option>
                @foreach ($fontes as $fonte)
                  <option value="{{ $fonte }}" {{ old('fonte') === $fonte ? 'selected' : '' }}>{{ $fonte }}</option>
                @endforeach
              </select>
              @error('fonte') <p class="f-err">{{ $message }}</p> @enderror
            </div>
            <div class="f-row">
              <label for="cst-coquetel">Coquetel</label>
              <select id="cst-coquetel" name="coquetel" required>
                <option value="" disabled {{ old('coquetel') ? '' : 'selected' }}>Escolha o coquetel</option>
                @foreach ($coqueteis as $coquetel)
                  <option value="{{ $coquetel }}" {{ old('coquetel') === $coquetel ? 'selected' : '' }}>{{ $coquetel }}</option>
                @endforeach
              </select>
              @error('coquetel') <p class="f-err">{{ $message }}</p> @enderror
            </div>
            <div class="f-row">
              <label for="cst-mensagem">Mensagem da personalização</label>
              <input id="cst-mensagem" type="text" name="mensagem" value="{{ old('mensagem') }}" required maxlength="120" placeholder="Ex.: Bia & Eduardo — 20 de setembro">
              <p class="f-hint">Até 8 palavras — é o texto que vai impresso no rótulo.</p>
              @error('mensagem') <p class="f-err">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-aptk btn-aptk--block">Comprar</button>
            <p class="f-hint" style="text-align:center; margin-top:10px;">A APTK recebe a sua customização e retorna com proposta e prazo.</p>
          </form>
        </div>
      </div>
    </div>
  </section>

  {{-- FULL CUSTOM — WhatsApp da equipe de criação --}}
  <section class="section full-band on-dark" id="full-custom">
    <div class="container-aptk">
      <x-brand.sunburst style="width:120px; color:var(--color-primary); margin-bottom:20px;" />
      <span class="eyebrow">Full Custom</span>
      <h2>Tem uma arte ou logo próprio?</h2>
      <p>Entre em contato com a nossa equipe de criação via WhatsApp — a gente desenvolve o rótulo completo com a sua identidade.</p>
      <a href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Olá! Quero um orçamento de Full Custom (rótulo com arte própria) da APTK.') }}"
         target="_blank" rel="noopener noreferrer" class="btn-aptk btn-whats">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 12a9 9 0 0 1-13.5 7.8L3 21l1.2-4.5A9 9 0 1 1 21 12Z"/><path d="M8.5 9.5c0 3 2 5 5 5"/></svg>
        Falar com a equipe de criação
      </a>
    </div>
  </section>

@endsection
