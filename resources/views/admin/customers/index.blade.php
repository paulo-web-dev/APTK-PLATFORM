@extends('layouts.admin')

@section('admin_title', 'Clientes')

@section('content')
    <div class="content-head">
        <h1>Clientes</h1>
        <p>{{ $customers->total() }} {{ $customers->total() === 1 ? 'cliente' : 'clientes' }}{{ $search !== '' ? ' para "'.$search.'"' : '' }}</p>
    </div>

    <form method="GET" action="{{ route('admin.customers.index') }}" style="margin-bottom:22px; display:flex; gap:10px; max-width:460px;">
        <input type="text" name="q" value="{{ $search }}" placeholder="Buscar por nome ou e-mail…" class="admin-input" style="flex:1;">
        <button type="submit" class="admin-btn">Buscar</button>
        @if ($search !== '')
            <a href="{{ route('admin.customers.index') }}" class="admin-btn admin-btn--ghost">Limpar</a>
        @endif
    </form>

    <div class="data-card">
        <table class="data-table">
            <thead>
                <tr><th>Cliente</th><th>Telefone</th><th>Pedidos</th><th>Total gasto</th><th>Cadastro</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($customers as $c)
                    <tr>
                        <td data-label="Cliente">
                            <div class="cust">
                                <span class="cust-av">{{ strtoupper(mb_substr($c->name, 0, 2)) }}</span>
                                <div>{{ $c->name }}<br><span class="td-muted" style="font-size:var(--text-xs);">{{ $c->email }}</span></div>
                            </div>
                        </td>
                        <td class="td-muted" data-label="Telefone">{{ $c->phone ?? '—' }}</td>
                        <td class="td-num" data-label="Pedidos">{{ $c->orders_count }}</td>
                        <td class="td-num" data-label="Total gasto">R$ {{ number_format($c->orders_sum_total ?? 0, 2, ',', '.') }}</td>
                        <td class="td-muted" data-label="Cadastro">{{ $c->created_at->format('d/m/Y') }}</td>
                        <td data-label="" style="text-align:right;"><a href="{{ route('admin.customers.show', $c) }}" class="admin-btn admin-btn--ghost" style="padding:6px 12px;">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="td-muted" style="text-align:center; padding:48px;">Nenhum cliente encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['paginator' => $customers])
@endsection
