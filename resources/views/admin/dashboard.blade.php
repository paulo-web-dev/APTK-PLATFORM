@extends('layouts.admin')

@section('admin_title', 'Dashboard')

@php
    $meses = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
    $mesRef = $meses[(int) now()->format('n')] . ' de ' . now()->format('Y');
@endphp

@section('content')
    <div class="content-head">
        <h1>Visão geral</h1>
        <p>Resumo da operação · {{ $mesRef }}</p>
    </div>

    <div class="metric-grid">
        <div class="metric-card">
            <div class="m-top">
                <span class="m-label">Pedidos no mês</span>
                <span class="m-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 5h2l2 11h9l2-7H7"/><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/></svg></span>
            </div>
            <div class="m-value">{{ $ordersThisMonth }}</div>
            <span class="m-delta flat">{{ $totalOrders }} no total</span>
        </div>

        <div class="metric-card">
            <div class="m-top">
                <span class="m-label">Receita no mês</span>
                <span class="m-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 2v20M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
            </div>
            <div class="m-value">R$ {{ number_format($revenueThisMonth, 0, ',', '.') }}</div>
            <span class="m-delta flat">ticket médio R$ {{ number_format($avgTicket, 0, ',', '.') }}</span>
        </div>

        <div class="metric-card">
            <div class="m-top">
                <span class="m-label">Produtos ativos</span>
                <span class="m-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 8l7-4 7 4v8l-7 4-7-4z"/><path d="M5 8l7 4 7-4"/></svg></span>
            </div>
            <div class="m-value">{{ $activeProds }}</div>
            <span class="m-delta flat">{{ $totalProds }} no catálogo</span>
        </div>

        <div class="metric-card">
            <div class="m-top">
                <span class="m-label">Clientes</span>
                <span class="m-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg></span>
            </div>
            <div class="m-value">{{ $customers }}</div>
            <span class="m-delta {{ $newThisWeek > 0 ? 'up' : 'flat' }}">{{ $newThisWeek > 0 ? '↑ '.$newThisWeek.' esta semana' : '— esta semana' }}</span>
        </div>
    </div>

    <div class="data-card">
        <div class="data-card-head">
            <h2>Últimos pedidos</h2>
            <a href="{{ route('admin.orders.index') }}">Ver todos →</a>
        </div>
        <table class="data-table">
            <thead>
                <tr><th>Pedido</th><th>Cliente</th><th>Data</th><th>Itens</th><th>Total</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($recentOrders as $o)
                    <tr>
                        <td class="td-id" data-label="Pedido"><a href="{{ route('admin.orders.show', $o) }}" style="color:inherit;">APTK-{{ str_pad($o->id, 5, '0', STR_PAD_LEFT) }}</a></td>
                        <td data-label="Cliente"><div class="cust"><span class="cust-av">{{ strtoupper(mb_substr($o->user->name ?? '?', 0, 2)) }}</span>{{ $o->user->name ?? '—' }}</div></td>
                        <td class="td-muted" data-label="Data">{{ $o->created_at->format('d/m/Y') }}</td>
                        <td class="td-num" data-label="Itens">{{ $o->items_count }}</td>
                        <td class="td-num" data-label="Total">R$ {{ number_format($o->total, 2, ',', '.') }}</td>
                        <td data-label="Status">@include('admin.partials.status', ['status' => $o->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="td-muted" style="text-align:center; padding:48px;">Nenhum pedido ainda. Assim que o primeiro pedido cair, ele aparece aqui.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
