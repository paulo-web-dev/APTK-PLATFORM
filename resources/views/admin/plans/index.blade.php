@extends('layouts.admin')

@section('admin_title', 'Planos do Clube')

@section('content')
    <div class="content-head" style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div>
            <h1>Planos do Clube</h1>
            <p>{{ $plans->total() }} {{ $plans->total() === 1 ? 'plano' : 'planos' }} de assinatura</p>
        </div>
        <a href="{{ route('admin.plans.create') }}" class="admin-btn">+ Novo plano</a>
    </div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif

    <div class="data-card">
        <table class="data-table">
            <thead>
                <tr><th>Plano</th><th>Preço</th><th>Assinantes</th><th>Destaque</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($plans as $plan)
                    <tr>
                        <td data-label="Plano">
                            {{ $plan->name }}
                            @if ($plan->kicker)<br><span class="td-muted" style="font-size:var(--text-xs);">{{ $plan->kicker }}</span>@endif
                        </td>
                        <td class="td-num" data-label="Preço">{{ $plan->priceDisplay() }}{{ $plan->isSelfServe() ? $plan->intervalLabel() : '' }}</td>
                        <td class="td-num" data-label="Assinantes">{{ $plan->subscriptions_count }}</td>
                        <td data-label="Destaque">
                            @if ($plan->featured)<span class="status status--pendente">Destaque</span>@else<span class="td-muted">—</span>@endif
                        </td>
                        <td data-label="Status">
                            @if ($plan->active)
                                <span class="status status--pago">Ativo</span>
                            @else
                                <span class="status status--cancelado">Inativo</span>
                            @endif
                        </td>
                        <td data-label="" style="text-align:right; white-space:nowrap;">
                            <a href="{{ route('admin.plans.edit', $plan) }}" class="admin-btn admin-btn--ghost" style="padding:6px 12px;">Editar</a>
                            <form action="{{ route('admin.plans.destroy', $plan) }}" method="POST" style="display:inline;" onsubmit="return confirm('Remover o plano {{ addslashes($plan->name) }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="admin-btn admin-btn--ghost" style="padding:6px 12px; color:var(--color-danger);">Excluir</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="td-muted" style="text-align:center; padding:48px;">Nenhum plano ainda. <a href="{{ route('admin.plans.create') }}" style="color:var(--color-primary);">Criar o primeiro</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['paginator' => $plans])
@endsection
