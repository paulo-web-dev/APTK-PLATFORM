@extends('layouts.admin')

@section('admin_title', 'Pedidos')

@php
    $statusLabels = ['pending'=>'Pendente','paid'=>'Pago','shipped'=>'Enviado','delivered'=>'Entregue','cancelled'=>'Cancelado'];
    $payLabels    = ['pix'=>'Pix','cartao'=>'Cartão','boleto'=>'Boleto'];
@endphp

@section('content')
    <div class="content-head">
        <h1>Pedidos</h1>
        <p>{{ $orders->total() }} {{ $orders->total() === 1 ? 'pedido' : 'pedidos' }} no total</p>
    </div>

    <div class="filter-row">
        <a href="{{ route('admin.orders.index') }}" class="filter-pill {{ ! $active ? 'is-active' : '' }}">Todos</a>
        @foreach ($statuses as $st)
            <a href="{{ route('admin.orders.index', ['status' => $st]) }}" class="filter-pill {{ $active === $st ? 'is-active' : '' }}">{{ $statusLabels[$st] ?? $st }}</a>
        @endforeach
    </div>

    <div class="data-card">
        <table class="data-table">
            <thead>
                <tr><th>Pedido</th><th>Cliente</th><th>Data</th><th>Itens</th><th>Total</th><th>Pagamento</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($orders as $o)
                    <tr>
                        <td class="td-id" data-label="Pedido"><a href="{{ route('admin.orders.show', $o) }}" style="color:inherit;">APTK-{{ str_pad($o->id, 5, '0', STR_PAD_LEFT) }}</a></td>
                        <td data-label="Cliente"><div class="cust"><span class="cust-av">{{ strtoupper(mb_substr($o->user->name ?? '?', 0, 2)) }}</span>{{ $o->user->name ?? '—' }}</div></td>
                        <td class="td-muted" data-label="Data">{{ $o->created_at->format('d/m/Y H:i') }}</td>
                        <td class="td-num" data-label="Itens">{{ $o->items_count }}</td>
                        <td class="td-num" data-label="Total">R$ {{ number_format($o->total, 2, ',', '.') }}</td>
                        <td class="td-muted" data-label="Pagamento">{{ $payLabels[$o->payment_method] ?? ($o->payment_method ?? '—') }}</td>
                        <td data-label="Status">@include('admin.partials.status', ['status' => $o->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="td-muted" style="text-align:center; padding:48px;">Nenhum pedido encontrado{{ $active ? ' com esse status' : '' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($orders->hasPages())
        <div class="pagination-wrap">
            @if ($orders->onFirstPage())
                <span class="admin-btn admin-btn--ghost" style="opacity:.4; pointer-events:none;">← Anterior</span>
            @else
                <a href="{{ $orders->previousPageUrl() }}" class="admin-btn admin-btn--ghost">← Anterior</a>
            @endif
            <span class="td-muted" style="font-family:var(--font-mono); font-size:var(--text-xs);">Página {{ $orders->currentPage() }} de {{ $orders->lastPage() }}</span>
            @if ($orders->hasMorePages())
                <a href="{{ $orders->nextPageUrl() }}" class="admin-btn admin-btn--ghost">Próxima →</a>
            @else
                <span class="admin-btn admin-btn--ghost" style="opacity:.4; pointer-events:none;">Próxima →</span>
            @endif
        </div>
    @endif
@endsection
