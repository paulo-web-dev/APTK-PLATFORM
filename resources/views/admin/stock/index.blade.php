@extends('layouts.admin')

@section('admin_title', 'Estoque')

@section('content')
    <div class="content-head">
        <h1>Estoque</h1>
        <p>{{ $stats['volumes'] }} volumes · {{ $stats['unidades'] }} unidades em estoque</p>
    </div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert-ok" style="color:var(--color-danger); border-left-color:var(--color-danger);">{{ $errors->first() }}</div>
    @endif

    <div class="metric-grid" style="margin-bottom:22px;">
        <div class="metric-card"><div class="m-top"><span class="m-label">Volumes</span></div><div class="m-value">{{ $stats['volumes'] }}</div></div>
        <div class="metric-card"><div class="m-top"><span class="m-label">Estoque baixo</span></div><div class="m-value" style="color:var(--color-primary);">{{ $stats['baixo'] }}</div></div>
        <div class="metric-card"><div class="m-top"><span class="m-label">Esgotados</span></div><div class="m-value" style="color:var(--color-danger);">{{ $stats['esgotado'] }}</div></div>
        <div class="metric-card"><div class="m-top"><span class="m-label">Sem controle</span></div><div class="m-value">{{ $stats['sem_controle'] }}</div></div>
    </div>

    <div class="filter-row">
        <a href="{{ route('admin.stock.index') }}" class="filter-pill {{ ! $filter ? 'is-active' : '' }}">Todos</a>
        <a href="{{ route('admin.stock.index', ['filtro' => 'baixo']) }}" class="filter-pill {{ $filter === 'baixo' ? 'is-active' : '' }}">Estoque baixo (≤ {{ $low }})</a>
        <a href="{{ route('admin.stock.index', ['filtro' => 'esgotado']) }}" class="filter-pill {{ $filter === 'esgotado' ? 'is-active' : '' }}">Esgotados</a>
        <a href="{{ route('admin.stock.index', ['filtro' => 'sem_controle']) }}" class="filter-pill {{ $filter === 'sem_controle' ? 'is-active' : '' }}">Sem controle</a>
    </div>

    <div class="data-card">
        <table class="data-table">
            <thead>
                <tr><th>Produto</th><th>Volume</th><th>Origem</th><th>Situação</th><th>Atual</th><th style="text-align:right;">Ajustar</th></tr>
            </thead>
            <tbody>
                @forelse ($linhas as $l)
                    <tr>
                        <td data-label="Produto">{{ $l['product']->name }}</td>
                        <td data-label="Volume">{{ $l['size'] ?? '—' }}</td>
                        <td data-label="Origem">
                            @if ($l['linx'])
                                <span class="status status--enviado">Linx</span>
                            @else
                                <span class="td-muted">site</span>
                            @endif
                        </td>
                        <td data-label="Situação">
                            @switch($l['situacao'])
                                @case('esgotado') <span class="status status--cancelado">Esgotado</span> @break
                                @case('baixo') <span class="status status--pendente">Baixo</span> @break
                                @case('sem_controle') <span class="status status--entregue" title="Vende sem limite">Sem controle</span> @break
                                @default <span class="status status--pago">OK</span>
                            @endswitch
                        </td>
                        <td class="td-num" data-label="Atual">{{ $l['saldo'] ?? '—' }}</td>
                        <td data-label="" style="text-align:right;">
                            @if ($l['linx'])
                                <span class="td-muted" style="font-size:.85em;">Vem da Linx · código {{ $l['codigo'] }} — <a href="{{ route('admin.microvix.index') }}">Painel Microvix</a></span>
                            @elseif ($l['size'] !== null)
                                <form action="{{ route('admin.stock.update', $l['product']) }}" method="POST" style="display:inline-flex; gap:8px; align-items:center; justify-content:flex-end;">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="size" value="{{ $l['size'] }}">
                                    <input type="number" name="size_stock" value="{{ $l['saldo'] }}" min="0" placeholder="sem controle" class="admin-input" style="width:120px; padding:6px 10px;">
                                    <button type="submit" class="admin-btn admin-btn--ghost" style="padding:6px 12px;">Salvar</button>
                                </form>
                            @else
                                <span class="td-muted" style="font-size:.85em;">Produto sem volumes</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="td-muted" style="text-align:center; padding:48px;">Nenhum volume {{ $filter ? 'nesse filtro' : 'cadastrado' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['paginator' => $linhas])
@endsection
