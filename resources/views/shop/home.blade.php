@extends('layouts.public')

@section('title', 'APTK Spirits — Drinks & histórias pra contar')

@push('styles')
<style>
  /* ---- Home: hero com o vídeo de fundo (leva 10) ---- */
  .hero { position: relative; overflow: hidden; background: var(--color-ink); border-bottom: 1px solid var(--color-border); min-height: min(86vh, 820px); display: flex; flex-direction: column; }
  .hero-video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: 62% center; }
  .hero-shade { position: absolute; inset: 0; background:
      linear-gradient(90deg, var(--color-ink) 0%, color-mix(in srgb, var(--color-ink) 82%, transparent) 34%, color-mix(in srgb, var(--color-ink) 18%, transparent) 70%, transparent 100%),
      linear-gradient(0deg, var(--color-ink) 0%, transparent 38%); }
  .hero-inner { position: relative; z-index: 1; flex: 1; display: flex; align-items: center; padding-block: clamp(56px, 9vw, 110px) clamp(40px, 6vw, 72px); }
  .hero-copy { max-width: 600px; }
  .hero-copy .eyebrow { margin-bottom: 18px; }
  .hero h1 { font-size: clamp(2.5rem, 5.6vw, 4.4rem); color: var(--color-cream); margin: 0 0 22px; }
  .hero p { font-size: clamp(var(--text-base), 1.6vw, var(--text-lg)); color: color-mix(in srgb, var(--color-cream) 80%, transparent); max-width: 520px; margin: 0 0 32px; }
  .hero-ctas { display: flex; flex-wrap: wrap; gap: 14px; }

  /* Faixa de marca no pé do hero */
  .hero-facts { position: relative; z-index: 1; border-top: 1px solid var(--color-border); background: color-mix(in srgb, var(--color-ink) 70%, transparent); backdrop-filter: blur(6px); }
  .hero-facts-list { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(4, 1fr); }
  .hero-facts-list li { display: flex; align-items: center; gap: 14px; padding: 22px 24px; border-left: 1px solid var(--color-border); }
  .hero-facts-list li:first-child { border-left: 0; padding-left: 0; }
  .hf-big { font-family: var(--font-display); font-weight: 700; font-size: clamp(1.4rem, 2.4vw, 1.9rem); color: var(--color-primary); line-height: 1; }
  .hf-lbl { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.14em; text-transform: uppercase; color: var(--color-text-muted); line-height: 1.5; max-width: 15ch; }
  .hf-line { width: 34px; height: 1px; background: var(--color-primary); flex-shrink: 0; }
  .hf-last .hf-lbl { color: var(--color-text); }

  /* ---- Home: um universo de possibilidades (leva 10) ---- */
  .hx-label { margin: 8px 0 20px; }
  .hx-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: clamp(48px, 6vw, 72px); }
  .hx-card { --mc-ratio: 4 / 5; }
  .hx-eco { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
  .hx-eco-card { flex-direction: row; }
  .hx-eco-card .mc-media { flex: 0 0 42%; aspect-ratio: auto; min-height: 260px; }
  .hx-eco-card .mc-body { justify-content: center; padding: 28px; }
  /* Recorte das fotos verticais (leva 13): rosto do bartender / gelo + copo */
  .hx-eco-img--barin { object-position: 50% 30%; }
  .hx-eco-img--ice { object-position: 62% 65%; }

  /* ---- Home: produto em destaque (retangular, imagem à esquerda) ---- */
  .featured-band { background: var(--color-ink); border-block: 1px solid var(--color-border); }
  .featured-grid { display: grid; grid-template-columns: minmax(280px, 420px) 1fr; gap: clamp(32px, 5vw, 64px); align-items: center; padding-block: clamp(36px, 5vw, 56px); }
  .featured-img { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: contain; object-position: center; padding: 18px; border-radius: var(--radius-lg); border: 1px solid var(--color-border); box-shadow: var(--shadow-card); background: var(--media-bg, var(--color-bg-media)); }
  .featured-body .badge-aptk { margin-bottom: 16px; }
  .featured-body .product-name { font-family: var(--font-script); font-size: clamp(2.2rem, 5vw, 3.2rem); color: var(--color-primary); margin-bottom: 12px; line-height: 1.05; }
  .featured-body .sub { font-family: var(--font-display); font-style: italic; font-size: var(--text-lg); color: var(--color-cream); margin: 0 0 14px; }
  .featured-body p.desc { color: color-mix(in srgb, var(--color-cream) 75%, transparent); margin: 0 0 24px; max-width: 460px; }
  .featured-specs { display: flex; gap: 36px; margin-bottom: 26px; flex-wrap: wrap; align-items: flex-start; }
  .featured-specs .k { display: block; font-size: var(--text-xs); color: color-mix(in srgb, var(--color-cream) 60%, transparent); letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 6px; }
  .featured-specs .v { font-family: var(--font-mono); color: var(--color-cream); font-size: var(--text-base); }
  .size-picker { display: flex; gap: 8px; flex-wrap: wrap; }
  .size-chip-btn { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.08em; padding: 7px 14px; border-radius: 100px; border: 1px solid var(--color-border); background: transparent; color: color-mix(in srgb, var(--color-cream) 75%, transparent); cursor: pointer; transition: color .2s ease, border-color .2s ease, background-color .2s ease; }
  .size-chip-btn:hover { border-color: var(--color-primary-muted); color: var(--color-cream); }
  .size-chip-btn.is-active { background: var(--color-primary); border-color: var(--color-primary); color: var(--color-text-inverse); }
  .featured-buy { display: flex; align-items: center; gap: 24px; flex-wrap: wrap; }
  .featured-buy .price-tag { font-family: var(--font-mono); font-size: var(--text-2xl); color: var(--color-cream); }

  /* ---- Home: seções largas no estilo do hero (leva 13) ---- */
  .hw { position: relative; overflow: hidden; background: var(--color-ink); border-bottom: 1px solid var(--color-border); min-height: min(72vh, 640px); display: flex; }
  .hw-media { position: absolute; inset: 0; container-type: size; }
  .hw-media > img, .hw-media > video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
  .hw-yt { position: absolute; inset: 0; opacity: 0; transition: opacity .8s ease; pointer-events: none; }
  .hw.is-playing .hw-yt { opacity: 1; }
  /* 16:9 cobrindo a seção inteira (o player do YouTube não tem object-fit) */
  .hw-yt iframe { position: absolute; top: 50%; left: 50%; width: max(100cqw, 177.78cqh); height: max(100cqh, 56.25cqw); transform: translate(-50%, -50%); border: 0; }
  .hw-inner { position: relative; z-index: 1; flex: 1; display: flex; align-items: center; padding-block: clamp(56px, 8vw, 96px); }
  .hw-copy { max-width: 560px; }
  .hw-copy .eyebrow { margin-bottom: 18px; }
  .hw h2 { font-size: clamp(2.2rem, 4.6vw, 3.6rem); color: var(--color-cream); margin: 0 0 20px; }
  .hw p { font-size: clamp(var(--text-base), 1.6vw, var(--text-lg)); color: color-mix(in srgb, var(--color-cream) 80%, transparent); max-width: 480px; margin: 0 0 30px; }
  /* Foto vertical (2:3): no desktop fica a fatia da fachada com o letreiro */
  .hw--lojas .hw-media > img { object-position: 50% 11%; }

  /* ---- Home: clube (teaser) ---- */
  .club { background: var(--color-bg-card); border-block: 1px solid var(--color-border); }


  /* ---- Home: vitrine/carrossel de produtos (leva 06) ---- */
  .shw-track { position: relative; }
  .shw-slide { display: none; }
  .shw-slide.is-active { display: block; animation: shwIn .45s ease; }
  @keyframes shwIn { from { opacity: 0; transform: translateX(14px); } to { opacity: 1; transform: none; } }
  .shw-arrow { position: absolute; top: 50%; transform: translateY(-50%); z-index: 2; width: 44px; height: 44px; border-radius: 50%; border: 1px solid var(--color-border); background: var(--color-bg); color: var(--color-text); font-size: 22px; line-height: 1; cursor: pointer; display: grid; place-items: center; transition: border-color .2s ease, color .2s ease; }
  .shw-arrow:hover { border-color: var(--color-primary); color: var(--color-primary); }
  .shw-arrow--prev { left: -8px; }
  .shw-arrow--next { right: -8px; }
  .shw-dots { display: flex; gap: 8px; justify-content: center; margin-top: 22px; }
  .shw-dot { width: 8px; height: 8px; border-radius: 50%; border: 0; background: var(--color-border); cursor: pointer; transition: background .2s ease, transform .2s ease; }
  .shw-dot.is-active { background: var(--color-primary); transform: scale(1.25); }

  /* ---- Home: Dicas e Novidades (leva 06) ---- */
  .nvh { background: var(--color-bg-card); border-block: 1px solid var(--color-border); }
  .nvh-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
  .nvh-card { display: flex; flex-direction: column; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; text-decoration: none; transition: border-color .25s ease, transform .25s ease; }
  .nvh-card:hover { border-color: var(--color-primary-muted); transform: translateY(-3px); }
  .nvh-cover { position: relative; aspect-ratio: 16 / 10; background: var(--color-ink); }
  .nvh-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .nvh-date { position: absolute; top: 12px; left: 12px; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-sm); text-align: center; padding: 6px 10px; line-height: 1.15; }
  .nvh-date .d { font-family: var(--font-mono); font-size: var(--text-lg); color: var(--color-text); display: block; }
  .nvh-date .m { font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.14em; text-transform: uppercase; color: var(--color-primary); }
  .nvh-body { padding: 16px 18px 20px; }
  .nvh-body h3 { font-family: var(--font-display); font-size: var(--text-lg); color: var(--color-text); margin: 0; }
  @media (max-width: 980px) {
    .nvh-grid { grid-template-columns: 1fr; }
    /* Slide empilhado: as setas ficam no meio da foto (4:3, largura do
       container), longe do texto. top = padding do slide + metade da foto. */
    .shw-arrow { top: calc(clamp(36px, 5vw, 56px) + (100vw - 48px) * 0.375); width: 38px; height: 38px; font-size: 20px; background: color-mix(in srgb, var(--color-bg) 82%, transparent); }
    .shw-arrow--prev { left: 6px; }
    .shw-arrow--next { right: 6px; }
  }

  /* ---- Home: animação de entrada do hero ---- */
  @keyframes riseIn { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
  .hero-copy > * { animation: riseIn .7s ease both; }
  .hero-copy > *:nth-child(2) { animation-delay: .08s; }
  .hero-copy > *:nth-child(3) { animation-delay: .16s; }
  .hero-copy > *:nth-child(4) { animation-delay: .24s; }

  /* ---- Home: responsivo ---- */
  @media (max-width: 980px) {
    .featured-grid { grid-template-columns: 1fr; gap: 28px; }
    .hx-grid { grid-template-columns: repeat(2, 1fr); }
    .hx-eco { grid-template-columns: 1fr; }
    .hero-facts-list { grid-template-columns: repeat(2, 1fr); }
    .hero-facts-list li { border-top: 1px solid var(--color-border); }
    .hero-facts-list li:nth-child(-n+2) { border-top: 0; }
    .hero-facts-list li:nth-child(3) { border-left: 0; padding-left: 0; }
  }
  @media (max-width: 820px) {
    .hw-inner { align-items: flex-end; }
    /* No celular o texto fica embaixo, com o gradiente de baixo pra cima */
    .hero-shade { background: linear-gradient(0deg, var(--color-ink) 0%, color-mix(in srgb, var(--color-ink) 78%, transparent) 55%, color-mix(in srgb, var(--color-ink) 35%, transparent) 100%); }
    .hero-inner { align-items: flex-end; }
  }
  @media (max-width: 520px) {
    .hx-grid { gap: 12px; }
    .hx-card .mc-body { padding: 14px 14px 16px; }
    .hx-card h3 { font-size: var(--text-base); }
    .hx-card p { font-size: var(--text-xs); }
    .hx-eco-card { flex-direction: column; }
    .hx-eco-card .mc-media { flex-basis: auto; aspect-ratio: 16 / 10; min-height: 0; }
    .hx-eco-img--barin { object-position: 50% 42%; }
    .hero-ctas .btn-aptk { width: 100%; }
    .hw-copy .btn-aptk { width: 100%; }
    .hero-facts-list li { padding: 16px 12px; gap: 10px; }
  }
</style>
@endpush

@section('content')

  {{-- HERO (leva 10, briefing p.9–10): o vídeo do Negroni vira o fundo do
       hero inteiro; texto à esquerda sobre o gradiente. Poster = frame do
       próprio vídeo (img/aptk/hero-negroni-poster.jpg). --}}
  <section class="hero on-dark">
    <video class="hero-video" autoplay muted loop playsinline poster="{{ asset('img/aptk/hero-negroni-poster.jpg') }}" aria-hidden="true">
      <source src="{{ asset('video/hero-negroni.mp4') }}" type="video/mp4">
    </video>
    <div class="hero-shade"></div>
    <div class="container-aptk hero-inner">
      <div class="hero-copy">
        <span class="eyebrow">Feito por humanos inquietos.</span>
        <h1 class="title-editorial">Não seguimos tendências. Criamos novos caminhos.</h1>
        <p>Pioneiros na alta coquetelaria engarrafada no Brasil. Unimos criatividade, técnica e alma brasileira para transformar a maneira de brindar, celebrar e compartilhar histórias.</p>
        <div class="hero-ctas">
          <a href="{{ route('catalog') }}" class="btn-aptk">Explore nossos coquetéis <span class="btn-arrow">→</span></a>
          <a href="{{ route('pages.show', 'quem-somos') }}" class="btn-aptk btn-aptk--outline">Conheça nossa história</a>
        </div>
      </div>
    </div>
    {{-- Faixa de marca (substitui os números provisórios 03 / 100%). --}}
    <div class="hero-facts">
      <div class="container-aptk">
        <ul class="hero-facts-list">
          <li><span class="hf-big">2016</span><span class="hf-lbl">Pioneiros na categoria</span></li>
          <li><span class="hf-big">Brasil</span><span class="hf-lbl">Alma brasileira em cada receita</span></li>
          <li><span class="hf-big">Autoral</span><span class="hf-lbl">Coquetelaria com identidade</span></li>
          <li class="hf-last"><span class="hf-line" aria-hidden="true"></span><span class="hf-lbl">Feito para compartilhar</span></li>
        </ul>
      </div>
    </div>
  </section>

  {{-- VITRINE DE PRODUTOS (leva 06): subiu pra logo após o hero/números e
       ganhou navegação lateral — destaque do admin primeiro, demais ativos
       na sequência. Layout do card preservado; seletor de volume por slide. --}}
  @if ($showcase->isNotEmpty())
  <section class="featured-band on-dark">
    <div class="container-aptk" style="position:relative;">
      <div class="shw-track" id="shwTrack">
        @foreach ($showcase as $idx => $product)
          @php
            $opts = $product->sizeOptions();
            $def  = $product->defaultSize();
          @endphp
          <div class="shw-slide {{ $idx === 0 ? 'is-active' : '' }}" data-slide="{{ $idx }}">
            <div class="featured-grid">
              {{-- O carrossel só recebe produto com foto (HomeController). --}}
              <x-product.image class="featured-img" :image="$product->fotoPadrao()" :alt="$product->name" />
              <div class="featured-body">
                <span class="badge-aptk">{{ $product->category?->name ?? 'Destaque' }}</span>
                <p class="product-name">{{ $product->name }}</p>
                @if ($product->short_description)
                  <p class="sub">{{ $product->short_description }}</p>
                @endif
                @if ($product->description)
                  <p class="desc">{{ \Illuminate\Support\Str::limit($product->description, 160) }}</p>
                @endif

                <div class="featured-specs">
                  @if ($product->abv)
                    <div><span class="k">Teor</span><span class="v">{{ $product->abv }}% vol.</span></div>
                  @endif
                  @if (count($opts))
                    <div>
                      <span class="k">Volume</span>
                      <div class="size-picker shw-size-picker">
                        @foreach ($opts as $size => $sizePrice)
                          <button type="button"
                                  class="size-chip-btn {{ $size === $def ? 'is-active' : '' }}"
                                  data-size="{{ $size }}"
                                  data-price="{{ number_format($sizePrice, 2, ',', '.') }}">{{ $size }}</button>
                        @endforeach
                      </div>
                    </div>
                  @endif
                </div>

                <div class="featured-buy">
                  <span class="price-tag shw-price">R$ {{ number_format($product->priceForSize($def), 2, ',', '.') }}</span>
                  <form action="{{ route('cart.add') }}" method="POST" style="display:inline;">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="size" class="shw-size-input" value="{{ $def }}">
                    <button type="submit" class="btn-aptk">Comprar</button>
                  </form>
                  <a href="{{ route('product', $product->slug) }}" class="btn-aptk btn-aptk--outline">Ver detalhes</a>
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>

      @if ($showcase->count() > 1)
        <button type="button" class="shw-arrow shw-arrow--prev" id="shwPrev" aria-label="Produto anterior">‹</button>
        <button type="button" class="shw-arrow shw-arrow--next" id="shwNext" aria-label="Próximo produto">›</button>
        <div class="shw-dots" id="shwDots">
          @foreach ($showcase as $idx => $p)
            <button type="button" class="shw-dot {{ $idx === 0 ? 'is-active' : '' }}" data-goto="{{ $idx }}" aria-label="Ir para {{ $p->name }}"></button>
          @endforeach
        </div>
      @endif
    </div>
  </section>
  @endif

  {{-- UM UNIVERSO DE POSSIBILIDADES (leva 10, briefing p.11–12): substitui
       "Carta de Coquetéis / O que você procura". Dois blocos: Explore a APTK
       e Nosso ecossistema. --}}
  @php
    // Fotos da leva 13 (img/aptk/home-*.jpg, originais fora do git em
    // img/fotos_site/). `pos` = object-position do recorte 4:5.
    $explore = [
      ['href' => route('catalog'), 'img' => asset('img/aptk/home-coqueteis-prontos.jpg'), 'alt' => 'Coquetel sendo servido de uma garrafa APTK em copo com gelo',
       'h' => 'Coquetéis Prontos', 'p' => 'A excelência da alta coquetelaria, pronta para servir.', 'pos' => '64% center'],
      // TEXTO PROVISÓRIO (leva 13): aprovação da gestão. O card passou de bases para o Custom.
      ['href' => route('custom'), 'img' => asset('img/aptk/home-crie-seu-drink.jpg'), 'alt' => 'Mão escrevendo à mão no rótulo de uma garrafa APTK',
       'h' => 'Crie Seu Drink', 'p' => 'Um coquetel. A sua assinatura.', 'pos' => 'center 55%'],
      ['href' => route('pages.show', 'eventos').'#gifting', 'img' => asset('img/aptk/home-arte-de-presentear.jpg'), 'alt' => 'Caixa de presente APTK com garrafa de Old Fashioned',
       'h' => 'Arte de Presentear', 'p' => 'Presentes que transformam gestos em histórias.', 'pos' => '70% center'],
      ['href' => route('pages.show', 'clube'), 'img' => asset('img/aptk/home-clube.jpg'), 'alt' => 'Negroni sendo servido em copo de cristal ao lado da garrafa',
       'h' => 'Clube APTK', 'p' => 'Seleções especiais, descobertas e edições limitadas.', 'pos' => 'center 60%'],
    ];
  @endphp
  <section class="section hx">
    <div class="container-aptk">
      <div class="section-head">
        <h2 class="section-title title-editorial">Um universo de possibilidades.</h2>
        <p>Dos clássicos da coquetelaria às criações autorais. Descubra diferentes formas de brindar, presentear e celebrar.</p>
      </div>

      <span class="eyebrow hx-label">Explore a APTK</span>
      <div class="hx-grid">
        @foreach ($explore as $card)
          <a href="{{ $card['href'] }}" class="media-card hx-card">
            <div class="mc-media">
              <img src="{{ $card['img'] }}" alt="{{ $card['alt'] }}" loading="lazy" style="object-position: {{ $card['pos'] }};">
            </div>
            <div class="mc-body">
              <h3>{{ $card['h'] }}</h3>
              <p>{{ $card['p'] }}</p>
              <span class="link-arrow">Explorar <span class="btn-arrow">→</span></span>
            </div>
          </a>
        @endforeach
      </div>

      <span class="eyebrow hx-label">Nosso ecossistema</span>
      <div class="hx-eco">
        <a href="{{ route('barin') }}" class="media-card hx-eco-card">
          <div class="mc-media"><img class="hx-eco-img--barin" src="{{ asset('img/aptk/home-barin.jpg') }}" alt="Bartender mexendo um drink no balcão" loading="lazy"></div>
          <div class="mc-body">
            <span class="mc-eyebrow">BARIN</span>
            <h3 class="title-editorial">Feito para o ritmo do bar</h3>
            <p>Qualidade, praticidade e consistência em cada serviço.</p>
            <span class="link-arrow">Conhecer a BARIN <span class="btn-arrow">→</span></span>
          </div>
        </a>
        <a href="https://ice4pros.com.br" class="media-card hx-eco-card" target="_blank" rel="noopener noreferrer">
          <div class="mc-media"><img class="hx-eco-img--ice" src="{{ asset('img/aptk/home-ice4pros.jpg') }}" alt="Gelo cristal lapidado Ice4Pros ao lado de um drink" loading="lazy"></div>
          <div class="mc-body">
            <span class="mc-eyebrow">Ice4Pros</span>
            <h3 class="title-editorial">O gelo faz a diferença</h3>
            <p>Gelo profissional para elevar cada detalhe do coquetel.</p>
            <span class="link-arrow">Conhecer a Ice4Pros <span class="btn-arrow">→</span></span>
          </div>
        </a>
      </div>
    </div>
  </section>

  {{-- SEÇÕES LARGAS (leva 13): no estilo do hero (mídia de fundo, mesmo
       gradiente e tipografia). Substituem a vitrine editorial, que saiu.
       Collabs: vídeo do YouTube (youtube-nocookie), mudo, em loop, sem
       controles. O poster fica sempre por baixo e o player só aparece quando
       começa a tocar (autoplay bloqueado, API fora do ar ou
       prefers-reduced-motion = só o poster). Com public/video/collabs.mp4 no
       lugar, usa o mp4 local e nem carrega o YouTube. --}}
  @php $collabsMp4 = is_file(public_path('video/collabs.mp4')); @endphp
  <section class="hw on-dark" @unless ($collabsMp4) data-yt="j0ozDwpm9L8" @endunless>
    <div class="hw-media">
      @if ($collabsMp4)
        <video autoplay muted loop playsinline poster="{{ asset('img/aptk/collabs-poster.jpg') }}" aria-hidden="true">
          <source src="{{ asset('video/collabs.mp4') }}" type="video/mp4">
        </video>
      @else
        <img src="{{ asset('img/aptk/collabs-poster.jpg') }}" alt="" loading="lazy" style="object-position: 70% center;">
        <div class="hw-yt" aria-hidden="true"><div id="collabsYt"></div></div>
      @endif
    </div>
    <div class="hero-shade"></div>
    <div class="container-aptk hw-inner">
      <div class="hw-copy">
        <span class="eyebrow">Collabs</span>
        <h2 class="title-editorial">Encontros que criam o extraordinário.</h2>
        <p>Projetos autorais que conectam marcas, criatividade e coquetelaria.</p>
        <a href="{{ route('pages.show', 'collabs') }}" class="btn-aptk">Explore as collabs <span class="btn-arrow">→</span></a>
      </div>
    </div>
  </section>

  <section class="hw hw--lojas on-dark">
    <div class="hw-media">
      <img src="{{ asset('img/aptk/home-nossas-lojas.jpg') }}" alt="Fachada iluminada da loja APTK Spirits" loading="lazy">
    </div>
    <div class="hero-shade"></div>
    <div class="container-aptk hw-inner">
      <div class="hw-copy">
        <span class="eyebrow">Nossas lojas</span>
        <h2 class="title-editorial">A alquimia ganha endereço.</h2>
        <p>Descubra nossos espaços e mergulhe no universo da marca.</p>
        <a href="{{ route('pages.show', 'quem-somos') }}#onde-encontrar" class="btn-aptk">Encontre uma loja <span class="btn-arrow">→</span></a>
      </div>
    </div>
  </section>

  {{-- Seção "Uma holding, três mundos" REMOVIDA (leva 01). --}}

  {{-- CLUBE (leva 03 — pré-lançamento): a vitrine de planos SAIU por decisão
       do cliente (sem valores/planos/benefícios). Teaser de captação abaixo;
       a máquina de assinatura segue dormente no código pro lançamento. --}}
  {{-- DICAS E NOVIDADES (leva 06): 3 últimos posts — se não houver
       publicações, a seção inteira se esconde. --}}
  @if ($latestPosts->isNotEmpty())
  <section class="section nvh">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Dicas e Novidades</span>
        <h2 class="section-title">Conteúdo para entusiastas</h2>
        <p>Dicas, novidades, eventos e receitas para harmonizar com os seus drinks.</p>
      </div>
      <div class="nvh-grid">
        @foreach ($latestPosts as $post)
          <a href="{{ route('novidades.show', $post->slug) }}" class="nvh-card">
            <div class="nvh-cover">
              <img src="{{ $post->capaUrl() }}" alt="{{ $post->title }}" loading="lazy">
              <span class="nvh-date">
                <span class="d">{{ $post->published_at->format('d') }}</span>
                <span class="m">{{ $post->published_at->translatedFormat('M') }}</span>
              </span>
            </div>
            <div class="nvh-body">
              <h3>{{ $post->title }}</h3>
            </div>
          </a>
        @endforeach
      </div>
      <div style="text-align:center; margin-top:26px;">
        <a href="{{ route('novidades.index') }}" class="btn-aptk btn-aptk--outline">Ver todas as novidades</a>
      </div>
    </div>
  </section>
  @endif

  <section class="section club">
    <div class="container-aptk" style="max-width: 780px; text-align: center;">
      <div class="section-head" style="margin-bottom: 22px;">
        <span class="eyebrow">Clube APTK</span>
        <h2 class="section-title title-editorial">Pertencer é só o começo.</h2>
        <p>Hospitalidade, cultura, gastronomia e coquetelaria. Comece grátis no Explorer, colecione no Collector e no Connoisseur — e, quem sabe um dia, receba o convite do Le Cercle.</p>
      </div>
      <a href="{{ route('pages.show', 'clube') }}" class="btn-aptk">Conhecer o Clube</a>
    </div>
  </section>

@endsection

@push('scripts')
<script>
  // Vitrine de produtos (leva 06): navegação lateral + seletor de volume
  // POR SLIDE (cada card tem seu picker, preço e input de tamanho).
  (function () {
    var slides = Array.prototype.slice.call(document.querySelectorAll('.shw-slide'));
    if (!slides.length) return;
    var dots = Array.prototype.slice.call(document.querySelectorAll('.shw-dot'));
    var current = 0;

    function goTo(i) {
      current = (i + slides.length) % slides.length;
      slides.forEach(function (sl, n) { sl.classList.toggle('is-active', n === current); });
      dots.forEach(function (d, n) { d.classList.toggle('is-active', n === current); });
    }

    var prev = document.getElementById('shwPrev');
    var next = document.getElementById('shwNext');
    if (prev) prev.addEventListener('click', function () { goTo(current - 1); });
    if (next) next.addEventListener('click', function () { goTo(current + 1); });
    dots.forEach(function (d) {
      d.addEventListener('click', function () { goTo(parseInt(d.getAttribute('data-goto'), 10)); });
    });

    // Seletor de volume: escopo do próprio slide.
    slides.forEach(function (slide) {
      var priceEl = slide.querySelector('.shw-price');
      var sizeInput = slide.querySelector('.shw-size-input');
      slide.querySelectorAll('.shw-size-picker .size-chip-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
          slide.querySelectorAll('.size-chip-btn').forEach(function (b) { b.classList.remove('is-active'); });
          btn.classList.add('is-active');
          if (priceEl) priceEl.textContent = 'R$ ' + btn.getAttribute('data-price');
          if (sizeInput) sizeInput.value = btn.getAttribute('data-size');
        });
      });
    });
  })();

  // Collabs (leva 13): vídeo do YouTube de fundo. Só carrega a API quando a
  // seção chega perto da tela; o iframe aparece (is-playing) quando o player
  // começa a tocar — se o autoplay for bloqueado, fica o poster.
  (function () {
    var sec = document.querySelector('.hw[data-yt]');
    if (!sec || !('IntersectionObserver' in window)) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    function iniciar() {
      window.onYouTubeIframeAPIReady = function () {
        new YT.Player('collabsYt', {
          host: 'https://www.youtube-nocookie.com',
          videoId: sec.getAttribute('data-yt'),
          playerVars: {
            autoplay: 1, mute: 1, controls: 0, loop: 1, playlist: sec.getAttribute('data-yt'),
            playsinline: 1, rel: 0, disablekb: 1, fs: 0, iv_load_policy: 3, modestbranding: 1,
          },
          events: {
            onReady: function (e) { e.target.mute(); e.target.playVideo(); },
            // Revela 2 s depois de começar: o YouTube mostra um controle no
            // meio do vídeo nos primeiros segundos. Depois fica (o loop
            // reinicia sem voltar pro poster).
            onStateChange: function (e) {
              if (e.data === YT.PlayerState.PLAYING && !sec.dataset.revelado) {
                sec.dataset.revelado = '1';
                setTimeout(function () { sec.classList.add('is-playing'); }, 2000);
              }
            },
          },
        });
        var f = document.querySelector('#collabsYt');
        if (f) { f.setAttribute('tabindex', '-1'); f.setAttribute('title', 'Vídeo das collabs da APTK'); }
      };
      var s = document.createElement('script');
      s.src = 'https://www.youtube.com/iframe_api';
      document.head.appendChild(s);
    }

    var io = new IntersectionObserver(function (entradas) {
      if (entradas[0].isIntersecting) { io.disconnect(); iniciar(); }
    }, { rootMargin: '300px 0px' });
    io.observe(sec);
  })();
</script>
@endpush
