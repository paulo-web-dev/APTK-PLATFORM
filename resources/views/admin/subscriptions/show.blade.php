@extends('layouts.admin')

@section('admin_title', 'Assinatura #'.$subscription->id)

@php
    $smap = ['active' => ['Ativa', 'status--pago'], 'paused' => ['Pausada', 'status--pendente'], 'cancelled' => ['Cancelada', 'status--cancelado']];
    [$sl, $sc] = $smap[$subscription->status] ?? [$subscription->status, 'status--pendente'];
    $pmLabel = ['pix' => 'PIX', 'cartao' => 'Cartão', 'boleto' => 'Boleto'];
@endphp

@section('content')
    <a href="{{ route('admin.subscriptions.index') }}" class="back-link" style="display:inline-block; margin-bottom:16px;">← Voltar às assinaturas</a>

    <div class="content-head" style="display:flex; align-items:center; gap:14px;">
        <h1 style="margin:0;">Assinatura #{{ $subscription->id }}</h1>
        <span class="status {{ $sc }}">{{ $sl }}</span>
    </div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif

    <div class="order-grid">
        <div>
            <div class="panel">
                <div class="panel-head">Plano</div>
                <div class="panel-body">
                    <div class="kv"><span class="k">Plano</span><span class="v">{{ $subscription->plan?->name ?? '—' }}</span></div>
                    <div class="kv"><span class="k">Valor</span><span class="v">R$ {{ number_format((float) $subscription->price, 2, ',', '.') }}/mês</span></div>
                    <div class="kv"><span class="k">Ciclo</span><span class="v">{{ $subscription->interval }}</span></div>
                    <div class="kv"><span class="k">Início</span><span class="v">{{ $subscription->started_at?->format('d/m/Y') ?? '—' }}</span></div>
                    <div class="kv"><span class="k">Próxima renovação</span><span class="v">{{ $subscription->isActive() && $subscription->next_renewal_at ? $subscription->next_renewal_at->format('d/m/Y') : '—' }}</span></div>
                    @if ($subscription->paused_at)<div class="kv"><span class="k">Pausada em</span><span class="v">{{ $subscription->paused_at->format('d/m/Y') }}</span></div>@endif
                    @if ($subscription->cancelled_at)<div class="kv"><span class="k">Cancelada em</span><span class="v">{{ $subscription->cancelled_at->format('d/m/Y') }}</span></div>@endif
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">Entrega &amp; pagamento</div>
                <div class="panel-body">
                    <div class="kv"><span class="k">Destinatário</span><span class="v">{{ $subscription->recipient_name ?? '—' }}</span></div>
                    <div class="kv"><span class="k">Pagamento</span><span class="v">{{ $pmLabel[$subscription->payment_method] ?? '—' }}</span></div>
                    @if ($subscription->shipping_address)
                        <div style="padding-top:12px;"><p class="td-muted" style="font-size:var(--text-xs); text-transform:uppercase; letter-spacing:0.06em; margin:0 0 6px;">Endereço</p><p class="addr-note">{{ $subscription->shipping_address }}</p></div>
                    @endif
                    @if ($subscription->notes)
                        <div style="padding-top:12px;"><p class="td-muted" style="font-size:var(--text-xs); text-transform:uppercase; letter-spacing:0.06em; margin:0 0 6px;">Observações</p><p class="addr-note">{{ $subscription->notes }}</p></div>
                    @endif
                </div>
            </div>
        </div>

        <div>
            <div class="panel">
                <div class="panel-head">Cliente</div>
                <div class="panel-body">
                    <div class="kv"><span class="k">Nome</span><span class="v">{{ $subscription->user->name ?? '—' }}</span></div>
                    <div class="kv"><span class="k">E-mail</span><span class="v" style="font-size:var(--text-xs);">{{ $subscription->user->email ?? '—' }}</span></div>
                    @if ($subscription->user)
                        <div style="padding-top:12px;"><a href="{{ route('admin.customers.show', $subscription->user) }}" class="admin-btn admin-btn--ghost" style="width:100%; justify-content:center;">Ver cliente</a></div>
                    @endif
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">Ações</div>
                <div class="panel-body" style="display:flex; flex-direction:column; gap:10px;">
                    @if ($subscription->isActive())
                        <form method="POST" action="{{ route('admin.subscriptions.pause', $subscription) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="admin-btn admin-btn--ghost" style="width:100%; justify-content:center;">Pausar</button>
                        </form>
                    @elseif ($subscription->isPaused())
                        <form method="POST" action="{{ route('admin.subscriptions.resume', $subscription) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="admin-btn" style="width:100%; justify-content:center;">Retomar</button>
                        </form>
                    @endif

                    @unless ($subscription->isCancelled())
                        <form method="POST" action="{{ route('admin.subscriptions.cancel', $subscription) }}" onsubmit="return confirm('Cancelar esta assinatura?');">
                            @csrf @method('PATCH')
                            <button type="submit" class="admin-btn admin-btn--ghost" style="width:100%; justify-content:center; color:var(--color-danger);">Cancelar assinatura</button>
                        </form>
                    @else
                        <p class="td-muted" style="font-size:var(--text-sm); margin:0;">Assinatura cancelada — sem ações disponíveis.</p>
                    @endunless
                </div>
            </div>
        </div>
    </div>
@endsection
