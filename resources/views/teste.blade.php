@extends('layouts.app')

@section('title', 'Home de teste')

@push('styles')
<style>
    .test-wrap {
        min-height: 100vh;
        display: flex; align-items: center; justify-content: center;
        padding: var(--s-7);
        background: var(--grad-vignette), var(--bg-0);
    }
    .test-card { max-width: 560px; width: 100%; padding: var(--s-8); text-align: center; }
    .test-card h1 { font-size: 32px; margin: var(--s-5) 0 var(--s-3); }
    .test-card .lead { color: var(--fg-3); margin-bottom: var(--s-6); }
    .disc-legend {
        display: flex; gap: var(--s-5); justify-content: center;
        flex-wrap: wrap; margin: var(--s-6) 0;
    }
    .disc-legend span { display: inline-flex; align-items: center; gap: var(--s-2); font-weight: 600; }
    .ok { color: var(--success); font-weight: 600; margin-top: var(--s-7); }
</style>
@endpush

@section('content')
<div class="test-wrap">
    <div class="card test-card animate-fadeUp">
        <div class="brand-lockup" style="justify-content: center;">
            <span class="brand-mark"><img src="{{ asset('assets/logo.png') }}" alt="DISC ONE"></span>
            <span class="brand-word">DISC<b>ONE</b></span>
        </div>

        <span class="eyebrow" style="margin-top: var(--s-6);">Etapa 2 · Layout</span>
        <h1>Visual aplicado com sucesso</h1>
        <p class="lead">
            Se você vê o tema escuro, a fonte <span class="mono">Sora/Inter</span> e as 4 cores abaixo,
            o <span class="mono">foundation.css</span> e o layout mestre estão funcionando.
        </p>

        <div class="disc-legend">
            <span><i class="disc-dot dot-d"></i> Dominância</span>
            <span><i class="disc-dot dot-i"></i> Influência</span>
            <span><i class="disc-dot dot-s"></i> Estabilidade</span>
            <span><i class="disc-dot dot-c"></i> Conformidade</span>
        </div>

        <div style="display: flex; gap: var(--s-3); justify-content: center; flex-wrap: wrap;">
            <button class="btn btn-primary btn-lg">Botão primário</button>
            <button class="btn btn-ghost btn-lg">Botão ghost</button>
        </div>

        <p class="ok">✓ Laravel {{ app()->version() }} · {{ config('app.name') }}</p>
    </div>
</div>
@endsection
