@extends('layouts.public')

@section('title', 'Custom — Rótulo personalizado · APTK Spirits')
@section('meta_description', 'APTK Custom: escolha o coquetel, o estilo do rótulo e a mensagem — ou traga a identidade da sua marca no Full Custom.')

@section('hide_feature_band', '1')

@push('styles')
<style>
  /* ---- Custom: hero (leva 10) — texto + duas fotos ---- */
  .cst-hero { position: relative; overflow: hidden; }
  .cst-hero::before { content: ""; position: absolute; top: -12%; right: -6%; width: 560px; height: 560px; background: radial-gradient(circle, var(--gold-faint) 0%, transparent 65%); pointer-events: none; }
  .cst-hero .container-aptk { position: relative; z-index: 1; padding-block: 64px 52px; display: grid; grid-template-columns: 1fr 1fr; gap: 56px; align-items: center; }
  .cst-hero .eyebrow { margin-bottom: 16px; }
  .cst-hero h2 { font-size: clamp(2.6rem, 6vw, 4.2rem); margin: 0 0 20px; max-width: 14ch; }
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

  /* ---- Configurador (leva 11): primeira dobra — prévia ao vivo + escolhas.
     Desktop: prévia sticky à esquerda, título e formulário à direita.
     Celular: título, prévia, formulário. ---- */
  .cst-topo { padding-block: clamp(28px, 4vw, 48px) clamp(64px, 9vw, 120px); }
  .cst-config { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); grid-template-areas: "media side" "media form"; grid-template-rows: auto 1fr; column-gap: clamp(32px, 5vw, 64px); align-items: start; }
  .cst-side { grid-area: side; }
  .cst-config-media { grid-area: media; position: sticky; top: 96px; }
  .cst-form { grid-area: form; }
  /* Moldura fixa (as imagens têm proporções um pouco diferentes): a página
     não pula ao trocar de bebida. A prévia fica centralizada dentro dela. */
  .cst-moldura { display: grid; place-items: center; aspect-ratio: 1 / 1.05; max-width: 460px; margin-inline: auto; background: var(--color-bg-media); border-radius: var(--radius-md); }
  .cst-config-previa { width: 100%; }
  .cst-config-media .cst-nota { text-align: center; font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.12em; text-transform: uppercase; color: var(--color-text-muted); margin: 12px 0 0; }
  .cst-previa__txt.is-vazio { opacity: .35; }
  .cst-side h1 { font-size: clamp(2rem, 4.2vw, 2.8rem); margin: 14px 0 16px; }
  .cst-side > p { color: var(--color-text-muted); line-height: 1.75; margin: 0 0 28px; }
  .cst-form { background: var(--color-bg-elevated); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 28px; }
  .cst-step { border: 0; margin: 0 0 22px; padding: 0 0 22px; border-bottom: 1px solid var(--color-border); min-width: 0; }
  .cst-step legend { float: none; width: auto; font-family: var(--font-display); font-weight: 700; font-size: var(--text-lg); color: var(--color-text); margin-bottom: 14px; padding: 0; }
  .cst-step .cs-num { font-family: var(--font-editorial); font-weight: 400; color: var(--color-primary); margin-right: 6px; }
  .cst-form .f-label { display: block; font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-text-muted); margin: 0 0 8px; }
  .cst-form input[type=text], .cst-form input[type=number] { width: 100%; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text); padding: 12px 14px; font-family: var(--font-body); font-size: var(--text-sm); }
  .cst-form input[type=number] { width: 88px; text-align: center; font-family: var(--font-mono); }
  .cst-form input:focus { outline: none; border-color: var(--color-primary); }
  .cst-form .f-hint { display: flex; justify-content: space-between; gap: 12px; font-size: var(--text-xs); color: var(--color-text-muted); margin: 6px 0 0; }
  .cst-form .f-err { font-size: var(--text-xs); color: var(--color-danger); margin-top: 8px; }

  /* Bebida: chips de rádio */
  .chip-group { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
  .chip-opt { margin: 0; }
  .chip-opt input, .style-opt input { position: absolute; opacity: 0; width: 1px; height: 1px; pointer-events: none; }
  .chip-opt span { display: block; text-align: center; font-family: var(--font-body); font-size: var(--text-sm); color: var(--color-text-muted); border: 1px solid var(--color-border); border-radius: 100px; padding: 8px 10px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: border-color .2s ease, color .2s ease, background-color .2s ease; }
  .chip-opt:hover span { border-color: var(--color-primary-muted); color: var(--color-text); }
  .chip-opt input:checked + span { background: var(--color-primary); border-color: var(--color-primary); color: var(--color-text-inverse); }
  .chip-opt input:focus-visible + span, .style-opt input:focus-visible + .so-tile { outline: 2px solid var(--color-primary); outline-offset: 2px; }

  /* Estilo do rótulo: miniaturas, uma coluna por fonte disponível */
  .style-group { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); gap: 12px; }
  .style-opt { margin: 0; cursor: pointer; }
  .so-tile { display: grid; place-items: center; min-height: 76px; background: var(--color-bg-media); border: 2px solid var(--color-border); border-radius: var(--radius-md); padding: 10px; text-align: center; transition: border-color .2s ease; }
  .style-opt:hover .so-tile { border-color: var(--color-primary-muted); }
  .style-opt input:checked + .so-tile { border-color: var(--color-primary); box-shadow: var(--shadow-glow); }
  .so-ex { color: var(--color-ink); line-height: 1.15; font-size: 1.25rem; }
  .so-ex.cst-f-amithen { font-size: 1.5rem; }
  .so-ex.cst-f-blacksword { font-size: 1.45rem; }
  .so-name { display: block; text-align: center; font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.12em; text-transform: uppercase; color: var(--color-text); margin-top: 8px; }

  .cst-compra { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
  .cst-preco { font-family: var(--font-mono); color: var(--color-text); }
  .cst-preco strong { display: block; font-size: var(--text-xl); font-weight: 400; }
  .cst-preco span { font-size: var(--text-xs); color: var(--color-text-muted); letter-spacing: .06em; text-transform: uppercase; }
  .cst-aviso { font-size: var(--text-sm); color: var(--color-text-muted); border-left: 3px solid var(--color-primary); padding: 4px 0 4px 12px; margin: 0 0 18px; }
  .cst-mini-trilho { display: none; }
  .cst-erro { font-family: var(--font-mono); font-size: var(--text-sm); color: var(--color-danger); border: 1px solid var(--color-border); border-left: 3px solid var(--color-danger); border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 20px; }

  /* ---- Full Custom (leva 10): texto à esquerda, foto à direita ---- */
  .full-band { background: var(--color-ink); border-block: 1px solid var(--color-border); }
  .full-grid { display: grid; grid-template-columns: 1fr 1fr; gap: clamp(32px, 5vw, 64px); align-items: center; }
  .full-band .eyebrow { margin-bottom: 16px; }
  .full-band h2 { font-size: clamp(2rem, 4.6vw, 3rem); color: var(--color-cream); margin: 0 0 16px; }
  .full-band p { color: color-mix(in srgb, var(--color-cream) 78%, transparent); font-size: var(--text-lg); line-height: 1.7; margin: 0 0 30px; max-width: 500px; }
  .full-img { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: var(--radius-lg); border: 1px solid var(--color-border); }

  @media (max-width: 900px) {
    .cst-hero .container-aptk, .full-grid { grid-template-columns: 1fr; gap: 36px; }
    .cst-config { grid-template-columns: minmax(0, 1fr); grid-template-areas: "side" "media" "form"; grid-template-rows: none; row-gap: 24px; }
    .cst-side h1 { margin-bottom: 10px; }
    .cst-side > p { margin-bottom: 0; }
    .cst-config-media { position: static; }
    .cst-moldura { max-width: 340px; }
    /* Miniatura: trilho sticky de altura zero no topo do formulário — a
       barra passa por cima do conteúdo sem ocupar espaço. Some enquanto a
       prévia grande está na tela (classe via JS). */
    .cst-mini-trilho { display: block; position: sticky; top: var(--header-h); z-index: 5; height: 0; margin: -22px -18px 22px; }
    .cst-mini { display: flex; align-items: center; gap: 12px; position: absolute; top: 0; left: 0; right: 0; padding: 8px 18px; background: var(--color-bg-elevated); border-bottom: 1px solid var(--color-border); box-shadow: var(--shadow-card); transition: opacity .2s ease; }
    .cst-mini.is-escondida { opacity: 0; pointer-events: none; }
    .cst-mini__previa { width: 64px; flex: none; border-radius: var(--radius-sm); }
    .cst-mini__info { display: flex; flex-direction: column; gap: 2px; min-width: 0; font-size: var(--text-xs); color: var(--color-text-muted); }
    .cst-mini__bebida { font-family: var(--font-display); font-size: var(--text-sm); color: var(--color-text); }
    .cst-mini__texto { color: var(--color-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cst-mini__detalhe { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cst-hero .container-aptk { padding-block: 44px 40px; }
  }
  @media (max-width: 520px) {
    .cst-form { padding: 22px 18px; }
    .style-group { gap: 8px; }
    .so-tile { min-height: 64px; padding: 8px 6px; }
    .so-ex { font-size: 1rem; }
    .so-ex.cst-f-amithen { font-size: 1.2rem; }
    .so-ex.cst-f-blacksword { font-size: 1.15rem; }
    .chip-group { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .cst-hero-fig:nth-child(2) { margin-top: 28px; }
  }
</style>
@endpush

@section('content')
  {{-- CONFIGURADOR (leva 11), primeira dobra: compra direta da garrafa 375 ml
       personalizada (bebida, texto e fonte de config/custom.php; texto sempre
       preto). Prévia ao vivo; o servidor revalida tudo (CustomService). --}}
  @php
    $sel = [
      'bebida' => old('bebida', $bebidas->keys()->first()),
      'texto'  => old('texto', ''),
      'fonte'  => old('fonte', array_key_first($fontes)),
      'cor'    => $cor,
    ];
  @endphp
  <section class="section cst-topo" id="pedido">
    <div class="container-aptk cst-config">
      <div class="cst-side">
        <span class="eyebrow">Crie seu APTK</span>
        <h1 class="title-editorial">Um coquetel. A sua assinatura.</h1>
        <p>Escolha seu coquetel favorito, escreva a sua mensagem e defina o estilo do rótulo. A garrafa de {{ $volume }} sai com o seu pedido, pronta para presentear.</p>
      </div>

      <div class="cst-config-media">
        <div class="cst-moldura">
          <x-custom.previa id="cstPrevia" class="cst-config-previa" :p="$sel" />
        </div>
        <p class="cst-nota">Imagem meramente ilustrativa</p>
      </div>

      <div class="cst-form">
        {{-- Celular: miniatura da prévia que acompanha a rolagem do formulário
             (sticky no topo da área do formulário). No desktop fica escondida:
             a prévia grande já é sticky ao lado. --}}
        <div class="cst-mini-trilho">
          <div class="cst-mini is-escondida" id="cstMini" aria-hidden="true">
            <x-custom.previa class="cst-mini__previa" :p="$sel" />
            <span class="cst-mini__info">
              <strong class="cst-mini__bebida"></strong>
              <span class="cst-mini__texto"></span>
              <span class="cst-mini__detalhe"></span>
            </span>
          </div>
        </div>

        @if (session('stock_error'))
          <div class="cst-erro">{{ session('stock_error') }}</div>
        @endif

        <form method="POST" action="{{ route('custom.cart') }}" id="cstForm">
          @csrf

          <fieldset class="cst-step">
            <legend><span class="cs-num">01</span> Seu coquetel</legend>
            <div class="chip-group" role="radiogroup" aria-label="Escolha da bebida">
              @foreach ($bebidas as $slug => $b)
                <label class="chip-opt" title="{{ $b['nome'] }}">
                  <input type="radio" name="bebida" value="{{ $slug }}" required @checked($sel['bebida'] === $slug)
                         data-img="{{ $b['imagem'] }}" data-ratio="{{ $b['proporcao'] }}"
                         data-area="{{ implode(',', $b['area']) }}" data-tam="{{ config('custom.bebidas.'.$slug.'.fonte') }}"
                         data-nome="{{ $b['nome'] }}">
                  <span>{{ $b['nome'] }}</span>
                </label>
              @endforeach
            </div>
            @error('bebida') <p class="f-err">{{ $message }}</p> @enderror
          </fieldset>

          <fieldset class="cst-step">
            <legend><span class="cs-num">02</span> Seu texto</legend>
            <label class="f-label" for="cst-texto">Texto do rótulo</label>
            <input id="cst-texto" type="text" name="texto" value="{{ $sel['texto'] }}" required maxlength="{{ $limite }}" autocomplete="off" placeholder="Ex.: Bia & Eduardo, 20.09">
            <p class="f-hint"><span>Até {{ $limite }} caracteres, em até {{ config('custom.linhas') }} linhas.</span><span id="cstConta">{{ mb_strlen($sel['texto']) }}/{{ $limite }}</span></p>
            @error('texto') <p class="f-err">{{ $message }}</p> @enderror
          </fieldset>

          <fieldset class="cst-step">
            <legend><span class="cs-num">03</span> Estilo do rótulo</legend>
            {{-- Só as fontes disponíveis agora (CustomService::fontes: a Blacksword
                 entra quando o arquivo existir). O texto é sempre preto. --}}
            <div class="style-group" role="radiogroup" aria-label="Estilo do rótulo">
              @foreach ($fontes as $fonte => $f)
                <label class="style-opt">
                  <input type="radio" name="fonte" value="{{ $fonte }}" required @checked($sel['fonte'] === $fonte)
                         data-classe="{{ $f['classe'] }}" data-escala="{{ $f['escala'] }}">
                  <span class="so-tile"><span class="so-ex {{ $f['classe'] }}">Bia &amp; Eduardo</span></span>
                  <span class="so-name">{{ $f['nome'] }}</span>
                </label>
              @endforeach
            </div>
            @error('fonte') <p class="f-err">{{ $message }}</p> @enderror
            @error('cor') <p class="f-err">{{ $message }}</p> @enderror
          </fieldset>

          <div class="cst-compra">
            <div class="cst-preco">
              <strong>R$ {{ number_format($preco, 2, ',', '.') }}</strong>
              <span>Garrafa {{ $volume }} · pronta entrega</span>
            </div>
            <div>
              <label class="f-label" for="cst-qty">Quantidade</label>
              <input id="cst-qty" type="number" name="qty" value="{{ old('qty', 1) }}" min="1" max="50">
            </div>
          </div>
          @error('qty') <p class="f-err">{{ $message }}</p> @enderror

          <p class="cst-aviso">{{ $aviso }}</p>
          <button type="submit" class="btn-aptk btn-aptk--block">Adicionar ao carrinho <span class="btn-arrow">→</span></button>
        </form>
      </div>
    </div>
  </section>

  {{-- APRESENTAÇÃO (briefing p.17–18): vem depois do configurador. --}}
  <section class="cst-hero">
    <div class="container-aptk">
      <div>
        <span class="eyebrow">APTK Custom</span>
        <h2 class="title-editorial">Sua história. Nossa alquimia.</h2>
        <p class="lead">Transformamos coquetéis em presentes que carregam histórias. Para marcas, celebrações e momentos especiais, criamos rótulos personalizados que unem alta coquetelaria, criatividade e identidade.</p>
        <a href="#pedido" class="btn-aptk">Personalize seu drink <span class="btn-arrow">→</span></a>
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
  // Prévia ao vivo (leva 11): troca imagem, área do rótulo, fonte e texto (cor fixa).
  // Os dados de cada opção vêm da config (data-*); o servidor revalida tudo.
  (function () {
    var form = document.getElementById('cstForm');
    var previa = document.getElementById('cstPrevia');
    if (!form || !previa) return;
    var mini = document.getElementById('cstMini');
    var previas = [previa].concat(mini ? [mini.querySelector('.cst-previa')] : []);
    var input = document.getElementById('cst-texto');
    var conta = document.getElementById('cstConta');
    var limite = parseInt(input.getAttribute('maxlength'), 10);
    function marcado(nome) { return form.querySelector('input[name="' + nome + '"]:checked'); }
    function pintar(p, b, f, v) {
      var img = p.querySelector('.cst-previa__img');
      var area = p.querySelector('.cst-previa__area');
      var txt = p.querySelector('.cst-previa__txt');
      if (b) {
        if (img && img.getAttribute('src') !== b.dataset.img) img.src = b.dataset.img;
        p.style.aspectRatio = b.dataset.ratio;
        var a = b.dataset.area.split(',');
        area.style.left = a[0] + '%'; area.style.top = a[1] + '%';
        area.style.width = a[2] + '%'; area.style.height = a[3] + '%';
      }
      var tam = b ? parseFloat(b.dataset.tam) : 3;
      txt.className = 'cst-previa__txt ' + (f ? f.dataset.classe : '');
      txt.style.fontSize = (tam * (f ? parseFloat(f.dataset.escala) : 1)) + 'cqw';
      txt.textContent = v.trim() || 'Seu texto aqui';
      txt.classList.toggle('is-vazio', !v.trim());
    }
    function render() {
      var b = marcado('bebida'), f = marcado('fonte');
      // Sem Enter: a quebra é automática (o servidor recusa quebra de linha).
      var v = input.value.replace(/[\r\n]+/g, ' ');
      previas.forEach(function (p) { pintar(p, b, f, v); });
      conta.textContent = v.length + '/' + limite;
      if (mini) {
        mini.querySelector('.cst-mini__bebida').textContent = b ? b.dataset.nome : '';
        mini.querySelector('.cst-mini__texto').textContent = v.trim() ? '“' + v.trim() + '”' : '';
        mini.querySelector('.cst-mini__detalhe').textContent = f ? f.closest('.style-opt').querySelector('.so-name').textContent : '';
      }
    }
    // Miniatura só aparece quando a prévia grande saiu da tela.
    if (mini && 'IntersectionObserver' in window) {
      new IntersectionObserver(function (e) {
        mini.classList.toggle('is-escondida', e[0].isIntersecting);
      }, { rootMargin: '-64px 0px 0px 0px' }).observe(previa);
    }
    form.addEventListener('change', render);
    input.addEventListener('input', render);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
    render();
  })();
</script>
@endpush
