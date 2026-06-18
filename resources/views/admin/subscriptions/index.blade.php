@extends('layouts.admin')

@section('admin_title', 'Assinaturas')

@php
    $smap = ['active' => ['Ativa', 'status--pago'], 'paused' => ['Pausada', 'status--pendente'], 'cancelled' => ['Cancelada', 'status--cancelado']];
@endphp

@section('content')
    <div class="content-head">
        <h1>Assinaturas</h1>
        <p>{{ $subscriptions->total() }} {{ $subscriptions->total() === 1 ? 'assinatura' : 'assinaturas' }}{{ $status ? ' · filtro aplicado' : '' }}</p>
    </div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif

    <div class="filter-row">
        <a href="{{ route('admin.subscriptions.index') }}" class="filter-pill {{ ! $status ? 'is-active' : '' }}">Todas ({{ $counts['all'] }})</a>
        <a href="{{ route('admin.subscriptions.index', ['status' => 'active']) }}" class="filter-pill {{ $status === 'active' ? 'is-active' : '' }}">Ativas ({{ $counts['active'] }})</a>
        <a href="{{ route('admin.subscriptions.index', ['status' => 'paused']) }}" class="filter-pill {{ $status === 'paused' ? 'is-active' : '' }}">Pausadas ({{ $counts['paused'] }})</a>
        <a href="{{ route('admin.subscriptions.index', ['status' => 'cancelled']) }}" class="filter-pill {{ $status === 'cancelled' ? 'is-active' : '' }}">Canceladas ({{ $counts['cancelled'] }})</a>
    </div>

    <div class="data-card">
        <table class="data-table">
            <thead>
                <tr><th>Assinante</th><th>Plano</th><th>Valor</th><th>Próx. renovação</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($subscriptions as $sub)
                    @php [$sl, $sc] = $smap[$sub->status] ?? [$sub->status, 'status--pendente']; @endphp
                    <tr>
                        <td data-label="Assinante">
                            <div class="cust"><span class="cust-av">{{ strtoupper(mb_substr($sub->user->name ?? '?', 0, 2)) }}</span>
                                <span>{{ $sub->user->name ?? '—' }}<br><span class="td-muted" style="font-size:var(--text-xs);">{{ $sub->user->email ?? '' }}</span></span>
                            </div>
                        </td>
                        <td data-label="Plano">{{ $sub->plan?->name ?? '—' }}</td>
                        <td class="td-num" data-label="Valor">R$ {{ number_format((float) $sub->price, 2, ',', '.') }}/mês</td>
                        <td class="td-muted" data-label="Próx. renovação">{{ $sub->isActive() && $sub->next_renewal_at ? $sub->next_renewal_at->format('d/m/Y') : '—' }}</td>
                        <td data-label="Status"><span class="status {{ $sc }}">{{ $sl }}</span></td>
                        <td data-label="" style="text-align:right; white-space:nowrap;">
                            <a href="{{ route('admin.subscriptions.show', $sub) }}" class="admin-btn admin-btn--ghost" style="padding:6px 12px;">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="td-muted" style="text-align:center; padding:48px;">Nenhuma assinatura {{ $status ? 'com esse status' : 'ainda' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['paginator' => $subscriptions])
@endsection
