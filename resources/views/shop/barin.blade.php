{{-- LP BARÍN (leva 03) — COPY PROVISÓRIO no tom do brandbook, marcado para
     ajuste pelo cliente. Estrutura pedida: Sobre · Posicionamento ·
     Diferenciais · Portfólio · Aplicações · Clientes · CTA produtos.
     Fotos: placeholders marcados até o material chegar. --}}
@extends('layouts.public')

@section('title', 'Barín — A linha artesanal da holding · APTK Spirits')
@section('meta_description', 'Barín: bebidas artesanais com receitas próprias e identidade de bar. Conheça a marca artesanal da holding APTK Spirits.')

@push('styles')
<style>
  /* ---- LP Barín (dark) ---- */
  .brn-hero { position: relative; overflow: hidden; border-bottom: 1px solid var(--color-border); }
  .brn-hero::before { content: ""; position: absolute; top: -18%; right: -8%; width: 520px; height: 520px; background: radial-gradient(circle, var(--gold-faint) 0%, transparent 65%); pointer-events: none; }
  .brn-hero .container-aptk { position: relative; z-index: 1; text-align: center; max-width: 820px; padding-block: clamp(56px, 8vw, 88px); display: flex; flex-direction: column; align-items: center; }
  .brn-hero .script-line { font-family: var(--font-script); font-size: clamp(1.6rem, 4vw, 2.4rem); color: var(--color-primary); margin-bottom: 10px; }
  .brn-hero h1 { font-size: clamp(2.2rem, 5.5vw, 3.5rem); letter-spacing: -0.01em; margin: 0 0 18px; }
  .brn-hero p.lead { font-size: var(--text-lg); color: var(--color-text-muted); line-height: 1.7; margin: 0 0 30px; max-width: 620px; }
  .brn-hero .ctas { display: flex; gap: 14px; flex-wrap: wrap; justify-content: center; }

  .brn-split .container-aptk { display: grid; grid-template-columns: 1fr 1fr; gap: clamp(32px, 5vw, 64px); align-items: center; }
  .brn-split p { color: var(--color-text-muted); line-height: 1.8; margin: 0 0 16px; }
  .brn-ph { aspect-ratio: 4 / 3; border: 1px solid var(--color-border); border-radius: var(--radius-lg); display: grid; place-items: center; background: repeating-linear-gradient(45deg, var(--color-bg-card), var(--color-bg-card) 12px, var(--color-bg) 12px, var(--color-bg) 24px); color: var(--color-text-muted); font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.14em; text-transform: uppercase; text-align: center; padding: 10px; }

  .band-card { background: var(--color-bg-card); border-block: 1px solid var(--color-border); }
  .brn-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
  .brn-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
  .brn-card { border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 28px 24px; background: var(--color-bg); transition: border-color .25s ease, transform .25s ease; }
  .brn-card:hover { border-color: var(--color-primary-muted); transform: translateY(-3px); }
  .brn-card .k { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.14em; text-transform: uppercase; color: var(--color-primary); }
  .brn-card h3 { font-family: var(--font-display); font-size: var(--text-xl); margin: 12px 0 8px; }
  .brn-card p { color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.7; margin: 0; }

  .brn-port { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
  .brn-port .port-item { border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; background: var(--color-bg); }
  .brn-port .port-ph { aspect-ratio: 4 / 5; display: grid; place-items: center; background: repeating-linear-gradient(45deg, var(--color-bg-card), var(--color-bg-card) 12px, var(--color-bg) 12px, var(--color-bg) 24px); color: var(--color-text-muted); font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.14em; text-transform: uppercase; text-align: center; padding: 10px; border-bottom: 1px solid var(--color-border); }
  .brn-port h3 { font-family: var(--font-display); font-size: var(--text-lg); margin: 0; padding: 16px 18px; }

  .cli-strip { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
  .cli-chip { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-text-muted); border: 1px solid var(--color-border); border-radius: 100px; padding: 9px 18px; }

  .brn-closing { text-align: center; border-top: 1px solid var(--color-border); }
  .brn-closing .container-aptk { max-width: 720px; display: flex; flex-direction: column; align-items: center; padding-block: clamp(48px, 7vw, 72px); }
  .brn-closing h2 { font-family: var(--font-display); font-size: clamp(1.9rem, 5vw, 2.8rem); margin: 0 0 14px; }
  .brn-closing p { color: var(--color-text-muted); font-size: var(--text-lg); margin: 0 0 28px; }

  @media (max-width: 980px) {
    .brn-split .container-aptk { grid-template-columns: 1fr; }
    .brn-grid-3, .brn-port { grid-template-columns: 1fr; }
    .brn-grid-4 { grid-template-columns: repeat(2, 1fr); }
  }
</style>
@endpush

@section('content')

  {{-- HERO --}}
  <section class="brn-hero">
    <div class="container-aptk">
      <p class="script-line">Barín</p>
      <h1>A alma artesanal da holding</h1>
      {{-- COPY PROVISÓRIO — ajustar com o material oficial da Barín. --}}
      <p class="lead">Receitas próprias, produção em pequena escala e identidade de bar em cada garrafa. A Barín é onde a coquetelaria da casa encontra o feito à mão.</p>
      <div class="ctas">
        <a href="{{ route('catalog', ['categoria' => 'barin']) }}" class="btn-aptk">Conheça os produtos</a>
        <a href="#sobre" class="btn-aptk btn-aptk--outline">Sobre a Barín</a>
      </div>
    </div>
  </section>

  {{-- SOBRE --}}
  <section class="section brn-split" id="sobre">
    <div class="container-aptk">
      <div>
        <span class="eyebrow">Sobre a Barín</span>
        <h2 class="section-title">Feita no ritmo do balcão</h2>
        {{-- COPY PROVISÓRIO --}}
        <p>A Barín nasceu dentro do bar — das receitas que os bartenders da casa criavam pra si, dos lotes pequenos que acabavam antes da semana, do jeito artesanal de fazer bebida quando ninguém está com pressa.</p>
        <p>Hoje é a linha artesanal da holding APTK: destilados e preparos com receitas próprias, produção controlada e a assinatura de quem serviu cada versão no balcão antes de engarrafar.</p>
      </div>
      <div class="brn-ph"><span>Foto — produção artesanal Barín<br>em breve</span></div>
    </div>
  </section>

  {{-- POSICIONAMENTO --}}
  <section class="section band-card">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Posicionamento</span>
        <h2 class="section-title">Artesanal de verdade, sem cerimônia</h2>
        {{-- COPY PROVISÓRIO --}}
        <p>Entre o industrializado e o inacessível existe a Barín: bebida de produção pequena, honesta no rótulo e generosa no copo — pra quem valoriza o feito à mão sem abrir mão do dia a dia.</p>
      </div>
    </div>
  </section>

  {{-- DIFERENCIAIS --}}
  <section class="section">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Diferenciais</span>
        <h2 class="section-title">O que faz uma Barín ser Barín</h2>
      </div>
      <div class="brn-grid-3">
        <div class="brn-card">
          <span class="k">01</span>
          <h3>Receitas próprias</h3>
          <p>Criações da casa, testadas no balcão antes de qualquer garrafa — nada de fórmula de prateleira.</p>
        </div>
        <div class="brn-card">
          <span class="k">02</span>
          <h3>Pequena escala</h3>
          <p>Lotes controlados, produção acompanhada de perto e consistência de bar de assinatura.</p>
        </div>
        <div class="brn-card">
          <span class="k">03</span>
          <h3>Identidade de bar</h3>
          <p>Rótulo, líquido e serviço pensados por quem vive coquetelaria — da holding APTK Spirits.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- PORTFÓLIO --}}
  <section class="section band-card">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Portfólio</span>
        <h2 class="section-title">A linha Barín</h2>
        <p>Fotos e nomes oficiais entram assim que o material da marca chegar.</p>
      </div>
      <div class="brn-port">
        {{-- Placeholders — trocar por produtos reais da linha Barín. --}}
        <div class="port-item"><div class="port-ph"><span>Foto do produto<br>em breve</span></div><h3>Linha destilados</h3></div>
        <div class="port-item"><div class="port-ph"><span>Foto do produto<br>em breve</span></div><h3>Linha preparos</h3></div>
        <div class="port-item"><div class="port-ph"><span>Foto do produto<br>em breve</span></div><h3>Edições da casa</h3></div>
      </div>
    </div>
  </section>

  {{-- APLICAÇÕES --}}
  <section class="section">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Aplicações</span>
        <h2 class="section-title">Onde a Barín entra</h2>
      </div>
      <div class="brn-grid-4">
        <div class="brn-card"><span class="k">Bar</span><h3>Carta de drinks</h3><p>Base artesanal pra coquetéis de assinatura no seu cardápio.</p></div>
        <div class="brn-card"><span class="k">Casa</span><h3>Coquetelaria caseira</h3><p>O feito à mão pra criar em casa, do clássico ao autoral.</p></div>
        <div class="brn-card"><span class="k">Presente</span><h3>Presentes & kits</h3><p>Garrafas com história pra ocasiões que merecem.</p></div>
        <div class="brn-card"><span class="k">Trade</span><h3>Revenda</h3><p>Condições de revenda e abastecimento via holding APTK.</p></div>
      </div>
    </div>
  </section>

  {{-- CLIENTES --}}
  <section class="section band-card">
    <div class="container-aptk">
      <div class="section-head">
        <span class="eyebrow">Clientes</span>
        <h2 class="section-title">Quem já serve Barín</h2>
        <p>Lista oficial em curadoria — os nomes entram com o material da marca.</p>
      </div>
      <div class="cli-strip">
        {{-- Placeholders — substituir pelos clientes reais da Barín. --}}
        <span class="cli-chip">Cliente Barín</span>
        <span class="cli-chip">Cliente Barín</span>
        <span class="cli-chip">Cliente Barín</span>
        <span class="cli-chip">Cliente Barín</span>
      </div>
    </div>
  </section>

  {{-- CTA FINAL --}}
  <section class="brn-closing">
    <div class="container-aptk">
      <x-brand.sunburst style="width:110px; color:var(--color-primary); margin-bottom:18px;" />
      <h2>Prove o artesanal da casa</h2>
      <p>A linha Barín está na loja APTK — engarrafada, pronta e com a assinatura do balcão.</p>
      <div style="display:flex; gap:14px; flex-wrap:wrap; justify-content:center;">
        <a href="{{ route('catalog', ['categoria' => 'barin']) }}" class="btn-aptk">Conheça os produtos</a>
        <a href="{{ route('pages.show', 'collabs') }}" class="btn-aptk btn-aptk--outline">Revenda e parcerias</a>
      </div>
    </div>
  </section>

@endsection
