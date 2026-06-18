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
                        <td class="td-id" data-label="Código">{{ $c->code }}</td>
                        <td class="td-num" data-label="Desconto">
                            {{ $c->type === 'percent' ? rtrim(rtrim(number_format($c->value, 2, ',', '.'), '0'), ',').'%' : 'R$ '.number_format($c->value, 2, ',', '.') }}
                        </td>
                        <td class="td-num" data-label="Mín. pedido">{{ $c->min_order > 0 ? 'R$ '.number_format($c->min_order, 2, ',', '.') : '—' }}</td>
                        <td class="td-num" data-label="Usos">{{ $c->used_count }}{{ $c->max_uses ? '/'.$c->max_uses : '' }}</td>
                        <td class="td-muted" data-label="Validade">{{ $c->expires_at ? $c->expires_at->format('d/m/Y') : '—' }}</td>
                        <td data-label="Status">
                            @if ($c->isValid())
                                <span class="status status--pago">Válido</span>
                            @elseif (! $c->active)
                                <span class="status status--cancelado">Inativo</span>
                            @else
                                <span class="status status--pendente">Expirado</span>
                            @endif
                        </td>
                        <td data-label="" style="text-align:right; white-space:nowrap;">
                            <a href="{{ route('admin.coupons.edit', $c) }}" class="admin-btn admin-btn--ghost" style="padding:6px 12px;">Editar</a>
                            <form action="{{ route('admin.coupons.destroy', $c) }}" method="POST" style="display:inline;" onsubmit="return confirm('Remover o cupom {{ $c->code }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="admin-btn admin-btn--ghost" style="padding:6px 12px; color:var(--color-danger);">Excluir</button>
                            </form>
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
