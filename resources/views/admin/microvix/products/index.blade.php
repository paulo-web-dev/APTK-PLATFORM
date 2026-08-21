@extends('layouts.admin')

@section('admin_title', 'Microvix — Produtos do ERP')

@section('content')
    <div class="content-head">
        <h1>Produtos do ERP (Microvix)</h1>
        <p>
            @if ($stats['semVinculo'] > 0)
                <strong style="color:var(--color-danger);">Faltam {{ $stats['semVinculo'] }} sem vínculo</strong> de {{ $stats['total'] }} registros do ERP.
            @elseif ($stats['total'] > 0)
                Tudo vinculado — {{ $stats['vinculados'] }} de {{ $stats['total'] }} registros.
            @else
                Nenhum registro do ERP ainda. Rode o sync (ou <code>microvix:fake</code> em desenvolvimento).
            @endif
        </p>
    </div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert-ok" style="color:var(--color-danger); border-left-color:var(--color-danger);">{{ $errors->first() }}</div>
    @endif

    <div class="metric-grid" style="margin-bottom:22px;">
        <div class="metric-card"><div class="m-top"><span class="m-label">Sem vínculo</span></div><div class="m-value" style="color:{{ $stats['semVinculo'] > 0 ? 'var(--color-danger)' : 'inherit' }};">{{ $stats['semVinculo'] }}</div></div>
        <div class="metric-card"><div class="m-top"><span class="m-label">Vinculados</span></div><div class="m-value">{{ $stats['vinculados'] }}</div></div>
        <div class="metric-card"><div class="m-top"><span class="m-label">Total no ERP</span></div><div class="m-value">{{ $stats['total'] }}</div></div>
    </div>

    <div class="filter-row" style="align-items:center; gap:12px; flex-wrap:wrap;">
        <a href="{{ route('admin.microvix.products.index') }}" class="filter-pill {{ ! $filter ? 'is-active' : '' }}">Todos</a>
        <a href="{{ route('admin.microvix.products.index', ['filtro' => 'sem-vinculo']) }}" class="filter-pill {{ $filter === 'sem-vinculo' ? 'is-active' : '' }}">Sem vínculo</a>
        <a href="{{ route('admin.microvix.products.index', ['filtro' => 'vinculados']) }}" class="filter-pill {{ $filter === 'vinculados' ? 'is-active' : '' }}">Vinculados</a>

        <form method="GET" action="{{ route('admin.microvix.products.index') }}" style="display:inline-flex; gap:8px; margin-left:auto;">
            @if ($filter)
                <input type="hidden" name="filtro" value="{{ $filter }}">
            @endif
            <input type="search" name="q" value="{{ $busca }}" placeholder="Código ou nome no ERP" class="admin-input" style="width:220px; padding:6px 10px;">
            <button type="submit" class="admin-btn admin-btn--ghost" style="padding:6px 12px;">Buscar</button>
        </form>
    </div>

    <div class="data-card">
        <table class="data-table">
            <thead>
                <tr><th>Código</th><th>Produto no ERP</th><th>Saldo</th><th>Preço</th><th>Vínculo com a loja</th><th style="text-align:right;"></th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $mp)
                    @php $sugestao = $sugestoes[$mp->id] ?? null; @endphp
                    <tr>
                        <td class="td-muted" data-label="Código">{{ $mp->codigoproduto }}</td>
                        <td data-label="Produto no ERP">
                            {{ $mp->nome_produto ?? '—' }}
                            @unless ($mp->ativo)
                                <span class="status status--cancelado" style="margin-left:6px;">inativo no ERP</span>
                            @endunless
                            @if ($mp->referencia || $mp->codauxiliar1)
                                <div class="td-muted" style="font-size:.82em;">{{ $mp->codauxiliar1 ?? $mp->referencia }}</div>
                            @endif
                        </td>
                        <td class="td-num" data-label="Saldo">{{ (float) $mp->saldo }}</td>
                        <td class="td-num" data-label="Preço">{{ $mp->preco !== null ? 'R$ '.number_format((float) $mp->preco, 2, ',', '.') : '—' }}</td>
                        <td data-label="Vínculo">
                            @if ($mp->product_id === null)
                                <span class="status status--pendente">Sem vínculo</span>
                                @if ($sugestao)
                                    <div class="td-muted" style="font-size:.82em;">sugestão por {{ $sugestao['criterio'] }} ({{ $sugestao['confianca'] }}) — confirme ao lado</div>
                                @endif
                            @else
                                <span class="status status--pago">{{ $mp->product?->name ?? "produto #{$mp->product_id}" }}{{ $mp->size ? " — {$mp->size}" : '' }}</span>
                            @endif
                        </td>
                        <td data-label="" style="text-align:right; white-space:nowrap;">
                            <form action="{{ route('admin.microvix.products.vincular', $mp) }}" method="POST" style="display:inline-flex; gap:8px; align-items:center;">
                                @csrf
                                @method('PATCH')
                                @php
                                    $selecionado = $mp->product_id !== null
                                        ? "{$mp->product_id}|{$mp->size}"
                                        : ($sugestao ? "{$sugestao['product']->id}|{$sugestao['size']}" : '');
                                @endphp
                                <select name="vinculo" class="admin-input" style="padding:6px 10px; max-width:230px;">
                                    <option value="">— escolher —</option>
                                    @foreach ($combos as $valor => $rotulo)
                                        <option value="{{ $valor }}" @selected($valor === $selecionado)>{{ $rotulo }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="admin-btn admin-btn--ghost" style="padding:6px 12px;">{{ $mp->product_id === null ? 'Vincular' : 'Alterar' }}</button>
                            </form>
                            @if ($mp->product_id !== null)
                                <form action="{{ route('admin.microvix.products.desvincular', $mp) }}" method="POST" style="display:inline-flex;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-btn admin-btn--ghost" style="padding:6px 12px;">Desvincular</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="td-muted" style="text-align:center; padding:48px;">Nenhum registro {{ $filter || $busca ? 'nesse filtro' : 'do ERP ainda' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['paginator' => $rows])
@endsection
