@extends('layouts.admin')

@section('admin_title', 'Cupons')

@section('content')
    <div class="content-head" style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div>
            <h1>Cupons</h1>
            <p>{{ $coupons->total() }} {{ $coupons->total() === 1 ? 'cupom' : 'cupons' }}</p>
        </div>
        <a href="{{ route('admin.coupons.create') }}" class="admin-btn">+ Novo cupom</a>
    </div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif

    <div class="data-card">
        <table class="data-table">
            <thead>
                <tr><th>Código</th><th>Desconto</th><th>Mín. pedido</th><th>Usos</th><th>Validade</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($coupons as $c)
                    <tr>
                        <td class="td-id" data-label="Código"><a href="{{ route('admin.coupons.show', $c) }}" style="color:var(--color-primary);">{{ $c->code }}</a>
                            @if ($c->name)<br><span class="td-muted" style="font-size:var(--text-xs);">{{ $c->name }}</span>@endif</td>
                        <td class="td-num" data-label="Desconto">{{ $c->rotuloValor() }}{{ $c->first_purchase_only ? ' · 1ª compra' : '' }}</td>
                        <td class="td-num" data-label="Mín. pedido">{{ $c->min_order > 0 ? moeda_br($c->min_order) : '—' }}</td>
                        <td class="td-num" data-label="Usos">{{ $c->usos_count }}{{ $c->max_uses ? '/'.$c->max_uses : '' }}</td>
                        <td class="td-muted" data-label="Validade">{{ $c->expires_at ? 'até '.data_br($c->expires_at) : '—' }}</td>
                        <td data-label="Status">
                            @switch($c->situacao())
                                @case('ativo') <span class="status status--pago">Ativo</span> @break
                                @case('inativo') <span class="status status--cancelado">Inativo</span> @break
                                @case('agendado') <span class="status status--pendente">Agendado</span> @break
                                @default <span class="status status--pendente">Expirado</span>
                            @endswitch
                        </td>
                        <td data-label="" style="text-align:right; white-space:nowrap;">
                            <a href="{{ route('admin.coupons.edit', $c) }}" class="admin-btn admin-btn--ghost" style="padding:6px 12px;">Editar</a>
                            @if ($c->usos_count === 0 && ! $c->orders()->exists())
                                <form action="{{ route('admin.coupons.destroy', $c) }}" method="POST" style="display:inline;" onsubmit="return confirm('Remover o cupom {{ $c->code }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-btn admin-btn--ghost" style="padding:6px 12px; color:var(--color-danger);">Excluir</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="td-muted" style="text-align:center; padding:48px;">Nenhum cupom ainda. <a href="{{ route('admin.coupons.create') }}" style="color:var(--color-primary);">Criar o primeiro</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['paginator' => $coupons])
@endsection
