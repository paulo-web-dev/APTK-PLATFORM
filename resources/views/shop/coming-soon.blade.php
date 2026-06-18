@extends('layouts.public')

@section('title', $title . ' · APTK Spirits')

@push('styles')
<style>
    .cs { padding-block: clamp(64px, 12vw, 140px); text-align: center; max-width: 680px; margin: 0 auto; }
    .cs .eyebrow { display: block; margin-bottom: 18px; }
    .cs h1 { font-family: var(--font-display); font-size: clamp(2.2rem, 6vw, 3.4rem); color: var(--color-text); margin: 0 0 18px; }
    .cs p { color: var(--color-text-muted); font-size: var(--text-lg); line-height: 1.7; margin: 0 0 32px; }
    .cs-actions { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }
</style>
@endpush

@section('content')
<main>
  <section class="cs">
    <div class="container-aptk">
      <span class="eyebrow">Em breve</span>
      <h1>{{ $title }}</h1>
      <p>{{ $description }}</p>
      <div class="cs-actions">
        <a href="{{ route('catalog') }}" class="btn-aptk">Ir para a loja</a>
        <a href="{{ route('home') }}" class="btn-aptk btn-aptk--outline">Voltar ao início</a>
      </div>
    </div>
  </section>
</main>
@endsection
