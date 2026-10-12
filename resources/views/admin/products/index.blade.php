@extends('layouts.admin')

@section('admin_title', 'Produtos')

@section('content')
    <div class="content-head" style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div>
            <h1>Produtos</h1>
            <p>{{ $products->total() }} {{ $products->total() === 1 ? 'produto' : 'produtos' }}{{ $filtro ? ' '.$filtro : ' no catálogo' }}</p>
        </div>
        <a href="{{ route('admin.products.create') }}" class="admin-btn">+ Novo produto</a>
    </div>

    <div class="filter-row">
        <a href="{{ route('admin.products.index') }}" class="filter-pill {{ ! $filtro ? 'is-active' : '' }}">Todos ({{ $contagem['todos'] }})</a>
        <a href="{{ route('admin.products.index', ['status' => 'ativos']) }}" class="filter-pill {{ $filtro === 'ativos' ? 'is-active' : '' }}">Ativos ({{ $contagem['ativos'] }})</a>
        <a href="{{ route('admin.products.index', ['status' => 'inativos']) }}" class="filter-pill {{ $filtro === 'inativos' ? 'is-active' : '' }}">Inativos ({{ $contagem['inativos'] }})</a>
    </div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif

    <div class="data-card">
        <table class="data-table">
            <thead>
                <tr><th></th><th>Produto</th><th>Categoria</th><th>SKU</th><th>Menor preço</th><th>Estoque</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($products as $p)
                    <tr>
                        <td data-label="">
                            <x-product.image class="prod-thumb" :image="$p->primaryImage" alt="" compact style="width:44px; height:44px;" />
                        </td>
                        <td data-label="Produto">
                            {{ $p->name }}
                            @if ($p->featured)<span class="status status--pendente" style="margin-left:6px;">Destaque</span>@endif
                        </td>
                        <td class="td-muted" data-label="Categoria">{{ $p->category?->name ?? '—' }}</td>
                        <td class="td-muted" data-label="SKU" style="font-family:var(--font-mono);">{{ $p->sku ?: '—' }}</td>
                        <td class="td-num" data-label="Menor preço">
                            @if (($menor = $p->menorPreco()) !== null)
                                R$ {{ number_format($menor, 2, ',', '.') }}@if (count($p->sizeOptions()) > 1)<span class="td-muted" style="font-size:var(--text-xs);"> · {{ count($p->sizeOptions()) }} vol.</span>@endif
                            @else
                                <span class="td-muted">sem preço</span>
                            @endif
                        </td>
                        <td class="td-num" data-label="Estoque">{{ $p->stock_qty }}</td>
                        <td data-label="Status">
                            @if ($p->active)
                                <span class="status status--pago">Ativo</span>
                            @else
                                <span class="status status--cancelado">Inativo</span>
                            @endif
                        </td>
                        <td data-label="" style="text-align:right; white-space:nowrap;">
                            <a href="{{ route('admin.products.edit', $p) }}" class="admin-btn admin-btn--ghost" style="padding:6px 12px;">Editar</a>
                            <form action="{{ route('admin.products.destroy', $p) }}" method="POST" style="display:inline;" onsubmit="return confirm('Remover {{ addslashes($p->name) }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="admin-btn admin-btn--ghost" style="padding:6px 12px; color:var(--color-danger);">Excluir</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="td-muted" style="text-align:center; padding:48px;">Nenhum produto {{ $filtro ? $filtro : 'ainda' }}. <a href="{{ route('admin.products.create') }}" style="color:var(--color-primary);">Criar o primeiro</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($products->hasPages())
        <div class="pagination-wrap">
            @if ($products->onFirstPage())
                <span class="admin-btn admin-btn--ghost" style="opacity:.4; pointer-events:none;">← Anterior</span>
            @else
                <a href="{{ $products->previousPageUrl() }}" class="admin-btn admin-btn--ghost">← Anterior</a>
            @endif
            <span class="td-muted" style="font-family:var(--font-mono); font-size:var(--text-xs);">Página {{ $products->currentPage() }} de {{ $products->lastPage() }}</span>
            @if ($products->hasMorePages())
                <a href="{{ $products->nextPageUrl() }}" class="admin-btn admin-btn--ghost">Próxima →</a>
            @else
                <span class="admin-btn admin-btn--ghost" style="opacity:.4; pointer-events:none;">Próxima →</span>
            @endif
        </div>
    @endif
@endsection
