@extends('layouts.admin')

@section('admin_title', 'Estoque')

@section('content')
    <div class="content-head">
        <h1>Estoque</h1>
        <p>{{ $stats['skus'] }} SKUs · {{ $stats['unidades'] }} unidades em estoque</p>
    </div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif

    <div class="metric-grid" style="margin-bottom:22px;">
        <div class="metric-card"><div class="m-top"><span class="m-label">SKUs</span></div><div class="m-value">{{ $stats['skus'] }}</div></div>
        <div class="metric-card"><div class="m-top"><span class="m-label">Estoque baixo</span></div><div class="m-value" style="color:var(--color-primary);">{{ $stats['baixo'] }}</div></div>
        <div class="metric-card"><div class="m-top"><span class="m-label">Esgotados</span></div><div class="m-value" style="color:var(--color-danger);">{{ $stats['esgotado'] }}</div></div>
        <div class="metric-card"><div class="m-top"><span class="m-label">Unidades totais</span></div><div class="m-value">{{ $stats['unidades'] }}</div></div>
    </div>

    <div class="filter-row">
        <a href="{{ route('admin.stock.index') }}" class="filter-pill {{ ! $filter ? 'is-active' : '' }}">Todos</a>
        <a href="{{ route('admin.stock.index', ['filtro' => 'baixo']) }}" class="filter-pill {{ $filter === 'baixo' ? 'is-active' : '' }}">Estoque baixo (≤ {{ $low }})</a>
        <a href="{{ route('admin.stock.index', ['filtro' => 'esgotado']) }}" class="filter-pill {{ $filter === 'esgotado' ? 'is-active' : '' }}">Esgotados</a>
    </div>

    <div class="data-card">
        <table class="data-table">
            <thead>
                <tr><th>Produto</th><th>SKU</th><th>Categoria</th><th>Situação</th><th>Atual</th><th style="text-align:right;">Ajustar</th></tr>
            </thead>
            <tbody>
                @forelse ($products as $p)
                    <tr>
                        <td data-label="Produto">{{ $p->name }}</td>
                        <td class="td-muted" data-label="SKU">{{ $p->sku ?? '—' }}</td>
                        <td class="td-muted" data-label="Categoria">{{ $p->category?->name ?? '—' }}</td>
                        <td data-label="Situação">
                            @if ($p->stock_qty <= 0)
                                <span class="status status--cancelado">Esgotado</span>
                            @elseif ($p->stock_qty <= $low)
                                <span class="status status--pendente">Baixo</span>
                            @else
                                <span class="status status--pago">OK</span>
                            @endif
                        </td>
                        <td class="td-num" data-label="Atual">{{ $p->stock_qty }}</td>
                        <td data-label="" style="text-align:right;">
                            <form action="{{ route('admin.stock.update', $p) }}" method="POST" style="display:inline-flex; gap:8px; align-items:center; justify-content:flex-end;">
                                @csrf
                                @method('PATCH')
                                <input type="number" name="stock_qty" value="{{ $p->stock_qty }}" min="0" class="admin-input" style="width:90px; padding:6px 10px;">
                                <button type="submit" class="admin-btn admin-btn--ghost" style="padding:6px 12px;">Salvar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="td-muted" style="text-align:center; padding:48px;">Nenhum produto {{ $filter ? 'nesse filtro' : 'cadastrado' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['paginator' => $products])
@endsection
