@extends('layouts.public')

@section('title', 'Custom — Rótulo personalizado · APTK Spirits')
@section('meta_description', 'APTK Custom: escolha o coquetel, o estilo do rótulo e a mensagem — ou traga a identidade da sua marca no Full Custom.')

@section('hide_feature_band', '1')

@push('styles')
<style>
  /* Rótulo de exemplo/prévia: papel Espumante, tinta Cuba Libre (tokens). */

  /* ---- Custom: hero (leva 10) — texto + duas fotos ---- */
  .cst-hero { position: relative; overflow: hidden; }
  .cst-hero::before { content: ""; position: absolute; top: -12%; right: -6%; width: 560px; height: 560px; background: radial-gradient(circle, var(--gold-faint) 0%, transparent 65%); pointer-events: none; }
  .cst-hero .container-aptk { position: relative; z-index: 1; padding-block: 64px 52px; display: grid; grid-template-columns: 1fr 1fr; gap: 56px; align-items: center; }
  .cst-hero .eyebrow { margin-bottom: 16px; }
  .cst-hero h1 { font-size: clamp(2.6rem, 6vw, 4.2rem); margin: 0 0 20px; max-width: 14ch; }
  .cst-hero p.lead { font-size: var(--text-lg); color: var(--color-text-muted); max-width: 540px; margin: 0 0 30px; line-height: 1.7; }
  .cst-hero-media { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  .cst-hero-fig { margin: 0; }
  .cst-hero-fig img { display: block; width: 100%; aspect-ratio: 3 / 4; object-fit: cover; border-radius: var(--radius-md); border: 1px solid var(--color-border); background: var(--color-ink); }
  .cst-hero-fig figcaption { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.12em; text-transform: uppercase; color: var(--color-text-muted); margin-top: 10px; }
  .cst-hero-fig:nth-child(2) { margin-top: 48px; }

  /* Passos compactos no hero */
  .steps-inline { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin: 26px 0 0; padding-top: 22px; border-top: 1px solid var(--color-border); }
  .steps-inline .si-txt { font-size: var(--text-sm); color: var(--color-text-muted); }
  .steps-inline .si-sep { color: var(--color-primary); }

  /* ---- Custom: a jornada em três momentos ---- */
  .cst-journey { border-block: 1px solid var(--color-border); background: var(--color-bg-card); padding-block: clamp(40px, 6vw, 64px); }

  /* ---- Crie seu APTK: formulário em 3 blocos ---- */
  .cst-form-grid { display: grid; grid-template-columns: .9fr 1.1fr; gap: 56px; align-items: start; }
  .cst-side h2 { font-size: clamp(2rem, 4.2vw, 2.8rem); margin: 14px 0 16px; }
  .cst-side > p { color: var(--color-text-muted); line-height: 1.75; margin: 0 0 32px; max-width: 460px; }
  .cst-form { background: var(--color-bg-elevated); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 32px; }
  .cst-step { border: 0; margin: 0 0 28px; padding: 0 0 26px; border-bottom: 1px solid var(--color-border); min-width: 0; }
  .cst-step legend { float: none; width: auto; font-family: var(--font-display); font-weight: 700; font-size: var(--text-lg); color: var(--color-text); margin-bottom: 16px; padding: 0; }
  .cst-step .cs-num { font-family: var(--font-editorial); font-weight: 400; color: var(--color-primary); margin-right: 6px; }
  .cst-form .f-row { margin-top: 16px; }
  .cst-form label, .cst-form .f-label { display: block; font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-text-muted); margin: 0 0 8px; }
  .cst-form input[type=text], .cst-form input[type=email], .cst-form input[type=tel] { width: 100%; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text); padding: 12px 14px; font-family: var(--font-body); font-size: var(--text-sm); }
  .cst-form input:focus { outline: none; border-color: var(--color-primary); }
  .cst-form .f-hint { font-size: var(--text-xs); color: var(--color-text-muted); margin-top: 6px; }
  .cst-form .f-err { font-size: var(--text-xs); color: var(--color-danger); margin-top: 8px; }

  /* Coquetel: chips de rádio */
  .chip-group { display: flex; flex-wrap: wrap; gap: 8px; }
  .chip-opt { margin: 0 !important; text-transform: none !important; letter-spacing: 0 !important; }
  .chip-opt input, .style-opt input { position: absolute; opacity: 0; width: 1px; height: 1px; pointer-events: none; }
  .chip-opt span { display: inline-block; font-family: var(--font-body); font-size: var(--text-sm); color: var(--color-text-muted); border: 1px solid var(--color-border); border-radius: 100px; padding: 8px 14px; cursor: pointer; transition: border-color .2s ease, color .2s ease, background-color .2s ease; }
  .chip-opt:hover span { border-color: var(--color-primary-muted); color: var(--color-text); }
  .chip-opt input:checked + span { background: var(--color-primary); border-color: var(--color-primary); color: var(--color-text-inverse); }
  .chip-opt input:focus-visible + span, .style-opt input:focus-visible + .so-tile { outline: 2px solid var(--color-primary); outline-offset: 2px; }

  /* Estilo do rótulo: miniaturas */
  .style-group { display: flex; flex-wrap: wrap; gap: 12px; }
  .style-group .style-opt { flex: 1 1 110px; max-width: 170px; }
  .style-opt { margin: 0 !important; cursor: pointer; text-transform: none !important; letter-spacing: 0 !important; }
  .so-tile { display: grid; place-items: center; aspect-ratio: 1 / 1; background: var(--color-cream); border: 2px solid transparent; border-radius: var(--radius-md); padding: 10px; text-align: center; transition: border-color .2s ease; }
  .style-opt:hover .so-tile { border-color: var(--color-primary-muted); }
  .style-opt input:checked + .so-tile { border-color: var(--color-primary); box-shadow: var(--shadow-glow); }
  .so-ex { color: var(--color-ink); line-height: 1.15; }
  .so-name { display: block; text-align: center; font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.12em; text-transform: uppercase; color: var(--color-text); margin-top: 8px; }

  /* Fontes de exemplo. PP Rader e Amithen são reais (auto-hospedadas);
     Blackword AINDA NÃO chegou: o estilo "Clássico" fica fora do seletor
     (CustomController::FONTES). A classe abaixo fica pronta pra quando o
     @font-face dela entrar em aptk-tokens.css. */
  .f-pprader { font-family: var(--font-display); font-weight: 700; }
  .f-amithen { font-family: var(--font-amithen); }
  .f-blackword { font-family: var(--font-script), serif; font-style: italic; }
  .so-ex.f-pprader { font-size: clamp(.95rem, 1.6vw, 1.2rem); }
  .so-ex.f-amithen { font-size: clamp(1rem, 1.7vw, 1.3rem); }
  .so-ex.f-blackword { font-size: clamp(1.2rem, 2vw, 1.5rem); }

  /* Prévia do rótulo */
  .label-preview { position: relative; background: var(--color-cream); border-radius: var(--radius-md); min-height: 170px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; text-align: center; padding: 22px 16px; margin-bottom: 18px; }
  .label-preview .lp-kicker { font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.14em; text-transform: uppercase; color: color-mix(in srgb, var(--color-ink) 55%, transparent); }
  .label-preview .lp-brand { display: inline-flex; align-items: center; gap: 6px; font-family: var(--font-body); font-weight: 600; font-size: 11px; letter-spacing: 0.14em; text-transform: uppercase; color: var(--color-ink); }
  .label-preview .lp-text { color: var(--color-ink); line-height: 1.2; word-break: break-word; font-size: clamp(1.3rem, 3vw, 1.8rem); }
  .label-preview .lp-drink { font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.14em; text-transform: uppercase; color: color-mix(in srgb, var(--color-ink) 60%, transparent); min-height: 1em; }

  .cst-ok { font-family: var(--font-mono); font-size: var(--text-sm); color: var(--color-success); border: 1px solid var(--color-border); border-left: 3px solid var(--color-success); border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 24px; }

  /* ---- Full Custom (leva 10): texto à esquerda, foto à direita ---- */
  .full-band { background: var(--color-ink); border-block: 1px solid var(--color-border); }
  .full-grid { display: grid; grid-template-columns: 1fr 1fr; gap: clamp(32px, 5vw, 64px); align-items: center; }
  .full-band .eyebrow { margin-bottom: 16px; }
  .full-band h2 { font-size: clamp(2rem, 4.6vw, 3rem); color: var(--color-cream); margin: 0 0 16px; }
  .full-band p { color: color-mix(in srgb, var(--color-cream) 78%, transparent); font-size: var(--text-lg); line-height: 1.7; margin: 0 0 30px; max-width: 500px; }
  .full-img { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: var(--radius-lg); border: 1px solid var(--color-border); }

  @media (max-width: 900px) {
    .cst-hero .container-aptk, .cst-form-grid, .full-grid { grid-template-columns: 1fr; gap: 36px; }
    .cst-hero .container-aptk { padding-block: 44px 40px; }
  }
  @media (max-width: 520px) {
    .cst-form { padding: 22px 18px; }
    .style-group { gap: 8px; }
    .cst-hero-fig:nth-child(2) { margin-top: 28px; }
  }
</style>
@endpush

@section('content')
  @php
    // Leva 10: estilos na tela; o lead continua gravando a fonte. Só aparece
    // estilo de fonte que está em CustomController::FONTES — Blackword
    // ("Clássico") fica fora até o arquivo da fonte chegar.
    $estilos = [
      'Blackword' => ['nome' => 'Clássico',  'cls' => 'f-blackword', 'ex' => "Feliz\n50"],
      'PP Rader'  => ['nome' => 'Editorial', 'cls' => 'f-pprader',   'ex' => "Bia &\nEduardo"],
      'Amithen'   => ['nome' => 'Autoral',   'cls' => 'f-amithen',   'ex' => "para a melhor\nmãe do mundo!"],
    ];
  @endphp

  {{-- HERO (briefing p.17–18) --}}
  <section class="cst-hero">
    <div class="container-aptk">
      <div>
        <span class="eyebrow">APTK Custom</span>
        <h1 class="title-editorial">Sua história. Nossa alquimia.</h1>
        <p class="lead">Transformamos coquetéis em presentes que carregam histórias. Para marcas, celebrações e momentos especiais, criamos rótulos personalizados que unem alta coquetelaria, criatividade e identidade.</p>
        <a href="#pedido" class="btn-aptk">Personalize seu APTK <span class="btn-arrow">→</span></a>
        <div class="steps-inline" aria-label="Como funciona">
          <span class="si-txt">Escolha seu coquetel</span>
          <span class="si-sep">·</span>
          <span class="si-txt">Personalize o rótulo</span>
          <span class="si-sep">·</span>
          <span class="si-txt">Surpreenda</span>
        </div>
      </div>
      <div class="cst-hero-media">
        {{-- FOTO: garrafa APTK com rótulo personalizado para presente/celebração (provisória: pessoas brindando; a do Negroni tinha o rótulo antigo). --}}
        <figure class="cst-hero-fig">
          <img src="{{ asset('img/aptk/cat-pessoas.jpg') }}" alt="Pessoas brindando com drinks da APTK">
          <figcaption>Presentes &amp; Celebrações</figcaption>
        </figure>
        {{-- FOTO: kit com rótulo de marca parceira e embalagem premium (provisória: loja APTK). --}}
        <figure class="cst-hero-fig">
          <img src="{{ asset('img/aptk/about-loja-3.jpg') }}" alt="Interior da loja APTK Spirits">
          <figcaption>Marcas &amp; Projetos Especiais</figcaption>
        </figure>
      </div>
    </div>
  </section>

  {{-- A JORNADA EM TRÊS MOMENTOS (briefing p.18) --}}
  <section class="cst-journey">
    <div class="container-aptk">
      <ol class="num-steps">
        <li>
          <span class="ns-num">01</span>
          <h3>Escolha seu coquetel</h3>
          <p>Selecione entre os clássicos e as criações autorais da APTK.</p>
        </li>
        <li>
          <span class="ns-num">02</span>
          <h3>Crie sua identidade</h3>
          <p>Personalize o rótulo com nomes, mensagens, artes ou a identidade da sua marca.</p>
        </li>
        <li>
          <span class="ns-num">03</span>
          <h3>Transforme em presente</h3>
          <p>Nossa equipe orienta a produção para que cada detalhe traduza a sua intenção.</p>
        </li>
      </ol>
    </div>
  </section>

  {{-- CRIE SEU APTK — formulário em 3 blocos (briefing p.19–21): coquetel →
       rótulo → dados → prévia e envio. Mesmos campos e validação de antes. --}}
  <section class="section" id="pedido">
    <div class="container-aptk">
      <div class="cst-form-grid">
        <div class="cst-side">
          <span class="eyebrow">Crie seu APTK</span>
          <h2 class="title-editorial">Um coquetel. A sua assinatura.</h2>
          <p>Personalizar é transformar um presente em algo único. Escolha seu coquetel favorito, defina o estilo do rótulo e adicione uma mensagem especial. Nós cuidamos dos detalhes para que sua história ganhe forma.</p>
          <ol class="num-steps num-steps--stack">
            <li>
              <span class="ns-num">01</span>
              <h3>Escolha seu coquetel</h3>
              <p>Explore os clássicos e as criações autorais disponíveis para personalização.</p>
            </li>
            <li>
              <span class="ns-num">02</span>
              <h3>Dê personalidade ao rótulo</h3>
              <p>Escolha entre nossos estilos de tipografia e crie sua mensagem personalizada.</p>
            </li>
            <li>
              <span class="ns-num">03</span>
              <h3>Conte com a APTK</h3>
              <p>Envie sua criação e nossa equipe entrará em contato com valores, prazos e detalhes do pedido.</p>
            </li>
          </ol>
        </div>

        <div class="cst-form">
          @if (session('custom_ok'))
            <div class="cst-ok">{{ session('custom_ok') }}</div>
          @endif
          <form method="POST" action="{{ route('custom.store') }}">
            @csrf

            <fieldset class="cst-step">
              <legend><span class="cs-num">01</span> Seu coquetel</legend>
              <div class="chip-group" role="radiogroup" aria-label="Escolha do coquetel">
                @foreach ($coqueteis as $coquetel)
                  <label class="chip-opt">
                    <input type="radio" name="coquetel" value="{{ $coquetel }}" required {{ old('coquetel') === $coquetel ? 'checked' : '' }}>
                    <span>{{ $coquetel }}</span>
                  </label>
                @endforeach
              </div>
              @error('coquetel') <p class="f-err">{{ $message }}</p> @enderror
            </fieldset>

            <fieldset class="cst-step">
              <legend><span class="cs-num">02</span> Seu rótulo</legend>
              <p class="f-label">Estilo do rótulo</p>
              <div class="style-group" role="radiogroup" aria-label="Estilo do rótulo">
                {{-- Ordem do briefing (Clássico, Editorial, Autoral); fonte sem estilo mapeado vai no fim com o próprio nome. --}}
                @foreach (array_unique(array_merge(array_intersect(array_keys($estilos), $fontes), $fontes)) as $fonte)
                  @php $e = $estilos[$fonte] ?? ['nome' => $fonte, 'cls' => 'f-pprader', 'ex' => $fonte]; @endphp
                  <label class="style-opt">
                    <input type="radio" name="fonte" value="{{ $fonte }}" data-cls="{{ $e['cls'] }}" required {{ old('fonte') === $fonte ? 'checked' : '' }}>
                    <span class="so-tile"><span class="so-ex {{ $e['cls'] }}">{!! nl2br(e($e['ex'])) !!}</span></span>
                    <span class="so-name">{{ $e['nome'] }}</span>
                  </label>
                @endforeach
              </div>
              @error('fonte') <p class="f-err">{{ $message }}</p> @enderror

              <div class="f-row">
                <label for="cst-mensagem">Mensagem personalizada (até 8 palavras)</label>
                <input id="cst-mensagem" type="text" name="mensagem" value="{{ old('mensagem') }}" required maxlength="120" placeholder="Ex.: Bia & Eduardo — 20 de setembro">
                @error('mensagem') <p class="f-err">{{ $message }}</p> @enderror
              </div>
            </fieldset>

            <fieldset class="cst-step">
              <legend><span class="cs-num">03</span> Seus dados</legend>
              <div class="f-row">
                <label for="cst-name">Nome</label>
                <input id="cst-name" type="text" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Nome completo">
                @error('name') <p class="f-err">{{ $message }}</p> @enderror
              </div>
              <div class="f-row">
                <label for="cst-email">E-mail</label>
                <input id="cst-email" type="email" name="email" value="{{ old('email') }}" required maxlength="255" placeholder="seu@email.com">
                @error('email') <p class="f-err">{{ $message }}</p> @enderror
              </div>
              <div class="f-row">
                <label for="cst-phone">WhatsApp (opcional)</label>
                <input id="cst-phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="20" placeholder="(11) 90000-0000">
                @error('phone') <p class="f-err">{{ $message }}</p> @enderror
              </div>
            </fieldset>

            {{-- Finalização: prévia do rótulo + envio --}}
            <div class="label-preview" id="labelPreview" aria-live="polite">
              <span class="lp-kicker">Prévia do seu rótulo</span>
              <span class="lp-brand"><x-brand.symbol style="width:14px;" /> APTK Spirits</span>
              <span class="lp-text f-pprader" id="lpText">Sua mensagem aqui</span>
              <span class="lp-drink" id="lpDrink"></span>
            </div>
            <button type="submit" class="btn-aptk btn-aptk--block">Solicitar minha personalização</button>
            <p class="f-hint" style="text-align:center; margin-top:10px;">A APTK recebe a sua customização e retorna com proposta e prazo.</p>
          </form>
        </div>
      </div>
    </div>
  </section>

  {{-- FULL CUSTOM (briefing p.22): texto à esquerda, foto à direita, CTA em
       contorno com seta. Mesmo WhatsApp da equipe de criação. --}}
  <section class="section full-band" id="full-custom">
    <div class="container-aptk full-grid">
      <div>
        <span class="eyebrow">Full Custom</span>
        <h2 class="title-editorial">Sua identidade. Nossa criação.</h2>
        <p>Muito além de um rótulo personalizado. Desenvolvemos projetos exclusivos que conectam sua marca ao universo da alta coquetelaria, com criatividade, sofisticação e atenção a cada detalhe.</p>
        <a href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Olá! Quero um orçamento de Full Custom (rótulo com arte própria) da APTK.') }}"
           target="_blank" rel="noopener noreferrer" class="btn-aptk btn-aptk--outline">
          Fale com nossa equipe <span class="btn-arrow">→</span>
        </a>
      </div>
      {{-- FOTO: garrafa APTK com rótulo personalizado para uma marca parceira + embalagem premium (provisória: Dry Martini da casa). --}}
      <img class="full-img" src="{{ asset('img/aptk/cat-dry-martini.jpg') }}" alt="Garrafa de Dry Martini da APTK com taça" loading="lazy">
    </div>
  </section>
@endsection

@push('scripts')
<script>
  // Prévia do rótulo (leva 10): mensagem + estilo + coquetel escolhidos.
  (function () {
    var msg = document.getElementById('cst-mensagem');
    var lpText = document.getElementById('lpText');
    var lpDrink = document.getElementById('lpDrink');
    if (!msg || !lpText) return;
    function marcado(nome) { return document.querySelector('input[name="' + nome + '"]:checked'); }
    function render() {
      var estilo = marcado('fonte');
      var drink = marcado('coquetel');
      lpText.className = 'lp-text ' + (estilo ? estilo.getAttribute('data-cls') : 'f-pprader');
      lpText.textContent = msg.value.trim() || 'Sua mensagem aqui';
      lpDrink.textContent = drink ? drink.value : '';
    }
    document.querySelectorAll('input[name="fonte"], input[name="coquetel"]').forEach(function (r) {
      r.addEventListener('change', render);
    });
    msg.addEventListener('input', render);
    render();
  })();
</script>
@endpush
