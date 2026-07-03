<x-app-layout>
    @push('styles')
    <style>
        .od-card { padding: 26px; margin-bottom: 20px; }
        .od-card h3 { font-family: var(--font-display); font-size: var(--text-lg); margin: 0 0 16px; }
        .od-row { display: flex; justify-content: space-between; gap: 12px; padding: 9px 0; border-bottom: 1px solid var(--color-border); font-size: var(--text-sm); color: var(--color-text); }
        .od-row:last-child { border-bottom: none; }
        .od-row .q { font-family: var(--font-mono); color: var(--color-text-muted); }
        .od-sub { display: flex; justify-content: space-between; font-size: var(--text-sm); color: var(--color-text-muted); padding: 6px 0; }
        .od-total { display: flex; justify-content: space-between; font-family: var(--font-mono); font-size: var(--text-lg); color: var(--color-text); padding-top: 14px; margin-top: 6px; border-top: 1px solid var(--color-border); }
        .od-meta { display: flex; gap: 32px; flex-wrap: wrap; }
        .od-meta .k { font-size: var(--text-xs); color: var(--color-text-muted); letter-spacing: .08em; text-transform: uppercase; display: block; margin-bottom: 4px; }
        .od-meta .v { font-family: var(--font-mono); color: var(--color-text); }
        .od-note { color: var(--color-text-muted); line-height: 1.7; white-space: pre-line; font-size: var(--text-sm); margin-top: 18px; }
        .od-back { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: .08em; text-transform: uppercase; color: var(--color-text-muted); text-decoration: none; }
        .od-back:hover { color: var(--color-primary); }
        .ord-status { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: .05em; text-transform: uppercase; padding: 5px 11px; border-radius: 100px; border: 1px solid; display: inline-block; }
        .s-pending { color: var(--color-text-muted); border-color: var(--color-border); }
        .s-paid, .s-delivered { color: var(--color-success); border-color: color-mix(in srgb, var(--color-success) 35%, transparent); }
        .s-shipped { color: var(--color-primary); border-color: var(--color-primary-muted); }
        .s-cancelled { color: var(--color-danger); border-color: color-mix(in srgb, var(--color-danger) 35%, transparent); }
    </style>
    @endpush

    @php $payLabels = ['pix' => 'Pix', 'cartao' => 'Cartão de crédito', 'boleto' => 'Boleto']; @endphp

    <x-slot name="header">
        <a href="{{ route('orders.index') }}" class="od-back">← Meus pedidos</a>
        <h1 style="margin-top:10px;">Pedido APTK-{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</h1>
        <p class="acc-sub">{{ $order->created_at->format('d/m/Y \à\s H:i') }} · @include('shop.partials.order-status', ['status' => $order->status])</p>
    </x-slot>

    <div class="card-aptk od-card">
        <h3>Itens</h3>
        @foreach ($order->items as $item)
            <div class="od-row">
                <span><span class="q">{{ $item->qty }}×</span> {{ $item->product_name }}@if ($item->size) <small style="color:var(--color-text-muted);">({{ $item->size }})</small>@endif</span>
                <span>R$ {{ number_format($item->total_price, 2, ',', '.') }}</span>
            </div>
        @endforeach
        <div class="od-sub"><span>Subtotal</span><span>R$ {{ number_format($order->subtotal, 2, ',', '.') }}</span></div>
        @if ($order->shipping_cost > 0)
            <div class="od-sub"><span>Frete</span><span>R$ {{ number_format($order->shipping_cost, 2, ',', '.') }}</span></div>
        @endif
        @if ($order->discount > 0)
            <div class="od-sub"><span>Desconto</span><span>− R$ {{ number_format($order->discount, 2, ',', '.') }}</span></div>
        @endif
        <div class="od-total"><span>Total</span><span>R$ {{ number_format($order->total, 2, ',', '.') }}</span></div>
    </div>

    <div class="card-aptk od-card">
        <h3>Detalhes</h3>
        <div class="od-meta">
            <div><span class="k">Pagamento</span><span class="v">{{ $payLabels[$order->payment_method] ?? ($order->payment_method ?? '—') }}</span></div>
            <div><span class="k">Status</span><span class="v">@include('shop.partials.order-status', ['status' => $order->status])</span></div>
        </div>
        @if ($order->notes)
            <p class="od-note">{{ $order->notes }}</p>
        @endif
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('catalog') }}" class="btn-aptk">Comprar novamente</a>
        <a href="{{ route('orders.index') }}" class="btn-aptk btn-aptk--outline">Voltar aos pedidos</a>
    </div>
</x-app-layout>
