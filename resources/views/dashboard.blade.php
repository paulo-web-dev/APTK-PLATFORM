<x-app-layout>
    @push('styles')
    <style>
        .acc-sub  { color: var(--color-text-muted); margin: 4px 0 0; font-size: var(--text-sm); }
        .acc-card { padding: 24px; height: 100%; }
        .acc-num  { font-family: var(--font-mono); font-size: var(--text-3xl); color: var(--color-text); margin: 12px 0 0; line-height: 1; }
        .acc-cap  { color: var(--color-text-muted); font-size: var(--text-sm); margin: 4px 0 0; }
    </style>
    @endpush

    <x-slot name="header">
        <h1>Minha conta</h1>
        <p class="acc-sub">Bem-vindo de volta, {{ auth()->user()->name }}.</p>
    </x-slot>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <a href="{{ route('orders.index') }}" class="card-aptk acc-card" style="display:block; text-decoration:none;">
                <span class="eyebrow">Pedidos</span>
                <p class="acc-num">{{ auth()->user()->orders()->count() }}</p>
                <p class="acc-cap">pedidos realizados · ver todos →</p>
            </a>
        </div>
        <div class="col-12 col-md-4">
            <div class="card-aptk acc-card">
                <span class="eyebrow">Endereços</span>
                <p class="acc-num">{{ auth()->user()->addresses()->count() }}</p>
                <p class="acc-cap">endereços salvos</p>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card-aptk acc-card">
                <span class="eyebrow">Acesso</span>
                <p class="acc-num" style="font-size: var(--text-xl); text-transform: capitalize; margin-top: 16px;">{{ auth()->user()->role }}</p>
                <p class="acc-cap">tipo de conta</p>
            </div>
        </div>
    </div>

    <div class="card-aptk" style="padding: 28px;">
        <h2 style="font-family: var(--font-display); font-size: var(--text-xl); margin: 0 0 8px;">Continue de onde parou</h2>
        <p style="color: var(--color-text-muted); margin: 0 0 20px;">Explore o catálogo, monte seu rótulo personalizado ou entre para o clube.</p>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('home') }}" class="btn-aptk">Ir para a loja</a>
            <a href="{{ route('orders.index') }}" class="btn-aptk btn-aptk--outline">Meus pedidos</a>
            <a href="{{ route('profile.edit') }}" class="btn-aptk btn-aptk--outline">Editar perfil</a>
        </div>
    </div>
</x-app-layout>
