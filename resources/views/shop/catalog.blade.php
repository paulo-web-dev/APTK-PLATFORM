@extends('layouts.public')

@section('title', 'Loja · APTK Spirits')

@push('styles')
<style>
    .catalog-head { padding-block: 56px 8px; }
    .cat-filter { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 36px; }
    .cat-pill { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.08em; text-transform: uppercase; padding: 8px 16px; border-radius: 100px; border: 1px solid var(--color-border); color: var(--color-text-muted); transition: color .2s ease, border-color .2s ease, background-color .2s ease; }
    .cat-pill:hover { color: var(--color-text); border-color: var(--color-primary-muted); }
    .cat-pill.is-active { background: var(--color-primary); color: var(--color-text-inverse); border-color: var(--color-primary); }
    .prod-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }
    .prod-card { display: flex; flex-direction: column; padding: 0; overflow: hidden; }
    .prod-card .prod-img { aspect-ratio: 1 / 1; border-radius: 0; border: none; border-bottom: 1px solid var(--color-border); }
    .prod-body { padding: 20px; display: flex; flex-direction: column; flex: 1; }
    .prod-cat { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-text-muted); margin-bottom: 8px; }
    .prod-name { font-family: var(--font-script); font-size: var(--text-2xl); color: var(--color-primary); line-height: 1.1; margin-bottom: 14px; }
    .prod-foot { margin-top: auto; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .prod-price { font-family: var(--font-mono); font-size: var(--text-lg); color: var(--color-text); }
    .empty { text-align: center; padding: 80px 20px; color: var(--color-text-muted); border: 1px dashed var(--color-border); border-radius: var(--radius-lg); }
    @media (max-width: 860px) { .prod-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 520px) { .prod-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<section class="section" style="padding-top: 0;">
    <div class="container-aptk">

        <div class="catalog-head">
            <span class="eyebrow">Catálogo</span>
            <h1 class="section-title">{{ $activeCategory?->name ?? 'Toda a loja' }}</h1>
        </div>

        <div class="cat-filter">
            <a href="{{ route('catalog') }}" class="cat-pill {{ ! $activeCategory ? 'is-active' : '' }}">Todos</a>
            @foreach ($categories as $cat)
                <a href="{{ route('catalog', ['categoria' => $cat->slug]) }}"
                   class="cat-pill {{ $activeCategory?->id === $cat->id ? 'is-active' : '' }}">{{ $cat->name }}</a>
            @endforeach
        </div>

        @if ($products->count())
            <div class="prod-grid">
                @foreach ($products as $product)
                    <div class="card-aptk prod-card">
                        <div class="placeholder prod-img"><span>{{ $product->name }}</span></div>
                        <div class="prod-body">
                            <span class="prod-cat">{{ $product->category?->name ?? 'APTK' }}</span>
                            <p class="prod-name">{{ $product->name }}</p>
                            <div class="prod-foot">
                                <span class="prod-price">R$ {{ number_format($product->price, 2, ',', '.') }}</span>
                                <a href="#" class="btn-aptk">Comprar</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $products->links('pagination::bootstrap-5') }}
            </div>
        @else
            <div class="empty">Nenhum produto nesta categoria ainda.</div>
        @endif

    </div>
</section>
@endsection
