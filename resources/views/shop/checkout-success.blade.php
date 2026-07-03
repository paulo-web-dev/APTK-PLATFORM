@extends('layouts.public')

@section('title', 'Pedido confirmado · APTK Spirits')

@push('styles')
<style>
    .ok { padding-block: 64px; max-width: 720px; margin: 0 auto; }
    .ok-badge { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.12em; text-transform: uppercase; color: var(--color-success); margin-bottom: 16px; display: block; }
    .ok-title { font-family: var(--font-display); font-size: clamp(2rem, 5vw, 3rem); color: var(--color-text); margin: 0 0 12px; }
    .ok-num { font-family: var(--font-mono); font-size: var(--text-lg); color: var(--color-primary); margin-bottom: 28px; }
    .ok-card { border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 28px; margin-bottom: 22px; }
    .ok-card h3 { font-family: var(--font-display); font-size: var(--text-lg); color: var(--color-text); margin: 0 0 18px; }
    .ok-row { display: flex; justify-content: space-between; gap: 12px; padding: 9px 0; border-bottom: 1px solid var(--color-border); font-size: var(--text-sm); color: var(--color-text); }
    .ok-row:last-child { border-bottom: none; }
    .ok-row .q { font-family: var(--font-mono); color: var(--color-text-muted); }
    .ok-total { display: flex; justify-content: space-between; font-family: var(--font-mono); font-size: var(--text-lg); color: var(--color-text); padding-top: 16px; margin-top: 8px; border-top: 1px solid var(--color-border); }
    .ok-meta { display: flex; gap: 32px; flex-wrap: wrap; }
    .ok-meta div { display: flex; flex-direction: column; gap: 4px; }
    .ok-meta .k { font-size: var(--text-xs); color: var(--color-text-muted); letter-spacing: 0.08em; text-transform: uppercase; }
    .ok-meta .v { font-family: var(--font-mono); color: var(--color-text); }
    .ok-note { color: var(--color-text-muted); line-height: 1.7; white-space: pre-line; font-size: var(--text-sm); }
    .ok-actions { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 8px; }
</style>
@endpush

@php
    $payLabels = ['pix' => 'Pix', 'cartao' => 'Cartão de crédito', 'boleto' => 'Boleto'];
@endphp

@section('content')
<section class="ok">
    <div class="container-aptk">

        <span class="ok-badge">✓ Pedido confirmado</span>
        <h1 class="ok-title">Obrigado pela sua compra!</h1>
        <p class="ok-num">Pedido APTK-{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</p>

        <div class="ok-card">
            <h3>Itens</h3>
            @foreach ($order->items as $item)
                <div class="ok-row">
                    <span><span class="q">{{ $item->qty }}×</span> {{ $item->product_name }}@if ($item->size) <small style="color:var(--color-text-muted);">({{ $item->size }})</small>@endif</span>
                    <span>R$ {{ number_format($item->total_price, 2, ',', '.') }}</span>
                </div>
            @endforeach
            <div class="ok-total"><span>Total</span><span>R$ {{ number_format($order->total, 2, ',', '.') }}</span></div>
        </div>

        <div class="ok-card">
            <h3>Detalhes</h3>
            <div class="ok-meta">
                <div><span class="k">Pagamento</span><span class="v">{{ $payLabels[$order->payment_method] ?? $order->payment_method }}</span></div>
                <div><span class="k">Status</span><span class="v">{{ ucfirst($order->status) }}</span></div>
            </div>
            @if ($order->notes)
                <p class="ok-note" style="margin-top:20px;">{{ $order->notes }}</p>
            @endif
        </div>

        <div class="ok-actions">
            <a href="{{ route('catalog') }}" class="btn-aptk">Continuar comprando</a>
            <a href="{{ route('dashboard') }}" class="btn-aptk btn-aptk--outline">Minha conta</a>
        </div>

    </div>
</section>
@endsection
