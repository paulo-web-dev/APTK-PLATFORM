{{-- CLUBE APTK (leva 03) — pré-lançamento: página de CAPTAÇÃO DE INTERESSE.
     Por decisão do cliente, NÃO comunicar valores, planos, benefícios ou
     condições de adesão. A máquina de assinatura (planos, rotas, admin)
     segue DORMENTE no código, pronta pro lançamento oficial. --}}
@extends('layouts.public')

@section('title', 'Clube APTK — Em breve · APTK Spirits')
@section('meta_description', 'O Clube APTK está chegando. Deixe seu contato e seja o primeiro a saber — receba as novidades da coquetelaria engarrafada da casa em primeira mão.')

@push('styles')
<style>
  /* ---- Clube: LP de captação (dark) ---- */
  .clb-hero { position: relative; overflow: hidden; border-bottom: 1px solid var(--color-border); }
  .clb-hero::before { content: ""; position: absolute; top: -22%; left: 50%; transform: translateX(-50%); width: 640px; height: 640px; background: radial-gradient(circle, var(--gold-faint) 0%, transparent 62%); pointer-events: none; }
  .clb-hero .container-aptk { position: relative; z-index: 1; text-align: center; max-width: 780px; padding-block: clamp(56px, 8vw, 88px); display: flex; flex-direction: column; align-items: center; }
  .clb-hero .soon { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.2em; text-transform: uppercase; color: var(--color-primary); border: 1px solid var(--color-primary-muted); border-radius: 100px; padding: 8px 18px; margin-bottom: 22px; }
  .clb-hero .script-line { font-family: var(--font-script); font-size: clamp(1.7rem, 4vw, 2.5rem); color: var(--color-primary); margin-bottom: 8px; }
  .clb-hero h1 { font-size: clamp(2.2rem, 5.5vw, 3.4rem); letter-spacing: -0.01em; margin: 0 0 18px; }
  .clb-hero p.lead { font-size: var(--text-lg); color: var(--color-text-muted); line-height: 1.75; margin: 0; max-width: 600px; }

  .clb-concept { border-bottom: 1px solid var(--color-border); }
  .clb-concept .container-aptk { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; padding-block: clamp(40px, 6vw, 64px); }
  .clb-c-card { text-align: center; padding: 26px 20px; }
  .clb-c-card .ci { font-family: var(--font-script); font-size: var(--text-3xl); color: var(--color-primary); display: block; margin-bottom: 10px; }
  .clb-c-card h3 { font-family: var(--font-display); font-size: var(--text-lg); margin: 0 0 8px; }
  .clb-c-card p { color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.7; margin: 0; }

  /* Formulário de interesse */
  .clb-form-band { background: var(--color-bg-card); }
  .clb-form-band .container-aptk { max-width: 640px; padding-block: clamp(48px, 7vw, 72px); }
  .clb-form { background: var(--color-bg-elevated); border: 1px solid var(--color-primary-muted); border-radius: var(--radius-lg); padding: 34px; }
  .clb-form h2 { font-family: var(--font-display); font-size: var(--text-2xl); margin: 0 0 8px; text-align: center; }
  .clb-form > p { color: var(--color-text-muted); font-size: var(--text-sm); text-align: center; margin: 0 0 24px; }
  .clb-form label { display: block; font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-text-muted); margin-bottom: 8px; }
  .clb-form input { width: 100%; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text); padding: 12px 14px; font-family: var(--font-body); font-size: var(--text-sm); }
  .clb-form input:focus { outline: none; border-color: var(--color-primary); }
  .clb-form .f-row { margin-bottom: 18px; }
  .clb-form .f-err { font-size: var(--text-xs); color: var(--color-danger); margin-top: 6px; }
  .clb-ok { font-family: var(--font-mono); font-size: var(--text-sm); color: var(--color-success); border: 1px solid var(--color-border); border-left: 3px solid var(--color-success); border-radius: var(--radius-sm); padding: 14px 16px; margin-bottom: 22px; text-align: center; }
  .clb-privacy { font-size: var(--text-xs); color: var(--color-text-muted); text-align: center; margin-top: 14px; }

  @media (max-width: 860px) { .clb-concept .container-aptk { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')

  {{-- HERO --}}
  <section class="clb-hero">
    <div class="container-aptk">
      <span class="soon">Em breve</span>
      <p class="script-line">Clube APTK</p>
      <h1>O melhor da casa, primeiro pra quem é da casa</h1>
      <p class="lead">Estamos preparando um clube pra quem leva a bebida a sério: a atmosfera do balcão APTK, os lotes que não se repetem e as histórias por trás de cada garrafa — chegando até você antes de todo mundo.</p>
    </div>
  </section>

  {{-- CONCEITO (sem mecânica, valores ou benefícios) --}}
  <section class="clb-concept">
    <div class="container-aptk">
      <div class="clb-c-card">
        <span class="ci">a</span>
        <h3>Alquimia de histórias</h3>
        <p>Cada garrafa da casa carrega uma criação, um lote, uma história — e o clube nasce pra contar essas histórias primeiro.</p>
      </div>
      <div class="clb-c-card">
        <span class="ci">b</span>
        <h3>Feito por humanos inquietos</h3>
        <p>Coquetelaria autoral, engarrafada em pequenas quantidades, do jeito que só quem vive o balcão sabe fazer.</p>
      </div>
      <div class="clb-c-card">
        <span class="ci">c</span>
        <h3>Você, por dentro</h3>
        <p>Quem estiver na lista fica sabendo de tudo em primeira mão — do conceito ao lançamento oficial do clube.</p>
      </div>
    </div>
  </section>

  {{-- FORMULÁRIO DE INTERESSE --}}
  <section class="clb-form-band">
    <div class="container-aptk">
      <div class="clb-form">
        @if (session('clube_ok'))
          <div class="clb-ok">{{ session('clube_ok') }}</div>
        @endif

        <h2>Entre pra lista</h2>
        <p>Deixe seu contato e receba as novidades do Clube APTK em primeira mão.</p>

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
          <button type="submit" class="btn-aptk btn-aptk--block">Quero ser avisado</button>
          <p class="clb-privacy">Usamos seu contato só pra falar do Clube APTK. Sem spam.</p>
        </form>
      </div>
    </div>
  </section>

@endsection
