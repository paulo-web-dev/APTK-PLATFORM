@extends('layouts.admin')

@section('admin_title', 'Dashboard')

@php
    $meses = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
    $mesRef = $meses[(int) now()->format('n')] . ' de ' . now()->format('Y');
    $leadLabels = ['new'=>'Novo','contacted'=>'Contatado','converted'=>'Convertido','archived'=>'Arquivado'];
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

    <h2 style="font-family:var(--font-body); font-size:var(--text-lg); font-weight:600; margin:4px 0 18px;">Clube &amp; marketing</h2>

    <div class="metric-grid">
        <div class="metric-card">
            <div class="m-top">
                <span class="m-label">Assinaturas ativas</span>
                <span class="m-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3l2.5 5 5.5.8-4 3.9.9 5.5L12 21l-4.9 2.6.9-5.5-4-3.9 5.5-.8z"/></svg></span>
            </div>
            <div class="m-value">{{ $activeSubs }}</div>
            <span class="m-delta flat">no Clube</span>
        </div>

        <div class="metric-card">
            <div class="m-top">
                <span class="m-label">Receita recorrente</span>
                <span class="m-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg></span>
            </div>
            <div class="m-value">R$ {{ number_format($mrr, 0, ',', '.') }}</div>
            <span class="m-delta flat">por mês (MRR)</span>
        </div>

        <div class="metric-card">
            <div class="m-top">
                <span class="m-label">Leads novos</span>
                <span class="m-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span>
            </div>
            <div class="m-value">{{ $newLeads }}</div>
            <span class="m-delta {{ $newLeads > 0 ? 'up' : 'flat' }}">{{ $newLeads > 0 ? 'aguardando contato' : '— nenhum novo' }}</span>
        </div>

        <div class="metric-card">
            <div class="m-top">
                <span class="m-label">Total de leads</span>
                <span class="m-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 5.2a3.2 3.2 0 0 1 0 5.6M21 20c0-2.6-1.6-4.8-4-5.6"/></svg></span>
            </div>
            <div class="m-value">{{ $totalLeads }}</div>
            <span class="m-delta flat">capturados</span>
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

    <div class="data-card" style="margin-top:24px;">
        <div class="data-card-head">
            <h2>Leads recentes</h2>
            <a href="{{ route('admin.leads.index') }}">Ver todos →</a>
        </div>
        <table class="data-table">
            <thead>
                <tr><th>Contato</th><th>Tipo</th><th>Data</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($recentLeads as $lead)
                    <tr>
                        <td data-label="Contato">{{ $lead->name ?: '—' }}<br><span class="td-muted" style="font-size:var(--text-xs);">{{ $lead->email }}</span></td>
                        <td class="td-muted" data-label="Tipo" style="text-transform:capitalize;">{{ $lead->type ?? '—' }}</td>
                        <td class="td-muted" data-label="Data">{{ $lead->created_at->format('d/m/Y') }}</td>
                        <td data-label="Status"><span class="td-muted">{{ $leadLabels[$lead->status] ?? ucfirst((string) $lead->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="td-muted" style="text-align:center; padding:40px;">Nenhum lead capturado ainda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
