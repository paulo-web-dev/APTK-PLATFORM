<x-app-layout>
    @push('styles')
    <style>
        .flash-ok { background: var(--gold-faint); border: 1px solid var(--color-primary-muted); color: var(--color-text); border-radius: var(--radius-md); padding: 14px 18px; margin-bottom: 24px; font-size: var(--text-sm); }

        .sub-item { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 28px; margin-bottom: 18px; }
        .sub-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
        .sub-head .s-plan { font-family: var(--font-script); font-size: var(--text-2xl); color: var(--color-primary); line-height: 1; }
        .sub-head .s-price { font-family: var(--font-mono); color: var(--color-text-muted); font-size: var(--text-sm); margin-top: 6px; }
        .badge-status { font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.12em; text-transform: uppercase; padding: 5px 12px; border-radius: var(--radius-sm); white-space: nowrap; border: 1px solid var(--color-border); color: var(--color-text-muted); }
        .badge-status.active { border-color: var(--color-primary); color: var(--color-primary); background: var(--gold-faint); }

        .sub-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; border-top: 1px solid var(--color-border); padding-top: 18px; margin-top: 18px; }
        .sub-details .k { font-size: var(--text-xs); color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.08em; }
        .sub-details .v { color: var(--color-text); font-size: var(--text-sm); margin-top: 3px; }

        .sub-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 20px; }
        .btn-text { background: none; border: none; color: var(--color-text-muted); font-size: var(--text-sm); cursor: pointer; text-decoration: underline; padding: 8px 4px; font-family: var(--font-body); }
        .btn-text:hover { color: var(--color-text); }

        .sub-past { margin-top: 36px; }
        .sub-past h2 { font-family: var(--font-display); font-size: var(--text-lg); margin: 0 0 12px; }
        .sub-past-item { display: flex; justify-content: space-between; gap: 12px; padding: 12px 2px; border-bottom: 1px solid var(--color-border); font-size: var(--text-sm); color: var(--color-text-muted); }

        .sub-empty { text-align: center; padding: 56px 24px; }
        .sub-empty p { color: var(--color-text-muted); margin: 0 0 22px; }
    </style>
    @endpush

    <x-slot name="header">
        <h1>Minha assinatura</h1>
        <p style="color: var(--color-text-muted); margin: 4px 0 0; font-size: var(--text-sm);">Gerencie o seu plano do Clube APTK.</p>
    </x-slot>

    @if (session('subscription_ok'))
        <div class="flash-ok">{{ session('subscription_ok') }}</div>
    @endif

    @php
        $pmLabel = ['pix' => 'PIX', 'cartao' => 'Cartão', 'boleto' => 'Boleto'];
        $current = $subscriptions->whereIn('status', ['active', 'paused']);
        $past = $subscriptions->where('status', 'cancelled');
    @endphp

    @forelse ($current as $sub)
        <div class="sub-item">
            <div class="sub-head">
                <div>
                    <p class="s-plan">{{ $sub->plan?->name ?? 'Plano' }}</p>
                    <p class="s-price">R$ {{ number_format((float) $sub->price, 2, ',', '.') }} / mês</p>
                </div>
                <span class="badge-status {{ $sub->isActive() ? 'active' : '' }}">{{ $sub->statusLabel() }}</span>
            </div>

            <div class="sub-details">
                <div>
                    <p class="k">{{ $sub->isPaused() ? 'Status' : 'Próxima renovação' }}</p>
                    <p class="v">{{ $sub->isPaused() ? 'Pausada' : ($sub->next_renewal_at?->format('d/m/Y') ?? '—') }}</p>
                </div>
                <div>
                    <p class="k">Pagamento</p>
                    <p class="v">{{ $pmLabel[$sub->payment_method] ?? '—' }}</p>
                </div>
                <div>
                    <p class="k">Início</p>
                    <p class="v">{{ $sub->started_at?->format('d/m/Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="k">Entrega</p>
                    <p class="v">{{ $sub->recipient_name }}@if ($sub->shipping_address)<br><span style="color: var(--color-text-muted);">{{ $sub->shipping_address }}</span>@endif</p>
                </div>
            </div>

            <div class="sub-actions">
                @if ($sub->isActive())
                    <form method="POST" action="{{ route('subscription.pause', $sub) }}">
                        @csrf
                        <button type="submit" class="btn-aptk btn-aptk--outline">Pausar</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('subscription.resume', $sub) }}">
                        @csrf
                        <button type="submit" class="btn-aptk">Retomar</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('subscription.cancel', $sub) }}" onsubmit="return confirm('Cancelar esta assinatura? Esta ação não pode ser desfeita.');">
                    @csrf
                    <button type="submit" class="btn-text">Cancelar assinatura</button>
                </form>
            </div>
        </div>
    @empty
        <div class="card-aptk sub-empty">
            <p>Você ainda não tem uma assinatura ativa.</p>
            <a href="{{ route('pages.show', 'clube') }}" class="btn-aptk">Conhecer o Clube</a>
        </div>
    @endforelse

    @if ($past->isNotEmpty())
        <div class="sub-past">
            <h2>Histórico</h2>
            @foreach ($past as $sub)
                <div class="sub-past-item">
                    <span>{{ $sub->plan?->name ?? 'Plano' }} · cancelada em {{ $sub->cancelled_at?->format('d/m/Y') ?? '—' }}</span>
                    <span>R$ {{ number_format((float) $sub->price, 2, ',', '.') }}/mês</span>
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
