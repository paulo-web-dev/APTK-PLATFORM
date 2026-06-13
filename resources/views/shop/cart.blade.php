@extends('layouts.public')

@section('title', 'Carrinho · APTK Spirits')

@push('styles')
<style>
    .cart-wrap { padding-block: 48px; }
    .cart-alert { font-family: var(--font-mono); font-size: var(--text-sm); color: var(--color-success); border: 1px solid var(--color-border); border-left: 3px solid var(--color-success); border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 28px; }
    .cart-layout { display: grid; grid-template-columns: 1fr 340px; gap: 40px; align-items: start; }
    .cart-row { display: grid; grid-template-columns: 84px 1fr auto auto auto; align-items: center; gap: 18px; padding: 18px 0; border-bottom: 1px solid var(--color-border); }
    .cart-thumb { width: 84px; height: 84px; aspect-ratio: 1/1; }
    .cart-info { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
    .cart-name { font-family: var(--font-script); font-size: var(--text-xl); color: var(--color-primary); text-decoration: none; line-height: 1.1; }
    .cart-name:hover { text-decoration: underline; }
    .cart-unit { font-family: var(--font-mono); font-size: var(--text-xs); color: var(--color-text-muted); }
    .cart-qty { display: flex; align-items: center; gap: 8px; }
    .qty-input { width: 64px; background: var(--color-bg); border: 1px solid var(--color-border); color: var(--color-text); border-radius: var(--radius-sm); padding: 7px 9px; font-family: var(--font-mono); text-align: center; }
    .qty-btn { background: none; border: none; color: var(--color-text-muted); font-family: var(--font-mono); font-size: var(--text-xs); text-transform: uppercase; letter-spacing: 0.06em; cursor: pointer; padding: 4px; }
    .qty-btn:hover { color: var(--color-primary); }
    .cart-subtotal { font-family: var(--font-mono); color: var(--color-text); white-space: nowrap; }
    .remove-btn { background: none; border: none; color: var(--color-text-muted); font-size: 1.4rem; line-height: 1; cursor: pointer; padding: 0 4px; }
    .remove-btn:hover { color: var(--color-danger); }
    .cart-summary { border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 26px; background: var(--color-bg-elevated); }
    .summary-title { font-family: var(--font-display); font-size: var(--text-xl); color: var(--color-text); margin: 0 0 18px; }
    .summary-row { display: flex; justify-content: space-between; font-family: var(--font-mono); font-size: var(--text-sm); color: var(--color-text); padding: 8px 0; }
    .summary-row.muted { color: var(--color-text-muted); }
    .summary-total { display: flex; justify-content: space-between; font-family: var(--font-mono); font-size: var(--text-lg); color: var(--color-text); padding: 16px 0 4px; margin-top: 8px; border-top: 1px solid var(--color-border); }
    .empty { text-align: center; padding: 80px 20px; color: var(--color-text-muted); border: 1px dashed var(--color-border); border-radius: var(--radius-lg); }
    @media (max-width: 820px) {
        .cart-layout { grid-template-columns: 1fr; }
        .cart-row { grid-template-columns: 64px 1fr auto; row-gap: 12px; }
        .cart-thumb { width: 64px; height: 64px; }
        .cart-qty { grid-column: 2 / -1; justify-content: flex-start; }
    }
</style>
@endpush

@section('content')
<section class="cart-wrap">
    <div class="container-aptk">

        <span class="eyebrow">Carrinho</span>
        <h1 class="section-title" style="margin-bottom:32px;">Seu carrinho</h1>

        @if (session('status'))
            <div class="cart-alert">{{ session('status') }}</div>
        @endif

        @if ($items->isEmpty())
            <div class="empty">
                Seu carrinho está vazio.
                <a href="{{ route('catalog') }}" class="text-gold">Ver a loja</a>.
            </div>
        @else
            <div class="cart-layout">
                <div class="cart-items">
                    @foreach ($items as $row)
                        <div class="cart-row">
                            <div class="placeholder cart-thumb"><span>{{ $row->product->name }}</span></div>

                            <div class="cart-info">
                                <a href="{{ route('product', $row->product->slug) }}" class="cart-name">{{ $row->product->name }}</a>
                                <span class="cart-unit">R$ {{ number_format($row->product->price, 2, ',', '.') }} / un</span>
                            </div>

                            <form action="{{ route('cart.update') }}" method="POST" class="cart-qty">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="product_id" value="{{ $row->product->id }}">
                                <input type="number" name="qty" value="{{ $row->qty }}" min="0" class="qty-input">
                                <button type="submit" class="qty-btn">Atualizar</button>
                            </form>

                            <span class="cart-subtotal">R$ {{ number_format($row->subtotal, 2, ',', '.') }}</span>

                            <form action="{{ route('cart.remove') }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="product_id" value="{{ $row->product->id }}">
                                <button type="submit" class="remove-btn" aria-label="Remover">&times;</button>
                            </form>
                        </div>
                    @endforeach
                </div>

                <aside class="cart-summary">
                    <h3 class="summary-title">Resumo</h3>
                    <div class="summary-row"><span>Subtotal</span><span>R$ {{ number_format($total, 2, ',', '.') }}</span></div>
                    <div class="summary-row muted"><span>Frete</span><span>No checkout</span></div>
                    <div class="summary-total"><span>Total</span><span>R$ {{ number_format($total, 2, ',', '.') }}</span></div>

                    <a href="{{ route('checkout.index') }}" class="btn-aptk btn-aptk--block" style="margin-top:20px;">Finalizar compra</a>
                    <a href="{{ route('catalog') }}" class="btn-aptk btn-aptk--outline btn-aptk--block" style="margin-top:10px;">Continuar comprando</a>
                </aside>
            </div>
        @endif

    </div>
</section>
@endsection
