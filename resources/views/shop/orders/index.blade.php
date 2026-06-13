<x-app-layout>
    @push('styles')
    <style>
        .ord-list { display: flex; flex-direction: column; gap: 14px; }
        .ord-row { display: flex; align-items: center; gap: 16px; padding: 20px 24px; flex-wrap: wrap; text-decoration: none; transition: border-color .2s ease; }
        .ord-row:hover { border-color: var(--color-primary-muted); }
        .ord-row .num { font-family: var(--font-mono); color: var(--color-text); font-size: var(--text-sm); }
        .ord-row .date { color: var(--color-text-muted); font-size: var(--text-sm); }
        .ord-row .total { font-family: var(--font-mono); color: var(--color-text); margin-left: auto; }
        .ord-row .arrow { color: var(--color-text-muted); }
        .ord-status { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: .05em; text-transform: uppercase; padding: 5px 11px; border-radius: 100px; border: 1px solid; display: inline-block; }
        .s-pending { color: var(--color-text-muted); border-color: var(--color-border); }
        .s-paid, .s-delivered { color: var(--color-success); border-color: color-mix(in srgb, var(--color-success) 35%, transparent); }
        .s-shipped { color: var(--color-primary); border-color: var(--color-primary-muted); }
        .s-cancelled { color: var(--color-danger); border-color: color-mix(in srgb, var(--color-danger) 35%, transparent); }
        .ord-empty { padding: 56px 24px; text-align: center; }
        .ord-empty p { color: var(--color-text-muted); margin: 0 0 20px; }
        .ord-pag { display: flex; gap: 12px; align-items: center; margin-top: 24px; }
    </style>
    @endpush

    <x-slot name="header">
        <h1>Meus pedidos</h1>
        <p class="acc-sub">{{ $orders->total() }} {{ $orders->total() === 1 ? 'pedido' : 'pedidos' }} no total.</p>
    </x-slot>

    @if ($orders->isEmpty())
        <div class="card-aptk ord-empty">
            <p>Você ainda não fez nenhum pedido.</p>
            <a href="{{ route('catalog') }}" class="btn-aptk">Explorar a loja</a>
        </div>
    @else
        <div class="ord-list">
            @foreach ($orders as $o)
                <a href="{{ route('orders.show', $o) }}" class="card-aptk ord-row">
                    <span class="num">APTK-{{ str_pad($o->id, 5, '0', STR_PAD_LEFT) }}</span>
                    <span class="date">{{ $o->created_at->format('d/m/Y') }}</span>
                    @include('shop.partials.order-status', ['status' => $o->status])
                    <span class="date">· {{ $o->items_count }} {{ $o->items_count === 1 ? 'item' : 'itens' }}</span>
                    <span class="total">R$ {{ number_format($o->total, 2, ',', '.') }}</span>
                    <span class="arrow">→</span>
                </a>
            @endforeach
        </div>

        @if ($orders->hasPages())
            <div class="ord-pag">
                @if ($orders->onFirstPage())
                    <span class="btn-aptk btn-aptk--outline" style="opacity:.4; pointer-events:none;">← Anterior</span>
                @else
                    <a href="{{ $orders->previousPageUrl() }}" class="btn-aptk btn-aptk--outline">← Anterior</a>
                @endif
                <span class="date" style="font-family:var(--font-mono); font-size:var(--text-xs);">Página {{ $orders->currentPage() }} de {{ $orders->lastPage() }}</span>
                @if ($orders->hasMorePages())
                    <a href="{{ $orders->nextPageUrl() }}" class="btn-aptk btn-aptk--outline">Próxima →</a>
                @else
                    <span class="btn-aptk btn-aptk--outline" style="opacity:.4; pointer-events:none;">Próxima →</span>
                @endif
            </div>
        @endif
    @endif
</x-app-layout>
