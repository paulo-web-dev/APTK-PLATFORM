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
  .sobre-hero h1 { font-family: var(--font-display); font-size: clamp(2.4rem, 6vw, 4rem); color: var(--color-cream); margin: 14px 0 16px; max-width: 18ch; }
  .sobre-hero p { color: color-mix(in srgb, var(--color-cream) 84%, transparent); font-size: var(--text-lg); max-width: 600px; margin: 0; }

  /* ---- Sobre: história + linha do tempo ---- */
  .story-lead { max-width: 760px; }
  .story-lead p { color: var(--color-text-muted); font-size: var(--text-lg); line-height: 1.8; margin: 0; }
  .story-divider { color: var(--color-primary); width: 240px; margin: 36px 0; }
  .timeline { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
  .tl-card { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 32px; position: relative; }
  .tl-card .tl-year { font-family: var(--font-mono); font-size: var(--text-sm); letter-spacing: 0.16em; color: var(--color-primary); }
  .tl-card h3 { font-family: var(--font-display); font-size: var(--text-2xl); margin: 10px 0 12px; }
  .tl-card p { color: var(--color-text-muted); margin: 0; line-height: 1.7; }

  /* ---- Sobre: valores ---- */
  .values { background: var(--color-bg-card); border-block: 1px solid var(--color-border); }
  .values-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }
  .value { text-align: center; padding: 24px 18px; }
  .value .v-num { font-family: var(--font-mono); color: var(--color-primary); font-size: var(--text-sm); letter-spacing: 0.18em; }
  .value h4 { font-family: var(--font-display); font-size: var(--text-xl); margin: 12px 0 8px; }
  .value p { color: var(--color-text-muted); font-size: var(--text-sm); margin: 0; }

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

  /* ---- Quem Somos: as marcas da holding ---- */
  .brands { background: var(--color-bg-card); border-block: 1px solid var(--color-border); }
  .brands-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }
  .brand-box { background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 30px 26px; display: flex; flex-direction: column; }
  .brand-box .b-tag { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.14em; text-transform: uppercase; color: var(--color-primary); margin-bottom: 12px; }
  .brand-box h3 { font-family: var(--font-display); font-size: var(--text-2xl); margin: 0 0 10px; }
  .brand-box p { color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.7; margin: 0 0 22px; flex-grow: 1; }

  /* ---- Sobre: responsivo ---- */
  @media (max-width: 760px) {
    .timeline { grid-template-columns: 1fr; }
    .values-grid { grid-template-columns: 1fr; }
    .gallery { height: auto; grid-template-columns: 1fr; grid-template-rows: none; }
    .gallery .g-tall { grid-row: auto; }
    .gallery img { aspect-ratio: 4 / 3; height: auto; }
    .contact-inner { grid-template-columns: 1fr; gap: 28px; }
    .brands-grid { grid-template-columns: 1fr; }
  }
</style>
@endpush

@section('content')

  {{-- HERO --}}
  <section class="sobre-hero">
    <img src="{{ asset('img/aptk/sobre-loja.jpg') }}" alt="Loja física da APTK Spirits">
    <div class="container-aptk">
      <span class="eyebrow">Quem somos</span>
      <h1>Da Apothek à APTK</h1>
      <p>Alta coquetelaria engarrafada, com alma brasileira — drinks que dialogam com todos os sotaques e culturas do Brasil.</p>
    </div>
  </section>

  {{-- HISTÓRIA --}}
  <section class="section">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Nossa história</span>
        <h2 class="section-title">Começou com uma inquietação</h2>
      </div>

      <div class="story-lead">
        <p>Tudo começou em 2016, em São Paulo, com a <strong>Apothek Cocktails &amp; Co.</strong> — pioneira na alta coquetelaria engarrafada no Brasil. A vontade de transformar grandes drinks em algo para levar para casa virou método; e o método virou marca.</p>
      </div>

      <x-brand.divider class="story-divider" />

      <div class="timeline">
        <div class="tl-card">
          <span class="tl-year">2016 · São Paulo</span>
          <h3>Apothek Cocktails &amp; Co.</h3>
          <p>Nasce a pioneira em alta coquetelaria engarrafada, levando o balcão do bar para dentro de casa, com o mesmo padrão de quem prepara na hora.</p>
        </div>
        <div class="tl-card">
          <span class="tl-year">2021 · Brasil</span>
          <h3>APTK Spirits</h3>
          <p>A Apothek evolui para a APTK Spirits — marca de alma brasileira, reunindo alta coquetelaria, drinks prontos para consumo e uma linha de bases para criar a própria coquetelaria.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- VALORES --}}
  <section class="section values">
    <div class="container-aptk">
      <div class="section-head" style="margin-inline:auto; text-align:center;">
        <span class="eyebrow" style="justify-content:center;">No que acreditamos</span>
        <h2 class="section-title" style="display:inline-block;">Três valores, um padrão</h2>
      </div>
      <div class="values-grid">
        <div class="value">
          <span class="v-num">01</span>
          <h4>Qualidade</h4>
          <p>Cuidado em cada detalhe, do destilado ao rótulo.</p>
        </div>
        <div class="value">
          <span class="v-num">02</span>
          <h4>Autenticidade</h4>
          <p>Pontos de vista próprios e receitas autorais, sem atalho.</p>
        </div>
        <div class="value">
          <span class="v-num">03</span>
          <h4>Excelência</h4>
          <p>O padrão do balcão, engarrafado e sempre consistente.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- AS MARCAS DA HOLDING (conteúdo da antiga página "Marcas") --}}
  <section class="section brands">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">A holding</span>
        <h2 class="section-title">Uma casa, três marcas</h2>
        <p>A APTK Spirits reúne a coquetelaria autoral, a linha artesanal BARIN e a operação B2B da Ice4Pros — cada uma com o seu papel, o mesmo padrão.</p>
      </div>
      <div class="brands-grid">
        <div class="brand-box">
          <span class="b-tag">Coquetelaria</span>
          <h3>APTK Spirits</h3>
          <p>Alta coquetelaria engarrafada, drinks prontos e bases autorais — o coração da casa.</p>
          <a href="{{ route('catalog') }}" class="btn-aptk btn-aptk--outline">Ver a carta</a>
        </div>
        <div class="brand-box">
          <span class="b-tag">Artesanal</span>
          <h3>BARIN</h3>
          <p>A linha artesanal da holding, com receitas próprias e alma de bar.</p>
          <a href="{{ route('catalog', ['categoria' => 'barin']) }}" class="btn-aptk btn-aptk--outline">Ver a loja BARIN</a>
        </div>
        <div class="brand-box">
          <span class="b-tag">B2B</span>
          <h3>Ice4Pros</h3>
          <p>Gelo e insumos para o trade, com capacidade produtiva e qualidade certificada.</p>
          <a href="{{ route('pages.show', 'collabs') }}" class="btn-aptk btn-aptk--outline">Falar com o comercial</a>
        </div>
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
