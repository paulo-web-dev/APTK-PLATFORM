@extends('layouts.admin')

@section('admin_title', 'Categorias')

@section('content')
    <div class="content-head" style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div>
            <h1>Categorias</h1>
            <p>{{ $categories->total() }} {{ $categories->total() === 1 ? 'categoria' : 'categorias' }}</p>
        </div>
        <a href="{{ route('admin.categories.create') }}" class="admin-btn">+ Nova categoria</a>
    </div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif

    <div class="data-card">
        <table class="data-table">
            <thead>
                <tr><th>Categoria</th><th>Slug</th><th>Produtos</th><th>Ordem</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($categories as $cat)
                    <tr>
                        <td data-label="Categoria">{{ $cat->name }}</td>
                        <td class="td-muted" data-label="Slug">{{ $cat->slug }}</td>
                        <td class="td-num" data-label="Produtos">{{ $cat->products_count }}</td>
                        <td class="td-num" data-label="Ordem">{{ $cat->sort_order }}</td>
                        <td data-label="Status">
                            @if ($cat->active)
                                <span class="status status--pago">Ativa</span>
                            @else
                                <span class="status status--cancelado">Inativa</span>
                            @endif
                        </td>
                        <td data-label="" style="text-align:right; white-space:nowrap;">
                            <a href="{{ route('admin.categories.edit', $cat) }}" class="admin-btn admin-btn--ghost" style="padding:6px 12px;">Editar</a>
                            <form action="{{ route('admin.categories.destroy', $cat) }}" method="POST" style="display:inline;" onsubmit="return confirm('Remover {{ addslashes($cat->name) }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="admin-btn admin-btn--ghost" style="padding:6px 12px; color:var(--color-danger);">Excluir</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="td-muted" style="text-align:center; padding:48px;">Nenhuma categoria ainda. <a href="{{ route('admin.categories.create') }}" style="color:var(--color-primary);">Criar a primeira</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['paginator' => $categories])
@endsection
