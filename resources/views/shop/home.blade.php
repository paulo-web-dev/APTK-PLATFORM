@extends('layouts.public')

@section('title', 'APTK Spirits — Drinks & histórias pra contar')

@push('styles')
<style>
  /* ---- Home: hero retangular (texto à esquerda + vídeo à direita) ---- */
  .hero { position: relative; overflow: hidden; background: var(--color-ink); border-bottom: 1px solid var(--color-border); }
  .hero::before { content: ""; position: absolute; top: -30%; right: -8%; width: 560px; height: 560px; background: radial-gradient(circle, var(--gold-faint) 0%, transparent 65%); pointer-events: none; }
  .hero-inner { position: relative; z-index: 1; padding-block: clamp(40px, 6vw, 64px); display: grid; grid-template-columns: 1fr 1fr; gap: clamp(32px, 5vw, 64px); align-items: center; }
  .hero-copy { max-width: 560px; }
  .hero .script-line { font-family: var(--font-script); font-size: clamp(1.5rem, 3.5vw, var(--text-3xl)); color: var(--color-primary); margin-bottom: 12px; }
  .hero h1 { font-size: clamp(2rem, 4.5vw, 3.1rem); letter-spacing: -0.01em; color: var(--color-cream); margin-bottom: 20px; }
  .hero p { font-size: clamp(var(--text-base), 2vw, var(--text-lg)); color: color-mix(in srgb, var(--color-cream) 78%, transparent); max-width: 500px; margin: 0 0 30px; }
  .hero-ctas { display: flex; flex-wrap: wrap; gap: 14px; }
  .hero-meta { margin-top: 40px; display: flex; gap: 40px; flex-wrap: wrap; }
  .hero-meta div { display: flex; flex-direction: column; gap: 2px; }
  .hero-meta .num { font-family: var(--font-mono); font-size: var(--text-2xl); color: var(--color-primary); }
  .hero-meta .lbl { font-size: var(--text-xs); color: color-mix(in srgb, var(--color-cream) 65%, transparent); letter-spacing: 0.1em; text-transform: uppercase; }

  /* Vídeo do hero (Ale envasando). Formato retangular 16:10.
     Arquivo esperado: public/video/hero-negroni.mp4 — enquanto não chega,
     o poster + marcação seguram o lugar. */
  .hero-media { position: relative; }
  .hero-media video,
  .hero-media .video-placeholder { display: block; width: 100%; aspect-ratio: 16 / 10; object-fit: cover; border-radius: var(--radius-lg); border: 1px solid var(--color-border); box-shadow: var(--shadow-card); background: #0D0A06; }
  .hero-media .photo-tag { position: absolute; left: 16px; bottom: 16px; background: color-mix(in srgb, var(--color-ink) 70%, transparent); color: var(--color-cream); font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.14em; text-transform: uppercase; padding: 7px 12px; border-radius: var(--radius-sm); backdrop-filter: blur(4px); }

  /* ---- Home: carta de coquetéis (categorias) ---- */
  .category-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
  .category-card { padding: 0; display: flex; flex-direction: column; min-height: 200px; text-decoration: none; overflow: hidden; }
  .category-card .cat-img { display: block; width: 100%; height: 180px; object-fit: cover; }
  .category-card .cat-body { padding: 22px 24px 26px; display: flex; flex-direction: column; flex: 1; }
  .category-card h3 { font-family: var(--font-display); font-size: var(--text-xl); margin-bottom: 6px; transition: color .2s ease; }
  .category-card:hover h3 { color: var(--color-primary); }
  .category-card p { color: var(--color-text-muted); font-size: var(--text-sm); margin: 0; }
  .category-card .cat-link { margin-top: auto; padding-top: 16px; font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.14em; text-transform: uppercase; color: var(--color-primary); display: inline-flex; align-items: center; gap: 8px; }
  .category-card--brand { border-color: var(--color-primary-muted); }
  .category-card--brand h3 { color: var(--color-primary); }

  /* ---- Modal do Clube (área reservada — em breve) ---- */
  .clube-modal .modal-content { background: var(--color-bg-elevated); border: 1px solid var(--color-primary-muted); border-radius: var(--radius-lg); color: var(--color-text); text-align: center; padding: 12px 8px 20px; }
  .clube-modal .modal-header { border: none; padding-bottom: 0; }
  .clube-modal .btn-close { filter: invert(0.5); }
  .clube-modal .cm-sun { width: 96px; color: var(--color-primary); margin: 0 auto 14px; }
  .clube-modal h3 { font-family: var(--font-display); font-size: var(--text-2xl); margin: 0 0 6px; }
  .clube-modal .cm-soon { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.16em; text-transform: uppercase; color: var(--color-primary); display: inline-block; margin-bottom: 14px; }
  .clube-modal p { color: var(--color-text-muted); margin: 0 0 24px; }
  .clube-modal .cm-ctas { display: flex; flex-direction: column; gap: 10px; max-width: 320px; margin-inline: auto; }
  .clube-modal .cm-hint { font-size: var(--text-xs); color: var(--color-text-muted); margin-top: 6px; }

  /* ---- Home: produto em destaque (retangular, imagem à esquerda) ---- */
  .featured-band { background: var(--color-ink); border-block: 1px solid var(--color-border); }
  .featured-grid { display: grid; grid-template-columns: minmax(280px, 420px) 1fr; gap: clamp(32px, 5vw, 64px); align-items: center; padding-block: clamp(36px, 5vw, 56px); }
  .featured-img { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; object-position: center; border-radius: var(--radius-lg); border: 1px solid var(--color-border); box-shadow: var(--shadow-card); background: #0D0A06; }
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

  /* ---- Home: carrossel promocional (lojas, collabs, custom, clube) ---- */
  .promo-band { border-block: 1px solid var(--color-border); background: var(--color-bg-card); }
  .promo-slide { display: grid; grid-template-columns: 1fr minmax(280px, 480px); gap: clamp(28px, 5vw, 56px); align-items: center; padding-block: clamp(36px, 5vw, 56px); }
  .promo-copy { max-width: 520px; }
  .promo-copy .eyebrow { margin-bottom: 14px; }
  .promo-copy h2 { font-family: var(--font-display); font-size: clamp(1.8rem, 4vw, 2.6rem); margin: 0 0 14px; }
  .promo-copy p { color: var(--color-text-muted); font-size: var(--text-base); line-height: 1.7; margin: 0 0 26px; }
  .promo-media { position: relative; }
  .promo-media img { display: block; width: 100%; aspect-ratio: 16 / 10; object-fit: cover; border-radius: var(--radius-lg); border: 1px solid var(--color-border); box-shadow: var(--shadow-card); }
  .promo-media .photo-tag { position: absolute; left: 14px; bottom: 14px; background: color-mix(in srgb, var(--color-ink) 70%, transparent); color: var(--color-cream); font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.14em; text-transform: uppercase; padding: 6px 10px; border-radius: var(--radius-sm); backdrop-filter: blur(4px); }
  .promo-band .carousel-indicators { position: static; margin: 0 0 28px; }
  .promo-band .carousel-indicators [data-bs-target] { width: 34px; height: 3px; border-radius: 2px; background: var(--color-border); border: none; opacity: 1; transition: background-color .2s ease; }
  .promo-band .carousel-indicators .active { background: var(--color-primary); }
  .promo-band .carousel-control-prev, .promo-band .carousel-control-next { width: 44px; height: 44px; top: 50%; transform: translateY(-50%); background: var(--color-bg-elevated); border: 1px solid var(--color-border); border-radius: 50%; opacity: 1; color: var(--color-text); }
  .promo-band .carousel-control-prev { left: -8px; }
  .promo-band .carousel-control-next { right: -8px; }
  .promo-band .carousel-control-prev-icon, .promo-band .carousel-control-next-icon { filter: invert(0.4) sepia(0.2); width: 18px; height: 18px; }

  /* ---- Home: clube (teaser) ---- */
  .club { background: var(--color-bg-card); border-block: 1px solid var(--color-border); }


  /* ---- Home: animação de entrada do hero ---- */
  @keyframes riseIn { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
  .hero-copy > *, .hero-media { animation: riseIn .7s ease both; }
  .hero-copy > *:nth-child(2) { animation-delay: .08s; }
  .hero-copy > *:nth-child(3) { animation-delay: .16s; }
  .hero-copy > *:nth-child(4) { animation-delay: .24s; }
  .hero-media { animation-delay: .18s; }

  /* ---- Home: responsivo ---- */
  @media (max-width: 980px) {
    .hero-inner { grid-template-columns: 1fr; gap: 32px; }
    .featured-grid { grid-template-columns: 1fr; gap: 28px; }
    .promo-slide { grid-template-columns: 1fr; gap: 24px; }
    .promo-media { order: -1; }
    .promo-band .carousel-control-prev, .promo-band .carousel-control-next { display: none; }
  }
  @media (max-width: 820px) {
    .category-grid { grid-template-columns: repeat(2, 1fr); }
    .hero-meta { gap: 28px; }
  }
  @media (max-width: 520px) {
    .category-grid { grid-template-columns: 1fr; }
    .hero-ctas .btn-aptk { width: 100%; }
  }
</style>
@endpush

@section('content')

  {{-- HERO — retangular: texto à esquerda, vídeo à direita (leva 01) --}}
  <section class="hero on-dark">
    <div class="container-aptk">
      <div class="hero-inner">
        <div class="hero-copy">
          <p class="script-line">Bem vindo</p>
          <h1>Praticidade com alta coquetelaria engarrafada em pequenas quantidades.</h1>
          <p>Dos clássicos aos drinks autorais, bases e edições limitadas, um lugar perfeito para quem leva bebida a sério.</p>
          <div class="hero-ctas">
            <a href="{{ route('catalog') }}" class="btn-aptk">Comprar agora</a>
            <a href="{{ route('pages.show', 'clube') }}" class="btn-aptk btn-aptk--outline">Seja membro</a>
          </div>
          {{-- Infos numéricas PROVISÓRIAS (cliente ainda decidindo — manter o asterisco). --}}
          <div class="hero-meta">
            <div><span class="num">03</span><span class="lbl">Marcas na holding*</span></div>
            <div><span class="num">100%</span><span class="lbl">Produção nacional*</span></div>
          </div>
        </div>
        <div class="hero-media">
          {{-- Vídeo do Negroni (leva 02). Soltar o arquivo
               "APTK - NEGRONI.mp4" renomeado para
               public/video/hero-negroni.mp4 e ele entra no lugar do poster. --}}
          <video autoplay muted loop playsinline poster="{{ asset('img/aptk/hero-bartender.jpg') }}">
            <source src="{{ asset('video/hero-negroni.mp4') }}" type="video/mp4">
          </video>
          <span class="photo-tag">Negroni — no balcão APTK</span>
        </div>
      </div>
    </div>
  </section>

  {{-- CARTA DE COQUETÉIS (categorias — nomes da leva 01) --}}
  <section class="section">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Carta de Coquetéis</span>
        <h2 class="section-title">O que você procura</h2>
        <p>Do drink pronto para servir aos lotes limitados que saem só uma vez.</p>
      </div>
      <div class="category-grid">
        <a href="{{ route('catalog', ['categoria' => 'classicos']) }}" class="card-aptk category-card">
          <img src="{{ asset('img/aptk/cat-dry-martini.jpg') }}" class="cat-img" alt="Dry Martini servido" loading="lazy">
          <div class="cat-body">
            <h3>Coquetéis</h3>
            <p>Negroni, dry martini e clássicos, prontos para servir.</p>
            <span class="cat-link">Ver categoria →</span>
          </div>
        </a>
        <a href="{{ route('catalog', ['categoria' => 'bases']) }}" class="card-aptk category-card">
          <img src="{{ asset('img/aptk/cat-fitzgerald.jpg') }}" class="cat-img" alt="Garrafa de gin da APTK" loading="lazy">
          <div class="cat-body">
            <h3>Bases</h3>
            <p>Nossas bases autorais para criar em casa.</p>
            <span class="cat-link">Ver categoria →</span>
          </div>
        </a>
        <a href="{{ route('catalog') }}" class="card-aptk category-card">
          <img src="{{ asset('img/aptk/cat-evento.jpg') }}" class="cat-img" alt="Mesa posta com drinks em um evento APTK" loading="lazy">
          <div class="cat-body">
            <h3>Kits &amp; Presentes</h3>
            <p>Combos curados com embalagem especial.</p>
            <span class="cat-link">Ver categoria →</span>
          </div>
        </a>
        {{-- O CLUBE — abre o pop-up da área reservada (em breve) --}}
        <a href="#" class="card-aptk category-card" data-bs-toggle="modal" data-bs-target="#clubeModal">
          <img src="{{ asset('img/aptk/cat-cosmopolitan.jpg') }}" class="cat-img" alt="Drink autoral servido ao lado da garrafa" loading="lazy">
          <div class="cat-body">
            <h3>O Clube</h3>
            <p>Edições limitadas e lotes que não se repetem.</p>
            <span class="cat-link">Área reservada →</span>
          </div>
        </a>
        {{-- Leva 03: as marcas abrem a LP institucional; a loja fica pro CTA da LP. --}}
        <a href="{{ route('barin') }}" class="card-aptk category-card category-card--brand">
          <img src="{{ asset('img/aptk/cat-pessoas.jpg') }}" class="cat-img" alt="Pessoas brindando com drinks da APTK" loading="lazy">
          <div class="cat-body">
            <h3>BARIN</h3>
            <p>As marcas da holding, num só lugar.</p>
            <span class="cat-link">Ver categoria →</span>
          </div>
        </a>
        <a href="{{ route('ice4pros') }}" class="card-aptk category-card category-card--brand">
          <img src="{{ asset('img/aptk/cat-loja.jpg') }}" class="cat-img" alt="Balcão da loja física da APTK" loading="lazy">
          <div class="cat-body">
            <h3>ICE4PROS</h3>
            <p>Coqueteleira, copos e linha para empresas.</p>
            <span class="cat-link">Ver categoria →</span>
          </div>
        </a>
      </div>
    </div>
  </section>

  {{-- MODAL — Área Reservada do Clube (em breve) --}}
  <div class="modal fade clube-modal" id="clubeModal" tabindex="-1" aria-labelledby="clubeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <x-brand.sunburst class="cm-sun" />
          <h3 id="clubeModalLabel">Área Reservada para os membros do Clube APTK</h3>
          <span class="cm-soon">Em breve</span>
          <p>Faça parte dessa atmosfera e saboreie o melhor da coquetelaria nacional.</p>
          <div class="cm-ctas">
            {{-- Leva 03 — pré-lançamento: só captação de interesse, sem mecânica. --}}
            <a href="{{ route('pages.show', 'clube') }}" class="btn-aptk btn-aptk--block">Quero receber novidades</a>
            <p class="cm-hint">Deixe seu contato e saiba tudo em primeira mão.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- PRODUTO EM DESTAQUE — retangular, imagem à esquerda + seletor de volume --}}
  @if ($featured)
  @php
    $ftOptions = $featured->sizeOptions();
    $ftDefault = $featured->defaultSize();
  @endphp
  <section class="featured-band on-dark">
    <div class="container-aptk">
      <div class="featured-grid">
        @if ($featured->primaryImage)
          <img src="{{ \Illuminate\Support\Facades\Storage::url($featured->primaryImage->path) }}" class="featured-img" alt="{{ $featured->name }}" loading="lazy">
        @else
          {{-- Imagem do destaque ainda não cadastrada — marcação provisória. --}}
          <img src="{{ asset('img/aptk/featured-negroni.jpg') }}" class="featured-img" alt="{{ $featured->name }}" loading="lazy">
        @endif
        <div class="featured-body">
          <span class="badge-aptk">{{ $featured->category?->name ?? 'Destaque' }}</span>
          <p class="product-name">{{ $featured->name }}</p>
          @if ($featured->short_description)
            <p class="sub">{{ $featured->short_description }}</p>
          @endif
          @if ($featured->description)
            <p class="desc">{{ \Illuminate\Support\Str::limit($featured->description, 160) }}</p>
          @endif

          <div class="featured-specs">
            @if ($featured->abv)
              <div><span class="k">Teor</span><span class="v">{{ $featured->abv }}% vol.</span></div>
            @endif
            @if (count($ftOptions))
              <div>
                <span class="k">Volume</span>
                {{-- O cliente escolhe o ML e o valor muda automaticamente. --}}
                <div class="size-picker" id="ftSizePicker">
                  @foreach ($ftOptions as $size => $sizePrice)
                    <button type="button"
                            class="size-chip-btn {{ $size === $ftDefault ? 'is-active' : '' }}"
                            data-size="{{ $size }}"
                            data-price="{{ number_format($sizePrice, 2, ',', '.') }}">{{ $size }}</button>
                  @endforeach
                </div>
              </div>
            @endif
          </div>

          <div class="featured-buy">
            <span class="price-tag" id="ftPrice">R$ {{ number_format($featured->priceForSize($ftDefault), 2, ',', '.') }}</span>
            <form action="{{ route('cart.add') }}" method="POST" style="display:inline;">
              @csrf
              <input type="hidden" name="product_id" value="{{ $featured->id }}">
              <input type="hidden" name="size" id="ftSizeInput" value="{{ $ftDefault }}">
              <button type="submit" class="btn-aptk">Comprar</button>
            </form>
            <a href="{{ route('product', $featured->slug) }}" class="btn-aptk btn-aptk--outline">Ver detalhes</a>
          </div>
        </div>
      </div>
    </div>
  </section>
  @endif

  {{-- CARROSSEL PROMOCIONAL — lojas · collabs · custom · clube (leva 01)
       Imagens provisórias com marcação — trocar quando o cliente enviar. --}}
  <section class="section promo-band">
    <div class="container-aptk">
      <div id="promoCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="6000">
        <div class="carousel-indicators">
          <button type="button" data-bs-target="#promoCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Lojas"></button>
          <button type="button" data-bs-target="#promoCarousel" data-bs-slide-to="1" aria-label="Collabs"></button>
          <button type="button" data-bs-target="#promoCarousel" data-bs-slide-to="2" aria-label="Custom"></button>
          <button type="button" data-bs-target="#promoCarousel" data-bs-slide-to="3" aria-label="Clube"></button>
        </div>
        <div class="carousel-inner">
          <div class="carousel-item active">
            <div class="promo-slide">
              <div class="promo-copy">
                <span class="eyebrow">Flagship</span>
                <h2>Onde a alquimia acontece</h2>
                <p>Visite o balcão APTK: drinks engarrafados, edições limitadas e a curadoria completa da casa — para levar para casa ou viver ali mesmo.</p>
                <a href="{{ route('pages.show', 'quem-somos') }}" class="btn-aptk">Visite nossas lojas</a>
              </div>
              <div class="promo-media">
                <img src="{{ asset('img/aptk/loja-aptk.jpg') }}" alt="Loja física da APTK Spirits" loading="lazy">
                <span class="photo-tag">Imagem provisória</span>
              </div>
            </div>
          </div>
          <div class="carousel-item">
            <div class="promo-slide">
              <div class="promo-copy">
                <span class="eyebrow">Collabs</span>
                <h2>Criações assinadas com quem a gente admira</h2>
                <p>Do Limoncello Braz ao Gin Gurumê: produtos exclusivos criados em parceria com bares, hotéis e marcas de todo o Brasil.</p>
                <a href="{{ route('pages.show', 'collabs') }}" class="btn-aptk">Ver as collabs</a>
              </div>
              <div class="promo-media">
                <img src="{{ asset('img/aptk/parceiros-hero.jpg') }}" alt="Collabs da APTK com o trade" loading="lazy">
                <span class="photo-tag">Imagem provisória</span>
              </div>
            </div>
          </div>
          <div class="carousel-item">
            <div class="promo-slide">
              <div class="promo-copy">
                <span class="eyebrow">Custom</span>
                <h2>Sua marca na nossa garrafa</h2>
                <p>Casamentos, presentes corporativos e datas especiais: personalizamos o rótulo e a curadoria do drink, do lote pequeno ao grande volume.</p>
                <a href="{{ route('custom') }}" class="btn-aptk">Personalizar rótulo</a>
              </div>
              <div class="promo-media">
                <img src="{{ asset('img/aptk/featured-negroni.jpg') }}" alt="Garrafa personalizada da APTK" loading="lazy">
                <span class="photo-tag">Imagem provisória</span>
              </div>
            </div>
          </div>
          <div class="carousel-item">
            <div class="promo-slide">
              <div class="promo-copy">
                <span class="eyebrow">Clube APTK · em breve</span>
                <h2>O melhor da casa, primeiro pra quem é da casa</h2>
                <p>Estamos preparando o Clube APTK. Entre na lista e receba as novidades em primeira mão — do conceito ao lançamento.</p>
                <a href="{{ route('pages.show', 'clube') }}" class="btn-aptk">Entrar na lista</a>
              </div>
              <div class="promo-media">
                <img src="{{ asset('img/aptk/clube-hero.jpg') }}" alt="Experiência do Clube APTK" loading="lazy">
                <span class="photo-tag">Imagem provisória</span>
              </div>
            </div>
          </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#promoCarousel" data-bs-slide="prev">
          <span class="carousel-control-prev-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Anterior</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#promoCarousel" data-bs-slide="next">
          <span class="carousel-control-next-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Próximo</span>
        </button>
      </div>
    </div>
  </section>

  {{-- Seção "Uma holding, três mundos" REMOVIDA (leva 01). --}}

  {{-- CLUBE (leva 03 — pré-lançamento): a vitrine de planos SAIU por decisão
       do cliente (sem valores/planos/benefícios). Teaser de captação abaixo;
       a máquina de assinatura segue dormente no código pro lançamento. --}}
  <section class="section club">
    <div class="container-aptk" style="max-width: 780px; text-align: center;">
      <div class="section-head" style="margin-bottom: 22px;">
        <span class="eyebrow">Clube APTK · em breve</span>
        <h2 class="section-title">Um clube pra quem leva a bebida a sério</h2>
        <p>A atmosfera do balcão, os lotes que não se repetem e as histórias da casa — chegando primeiro pra quem estiver na lista.</p>
      </div>
      <a href="{{ route('pages.show', 'clube') }}" class="btn-aptk">Quero ser avisado</a>
    </div>
  </section>

@endsection

@push('scripts')
<script>
  // Destaque da home: escolher o volume atualiza preço + input do carrinho.
  (function () {
    var picker = document.getElementById('ftSizePicker');
    if (!picker) return;
    var priceEl = document.getElementById('ftPrice');
    var sizeInput = document.getElementById('ftSizeInput');
    picker.querySelectorAll('.size-chip-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        picker.querySelectorAll('.size-chip-btn').forEach(function (b) { b.classList.remove('is-active'); });
        btn.classList.add('is-active');
        priceEl.textContent = 'R$ ' + btn.getAttribute('data-price');
        sizeInput.value = btn.getAttribute('data-size');
      });
    });
  })();
</script>
@endpush
