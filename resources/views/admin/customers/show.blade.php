@extends('layouts.admin')

@section('admin_title', 'Cliente · ' . $user->name)

@section('content')
    <a href="{{ route('admin.customers.index') }}" class="back-link" style="display:inline-block; margin-bottom:16px;">← Voltar aos clientes</a>

    <div class="content-head">
        <h1>{{ $user->name }}</h1>
        <p>{{ $user->email }} · {{ $user->phone ?? 'sem telefone' }}</p>
    </div>

    <div class="metric-grid" style="margin-bottom:24px;">
        <div class="metric-card"><div class="m-top"><span class="m-label">Pedidos</span></div><div class="m-value">{{ $stats['orders'] }}</div></div>
        <div class="metric-card"><div class="m-top"><span class="m-label">Total gasto</span></div><div class="m-value">R$ {{ number_format($stats['total'], 0, ',', '.') }}</div></div>
        <div class="metric-card"><div class="m-top"><span class="m-label">Último pedido</span></div><div class="m-value" style="font-size:1.4rem;">{{ $stats['lastDate'] ? $stats['lastDate']->format('d/m/Y') : '—' }}</div></div>
        <div class="metric-card"><div class="m-top"><span class="m-label">Cliente desde</span></div><div class="m-value" style="font-size:1.4rem;">{{ $user->created_at->format('m/Y') }}</div></div>
    </div>

    <div class="data-card">
        <div class="data-card-head"><h2>Pedidos do cliente</h2></div>
        <table class="data-table">
            <thead>
                <tr><th>Pedido</th><th>Data</th><th>Itens</th><th>Total</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($orders as $o)
                    <tr>
                        <td class="td-id" data-label="Pedido"><a href="{{ route('admin.orders.show', $o) }}" style="color:inherit;">APTK-{{ str_pad($o->id, 5, '0', STR_PAD_LEFT) }}</a></td>
                        <td class="td-muted" data-label="Data">{{ $o->created_at->format('d/m/Y') }}</td>
                        <td class="td-num" data-label="Itens">{{ $o->items_count }}</td>
                        <td class="td-num" data-label="Total">R$ {{ number_format($o->total, 2, ',', '.') }}</td>
                        <td data-label="Status">@include('admin.partials.status', ['status' => $o->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="td-muted" style="text-align:center; padding:40px;">Esse cliente ainda não fez pedidos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['paginator' => $orders])
@endsection
