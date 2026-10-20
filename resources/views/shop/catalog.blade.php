@extends('layouts.public')

@section('title', 'Produtos · APTK Spirits')

@push('styles')
<style>
    .catalog-head { padding-block: 56px 8px; }
    .cat-cover { position: relative; overflow: hidden; border-bottom: 1px solid var(--color-border); }
    .cat-cover .cat-cover-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .cat-cover .cat-cover-overlay { position: absolute; inset: 0; background: linear-gradient(180deg, color-mix(in srgb, var(--color-ink) 52%, transparent), color-mix(in srgb, var(--color-ink) 84%, transparent)); }
    .cat-cover .cat-cover-inner { position: relative; z-index: 1; padding-block: clamp(48px, 9vw, 92px); }
    .cat-cover .eyebrow { margin-bottom: 14px; }
    .cat-cover h1 { font-size: clamp(2.3rem, 5.6vw, 3.8rem); color: var(--color-cream); margin: 0 0 14px; max-width: 16ch; }
    .cat-cover p { color: color-mix(in srgb, var(--color-cream) 82%, transparent); font-size: var(--text-lg); max-width: 600px; margin: 0; }
    .cat-filter { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 36px; }
    .cat-pill { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.08em; text-transform: uppercase; padding: 8px 16px; border-radius: 100px; border: 1px solid var(--color-border); color: var(--color-text-muted); transition: color .2s ease, border-color .2s ease, background-color .2s ease; }
    .cat-pill:hover { color: var(--color-text); border-color: var(--color-primary-muted); }
    .cat-pill.is-active { background: var(--color-primary); color: var(--color-text-inverse); border-color: var(--color-primary); }
    .prod-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }
    .prod-card { display: flex; flex-direction: column; padding: 0; overflow: hidden; }
    .prod-card .prod-img { aspect-ratio: 1 / 1; margin: 10px 10px 0; border-radius: var(--radius-md); border: none; background: var(--color-bg-media); }
    .prod-card img.prod-img, .prod-card .prod-ph-box.prod-img { width: calc(100% - 20px); object-fit: contain; padding: 18px; }
    .prod-body { padding: 20px; display: flex; flex-direction: column; flex: 1; }
    .prod-cat { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-text-muted); margin-bottom: 8px; }
    .prod-name { font-family: var(--font-script); font-size: var(--text-2xl); color: var(--color-primary); line-height: 1.1; margin: 0 0 14px; }
    .prod-foot { margin-top: auto; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .prod-price { font-family: var(--font-mono); font-size: var(--text-lg); color: var(--color-text); }
    .prod-out { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-danger); border: 1px solid currentColor; border-radius: 100px; padding: 4px 10px; white-space: nowrap; }
    .empty { text-align: center; padding: 80px 20px; color: var(--color-text-muted); border: 1px dashed var(--color-border); border-radius: var(--radius-lg); }
    @media (max-width: 860px) { .prod-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 520px) { .prod-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')

@php
    /* Capa da listagem (leva 01): a aba "Todos" também tem capa, como as
       categorias. Sem categoria ativa (ou categoria ainda sem imagem),
       entra a capa padrão — imagem PROVISÓRIA, trocar quando o cliente enviar. */
    $coverImage = ($activeCategory && $activeCategory->image)
        ? \Illuminate\Support\Facades\Storage::url($activeCategory->image)
        : asset('img/aptk/loja-aptk.jpg');
    // Leva 10 (briefing p.16): capa de "Todos os produtos" com o texto novo.
    $coverTitle = $activeCategory?->name ?? 'Alta coquetelaria. Novas possibilidades.';
    $coverText  = $activeCategory?->description
        ?? 'Dos clássicos às criações autorais, descubra um portfólio que combina técnica, criatividade e alma brasileira. Coquetéis prontos para servir e bases para criar, explorar e celebrar do seu jeito.';
@endphp
<section class="cat-cover on-dark">
    <img class="cat-cover-bg" src="{{ $coverImage }}" alt="{{ $coverTitle }}">
    <div class="cat-cover-overlay"></div>
    <div class="container-aptk cat-cover-inner">
        <span class="eyebrow">Explore nossos coquetéis</span>
        <h1 class="title-editorial">{{ $coverTitle }}</h1>
        @if ($coverText)
            <p>{{ $coverText }}</p>
        @endif
    </div>
</section>

<section class="section" style="padding-top: 40px;">
    <div class="container-aptk">

        <div class="cat-filter">
            <a href="{{ route('catalog') }}" class="cat-pill {{ ! $activeCategory ? 'is-active' : '' }}">Todos os produtos</a>
            @foreach ($categories as $cat)
                <a href="{{ route('catalog', ['categoria' => $cat->slug]) }}"
                   class="cat-pill {{ $activeCategory?->id === $cat->id ? 'is-active' : '' }}">{{ $cat->name }}</a>
            @endforeach
        </div>

        @if ($products->count())
            <div class="prod-grid">
                @foreach ($products as $product)
                    <div class="card-aptk prod-card">
                        <a href="{{ route('product', $product->slug) }}" style="text-decoration:none; color:inherit;">
                            <x-product.image class="prod-img" :image="$product->primaryImage" :alt="$product->name" />
                        </a>
                        <div class="prod-body">
                            <span class="prod-cat">{{ $product->category?->name ?? 'APTK' }}</span>
                            <a href="{{ route('product', $product->slug) }}" style="text-decoration:none;">
                                <p class="prod-name">{{ $product->name }}</p>
                            </a>
                            <div class="prod-foot">
                                <span class="prod-price">R$ {{ number_format($product->price, 2, ',', '.') }}</span>
                                {{-- Esgotado continua na vitrine, com selo — não some da lista. --}}
                                @if ($product->isSoldOut())
                                    <span class="prod-out">Esgotado</span>
                                @endif
                                <a href="{{ route('product', $product->slug) }}" class="btn-aptk">Ver</a>
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
