{{-- QUEM SOMOS (leva 01) — funde a antiga "Sobre" com o conteúdo da
     antiga página "Marcas" (bloco "Uma casa, três marcas" abaixo).
     /sobre e /marcas redirecionam pra cá (routes/web.php). --}}
@extends('layouts.public')

@section('title', 'Quem Somos · APTK Spirits')
@section('meta_description', 'Da Apothek Cocktails & Co. (2016) à APTK Spirits (2021): alta coquetelaria engarrafada, com alma brasileira. Feito por humanos inquietos.')

{{-- Esta página tem fechamento próprio (contato), então escondemos a faixa institucional do layout. --}}
@section('hide_feature_band', '1')

@push('styles')
<style>
  /* ---- Sobre: hero (loja, escuro) ---- */
  .sobre-hero { position: relative; overflow: hidden; min-height: 56vh; display: flex; align-items: flex-end; border-bottom: 1px solid var(--color-border); }
  .sobre-hero img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
  .sobre-hero::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, color-mix(in srgb, var(--color-ink) 28%, transparent) 0%, color-mix(in srgb, var(--color-ink) 82%, transparent) 100%); }
  .sobre-hero .container-aptk { position: relative; z-index: 1; padding-block: 52px; }
  .sobre-hero .eyebrow { color: var(--color-cream); }
  .sobre-hero .eyebrow::before { background: var(--color-cream); }
  .sobre-hero h1 { font-size: clamp(2.6rem, 6.4vw, 4.4rem); color: var(--color-cream); margin: 14px 0 16px; max-width: 18ch; }
  .sobre-hero p { color: color-mix(in srgb, var(--color-cream) 84%, transparent); font-size: var(--text-lg); max-width: 600px; margin: 0; }

  /* ---- Quem Somos: a holding (leva 10) ---- */
  .qs-holding { border-bottom: 1px solid var(--color-border); }
  .qs-holding .section-head { max-width: 860px; }
  .qs-holding-text { max-width: 760px; display: flex; flex-direction: column; gap: 18px; color: var(--color-text-muted); line-height: 1.85; }
  .qs-holding-text p { margin: 0; }
  .qs-holding-text .qs-holding-lead { font-family: var(--font-editorial); font-size: clamp(1.2rem, 2.4vw, 1.5rem); line-height: 1.55; color: var(--color-text); }

  /* ---- Sobre: história + trajetória ---- */
  .story-lead { max-width: 760px; }
  .story-lead p { color: var(--color-text-muted); font-size: var(--text-lg); line-height: 1.8; margin: 0; }
  .qs-tl-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin: clamp(48px, 7vw, 80px) 0 24px; }
  .qs-tl-head h3 { font-size: clamp(1.6rem, 3.4vw, 2.2rem); margin: 12px 0 0; }
  .qs-tl-range { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.16em; color: var(--color-text-muted); white-space: nowrap; }
  .timeline { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
  .tl-card { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; display: flex; flex-direction: column; }
  .tl-img { display: block; width: 100%; aspect-ratio: 16 / 10; object-fit: cover; background: var(--color-bg-media); }
  .tl-body { padding: 26px 28px 30px; }
  .tl-card .tl-year { font-family: var(--font-editorial); font-size: var(--text-2xl); color: var(--color-primary); }
  .tl-card h3 { font-family: var(--font-display); font-size: var(--text-xl); margin: 10px 0 12px; }
  .tl-card p { color: var(--color-text-muted); margin: 0; line-height: 1.7; }

  /* ---- Quem Somos: no que acreditamos ---- */
  .values { background: var(--color-bg-card); border-block: 1px solid var(--color-border); }

  /* ---- Sobre: galeria ---- */
  .gallery { display: grid; grid-template-columns: 1fr 1.3fr; grid-template-rows: 1fr 1fr; gap: 16px; height: 520px; }
  .gallery img { width: 100%; height: 100%; object-fit: cover; border-radius: var(--radius-md); border: 1px solid var(--color-border); display: block; }
  .gallery .g-tall { grid-row: span 2; }

  /* ---- Sobre: onde encontrar ---- */
  .malls-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 22px; }
  .mall-city { border-top: 1px solid var(--color-primary-muted); padding-top: 16px; }
  .mall-city h4 { font-family: var(--font-display); font-size: var(--text-lg); margin: 0 0 12px; }
  .mall-city ul { margin: 0; padding: 0; list-style: none; }
  .mall-city li { color: var(--color-text-muted); font-size: var(--text-sm); padding: 4px 0; }

  /* ---- Sobre: contato (faixa escura de fechamento) ---- */
  .contact-band { background: var(--color-bg); }
  .contact-inner { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; align-items: center; }
  .contact-band .sun { width: 120px; color: var(--color-primary); margin-bottom: 18px; }
  .contact-band h2 { font-family: var(--font-display); font-size: clamp(1.8rem, 4vw, 2.6rem); color: var(--color-text); margin: 0 0 12px; }
  .contact-band p.lead { color: var(--color-text-muted); font-size: var(--text-lg); margin: 0 0 26px; max-width: 460px; }
  .contact-list { display: flex; flex-direction: column; gap: 14px; }
  .contact-list .ct-role { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.14em; text-transform: uppercase; color: var(--color-text-muted); margin-bottom: 2px; }
  .contact-list .ct-name { font-family: var(--font-display); font-size: var(--text-xl); color: var(--color-text); }
  .contact-list a { color: var(--color-text); display: inline-flex; align-items: center; gap: 10px; font-size: var(--text-base); transition: color .2s ease; }
  .contact-list a:hover { color: var(--color-primary); }
  .contact-list svg { width: 18px; height: 18px; color: var(--color-primary); flex-shrink: 0; }
  .contact-cta { margin-top: 30px; }

  /* ---- Quem Somos: ecossistema (cards com foto, leva 10) ---- */
  .brands { background: var(--color-bg-card); border-block: 1px solid var(--color-border); }
  .brands .section-head { max-width: 720px; }
  .qs-eco-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }
  .qs-eco-card { --mc-ratio: 4 / 5; background: var(--color-bg); }
  .qs-eco-card h3.title-editorial { font-size: var(--text-3xl); }
  .qs-eco-card .qs-eco-sub { font-family: var(--font-editorial); font-size: var(--text-base); color: var(--color-text); }
  /* Foto de produto (fundo preto 2:3): inteira, sem corte */
  .qs-eco-media--produto > img { object-fit: contain; padding: 6% 0; }

  /* ---- Sobre: responsivo ---- */
  @media (max-width: 760px) {
    .timeline { grid-template-columns: 1fr; }
    .qs-tl-head { flex-direction: column; align-items: flex-start; }
    .gallery { height: auto; grid-template-columns: 1fr; grid-template-rows: none; }
    .gallery .g-tall { grid-row: auto; }
    .gallery img { aspect-ratio: 4 / 3; height: auto; }
    .contact-inner { grid-template-columns: 1fr; gap: 28px; }
    .qs-eco-grid { grid-template-columns: 1fr; }
  }
</style>
@endpush

@section('content')

  {{-- A HOLDING — texto da Small Batches (leva 10, briefing p.1). Veio em
       caixa alta; publicado em caixa normal, como na leva 06 (caixa alta
       integral nesse volume prejudica leitura e acessibilidade). --}}
  <section class="section qs-holding">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">A holding</span>
        <h2 class="section-title title-editorial">Small Batches — um ecossistema de marcas, histórias e possibilidades.</h2>
      </div>
      <div class="qs-holding-text">
        <p class="qs-holding-lead">A Small Batches é uma holding brasileira que reúne marcas com um propósito em comum: transformar a maneira como as pessoas descobrem, consomem e se conectam com o universo da coquetelaria.</p>
        <p>Nosso ecossistema é formado por três marcas complementares: a APTK Spirits, referência em coquetéis engarrafados, gifting e projetos especiais; a Ice4Pros, especializada em gelo profissional de alta qualidade; e a Barín, desenvolvida para atender às necessidades de bares, restaurantes, eventos e operações de grande escala.</p>
        <p>Unimos conhecimento, técnica, criatividade e visão de negócio para desenvolver produtos e soluções que conectam qualidade, inovação e praticidade, sem abrir mão da autenticidade.</p>
        <p>Cada marca possui sua própria identidade, mas todas compartilham o mesmo compromisso com a excelência, a atenção aos detalhes e a valorização das relações humanas.</p>
        <p>Acreditamos que a coquetelaria vai muito além do que está dentro do copo. Ela está na escolha dos ingredientes, no cuidado com cada receita, na forma de servir e, principalmente, nas histórias que nascem dos encontros.</p>
        <p>Mais do que um grupo de marcas, somos um ecossistema que conecta pessoas, ideias e oportunidades, construindo novos caminhos para a coquetelaria e a hospitalidade no Brasil.</p>
      </div>
    </div>
  </section>

  {{-- HERO — citação do briefing (p.3) --}}
  <section class="sobre-hero">
    <img src="{{ asset('img/aptk/sobre-loja.jpg') }}" alt="Loja física da APTK Spirits">
    <div class="container-aptk">
      <span class="eyebrow">Quem somos</span>
      <h1 class="title-editorial">Da Apothek à APTK.</h1>
      <p>A evolução da alta coquetelaria brasileira, conectando tradição, criatividade e novas tendências de consumo em cada celebração.</p>
    </div>
  </section>

  {{-- HISTÓRIA + TRAJETÓRIA (briefing p.2–4) --}}
  <section class="section">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Nossa história</span>
        <h2 class="section-title title-editorial">Começou com uma inquietação</h2>
      </div>
      <div class="story-lead">
        <p>Em 2016, em São Paulo, nasceu a <strong>Apothek Cocktails &amp; Co.</strong>, pioneira na alta coquetelaria engarrafada no Brasil. Movida pelo desejo de reinventar a forma de consumir coquetéis, a marca antecipou tendências e rompeu com os formatos tradicionais, levando a sofisticação e a criatividade dos grandes bares para novas ocasiões de consumo. O que começou como uma ideia inovadora se transformou em uma marca que conecta tradição, inovação e novas formas de celebrar.</p>
      </div>

      <div class="qs-tl-head">
        <div>
          <span class="eyebrow">Nossa trajetória</span>
          <h3 class="title-editorial">Do pioneirismo à evolução.</h3>
        </div>
        <span class="qs-tl-range">2016 — 2021</span>
      </div>
      <div class="timeline">
        <article class="tl-card">
          {{-- FOTO: garrafa ou balcão da era Apothek Cocktails & Co. (2016). Provisória: Negroni em garrafas da casa. --}}
          <img class="tl-img" src="{{ asset('img/aptk/featured-negroni.jpg') }}" alt="Garrafas de Negroni da casa" loading="lazy">
          <div class="tl-body">
            <span class="tl-year">2016 · São Paulo</span>
            <h3>Apothek Cocktails &amp; Co.</h3>
            <p>Nasce a pioneira da alta coquetelaria engarrafada no Brasil. Uma marca que desafiou convenções, antecipou tendências e abriu caminho para uma nova forma de consumir coquetéis, levando a excelência dos grandes bares para além do balcão.</p>
          </div>
        </article>
        <article class="tl-card">
          <img class="tl-img" src="{{ asset('img/aptk/about-loja-2.jpg') }}" alt="Loja da APTK Spirits com a marca iluminada" loading="lazy">
          <div class="tl-body">
            <span class="tl-year">2021 · Brasil</span>
            <h3>APTK Spirits</h3>
            <p>O pioneirismo evolui e ganha uma nova identidade. A Apothek se transforma em APTK Spirits, ampliando seu universo com alma brasileira, inovação e criatividade. Uma marca que conecta a alta coquetelaria a novos estilos de vida, ocasiões de consumo e formas de celebrar.</p>
          </div>
        </article>
      </div>
    </div>
  </section>

  {{-- NO QUE ACREDITAMOS (briefing p.5–6) --}}
  <section class="section values">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">No que acreditamos</span>
        <h2 class="section-title title-editorial">Nossa essência. Nosso jeito de fazer.</h2>
      </div>
      <ol class="num-steps">
        <li>
          <span class="ns-num">01</span>
          <h3>Pioneirismo</h3>
          <p>Nascemos para desafiar o convencional. Antecipamos tendências, abrimos novos caminhos e seguimos transformando a maneira de consumir e celebrar a coquetelaria.</p>
        </li>
        <li>
          <span class="ns-num">02</span>
          <h3>Autenticidade</h3>
          <p>Acreditamos na criatividade sem fórmulas prontas. Com alma brasileira e olhar autoral, valorizamos nossas origens enquanto exploramos novas possibilidades.</p>
        </li>
        <li>
          <span class="ns-num">03</span>
          <h3>Excelência</h3>
          <p>Da escolha dos ingredientes à última gota, unimos técnica, inovação e atenção aos detalhes para levar o padrão da alta coquetelaria a qualquer ocasião.</p>
        </li>
      </ol>
    </div>
  </section>

  {{-- O ECOSSISTEMA (briefing p.7–8). Links em config/marcas.php ('links'):
       o briefing pede o Instagram de cada marca; Barín e Ice4Pros ainda
       apontam pra LP / site até chegarem os perfis. --}}
  @php
    $links = config('marcas.links');
    $ecossistema = [
      [
        'link'  => $links['aptk'],
        'img'   => \Illuminate\Support\Facades\Storage::url('products/negroni-classico-375ml-frente.jpg'),
        'alt'   => 'Garrafa de Negroni Clássico da APTK Spirits',
        'name'  => 'APTK Spirits',
        'sub'   => 'Alta coquetelaria. Alquimia de histórias.',
        'text'  => 'Pioneira na alta coquetelaria engarrafada no Brasil, une sofisticação, criatividade e autenticidade em coquetéis prontos para servir, gifting e projetos especiais.',
        'media' => 'qs-eco-media--produto',
      ],
      [
        'link'  => $links['barin'],
        // FOTO: garrafa ou serviço da Barín no balcão. Provisória: bartender da APTK.
        'img'   => asset('img/aptk/hero-bartender.jpg'),
        'alt'   => 'Bartender preparando um drink no balcão',
        'name'  => 'Barín',
        'sub'   => 'Qualidade e eficiência em cada serviço.',
        'text'  => 'Coquetelaria desenvolvida para o ritmo de bares, restaurantes e eventos. Receitas de qualidade, padronização e praticidade para operações que exigem consistência e escala.',
        'media' => '',
      ],
      [
        'link'  => $links['ice4pros'],
        'img'   => asset('img/aptk/ice4pros-cubo.jpg'),
        'alt'   => 'Cubo de gelo cristal Ice4Pros iluminado',
        'name'  => 'Ice4Pros',
        'sub'   => 'O gelo também é protagonista.',
        'text'  => 'Especialista em gelo profissional, combina precisão, tecnologia e qualidade para valorizar cada coquetel e atender às exigências da coquetelaria e da hospitalidade.',
        'media' => '',
      ],
    ];
  @endphp
  <section class="section brands">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">O ecossistema Small Batches</span>
        <h2 class="section-title title-editorial">Três marcas. Um universo de possibilidades.</h2>
        <p>A Small Batches reúne marcas que compartilham a paixão pela coquetelaria, pela inovação e pela excelência. Um ecossistema que conecta criatividade, conhecimento e capacidade produtiva para transformar diferentes momentos e ocasiões de consumo.</p>
      </div>
      <div class="qs-eco-grid">
        @foreach ($ecossistema as $marca)
          @php $externo = str_starts_with($marca['link']['url'], 'http'); @endphp
          <a href="{{ $marca['link']['url'] }}" class="media-card qs-eco-card" @if ($externo) target="_blank" rel="noopener noreferrer" @endif>
            <div class="mc-media {{ $marca['media'] }}">
              <img src="{{ $marca['img'] }}" alt="{{ $marca['alt'] }}" loading="lazy">
            </div>
            <div class="mc-body">
              <h3 class="title-editorial">{{ $marca['name'] }}</h3>
              <p class="qs-eco-sub">{{ $marca['sub'] }}</p>
              <p>{{ $marca['text'] }}</p>
              <span class="link-arrow">{{ $marca['link']['label'] }} <span class="btn-arrow">→</span></span>
            </div>
          </a>
        @endforeach
      </div>
    </div>
  </section>

  {{-- GALERIA --}}
  <section class="section">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">O universo APTK</span>
        <h2 class="section-title">Onde a alquimia acontece</h2>
      </div>
      <div class="gallery">
        <img class="g-tall" src="{{ asset('img/aptk/sobre-barman.jpg') }}" alt="Bartender da APTK preparando um drink">
        <img src="{{ asset('img/aptk/sobre-interior.jpg') }}" alt="Interior da loja física da APTK">
        <img src="{{ asset('img/aptk/sobre-pessoas.jpg') }}" alt="Pessoas brindando com drinks da APTK">
      </div>
    </div>
  </section>

  {{-- ONDE ENCONTRAR --}}
  <section class="section" style="padding-top:0;">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Onde encontrar</span>
        <h2 class="section-title">APTK pelo Brasil</h2>
        <p>Nossos pontos de venda e parceiros — do shopping ao bar de bairro.</p>
      </div>
      <div class="malls-grid">
        <div class="mall-city">
          <h4>São Paulo</h4>
          <ul>
            <li>Shops Jardins</li>
            <li>Shopping Cidade Jardim</li>
            <li>Shopping Eldorado</li>
            <li>Shopping Villa Lobos</li>
            <li>Shopping Pátio Paulista</li>
            <li>Vila Madalena</li>
            <li>Cidade Matarazzo</li>
            <li>The Copper Bar Brasil</li>
          </ul>
        </div>
        <div class="mall-city">
          <h4>Belo Horizonte</h4>
          <ul>
            <li>Botânico Shopping</li>
            <li>Braseiro da Colina</li>
          </ul>
        </div>
        <div class="mall-city">
          <h4>Rio de Janeiro</h4>
          <ul>
            <li>Shopping RioSul</li>
          </ul>
        </div>
        <div class="mall-city">
          <h4>Curitiba</h4>
          <ul>
            <li>Babilônia Gastronomia e Cia</li>
          </ul>
        </div>
        <div class="mall-city">
          <h4>Distrito Federal</h4>
          <ul>
            <li>Aeroporto Internacional de Brasília</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  {{-- CONTATO (fechamento escuro) --}}
  <section class="section section--dark contact-band">
    <div class="container-aptk">
      <div class="contact-inner">
        <div>
          <x-brand.sunburst class="sun" />
          <h2>Vamos criar histórias juntos</h2>
          <p class="lead">Bares, eventos, distribuição e parcerias — fale com a gente.</p>
          <a href="{{ route('catalog') }}" class="btn-aptk contact-cta">Ver a carta</a>
        </div>
        <div class="contact-list">
          <div>
            <p class="ct-role">Comercial · Head de Marketing</p>
            <p class="ct-name">Rafael Camperlingo</p>
          </div>
          <a href="mailto:rafael@aptkspirits.com">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
            rafael@aptkspirits.com
          </a>
          <a href="tel:+5511992788592">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.6a2 2 0 0 1-.5 2.1L8.1 9.5a16 16 0 0 0 6 6l1.1-1.1a2 2 0 0 1 2.1-.5c.8.3 1.7.5 2.6.6a2 2 0 0 1 1.7 2Z"/></svg>
            +55 11 99278-8592
          </a>
          <a href="https://www.instagram.com/aptkspirits/" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
            @aptkspirits
          </a>
        </div>
      </div>
    </div>
  </section>

@endsection
