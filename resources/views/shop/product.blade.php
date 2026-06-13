@extends('layouts.public')

@section('title', $product->name . ' · APTK Spirits')

@push('styles')
<style>
    .pdp { padding-block: 48px; }
    .pdp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 56px; align-items: start; }
    .pdp-gallery .main-img { aspect-ratio: 1 / 1; }
    .pdp-thumbs { display: flex; gap: 12px; margin-top: 14px; }
    .pdp-thumbs .thumb { width: 72px; height: 72px; flex-shrink: 0; aspect-ratio: 1 / 1; }
    .pdp-breadcrumb { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-text-muted); margin-bottom: 24px; }
    .pdp-breadcrumb a { color: var(--color-text-muted); }
    .pdp-breadcrumb a:hover { color: var(--color-primary); }
    .pdp-name { font-family: var(--font-script); font-size: clamp(2.5rem, 6vw, 3.75rem); color: var(--color-primary); line-height: 1.05; margin-bottom: 16px; }
    .pdp-sub { font-family: var(--font-display); font-style: italic; font-size: var(--text-xl); color: var(--color-text); margin: 0 0 22px; }
    .pdp-price-row { display: flex; align-items: baseline; gap: 14px; margin-bottom: 26px; }
    .pdp-price { font-family: var(--font-mono); font-size: var(--text-3xl); color: var(--color-text); }
    .pdp-compare { font-family: var(--font-mono); font-size: var(--text-lg); color: var(--color-text-muted); text-decoration: line-through; }
    .pdp-desc { color: var(--color-text-muted); line-height: 1.7; margin-bottom: 28px; }
    .pdp-specs { display: flex; gap: 32px; flex-wrap: wrap; padding: 22px 0; border-block: 1px solid var(--color-border); margin-bottom: 28px; }
    .pdp-specs div { display: flex; flex-direction: column; gap: 4px; }
    .pdp-specs .k { font-size: var(--text-xs); color: var(--color-text-muted); letter-spacing: 0.1em; text-transform: uppercase; }
    .pdp-specs .v { font-family: var(--font-mono); color: var(--color-text); }
    .pdp-buy { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; }
    .stock-ok { color: var(--color-success); }
    .stock-out { color: var(--color-danger); }
    @media (max-width: 860px) { .pdp-grid { grid-template-columns: 1fr; gap: 32px; } }
</style>
@endpush

@section('content')
<section class="pdp">
    <div class="container-aptk">

        <div class="pdp-breadcrumb">
            <a href="{{ route('home') }}">Início</a> /
            <a href="{{ route('catalog') }}">Loja</a>
            @if ($product->category)
                / <a href="{{ route('catalog', ['categoria' => $product->category->slug]) }}">{{ $product->category->name }}</a>
            @endif
        </div>

        <div class="pdp-grid">
            {{-- Galeria --}}
            <div class="pdp-gallery">
                @if ($product->images->isNotEmpty())
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($product->images->first()->path) }}"
                         alt="{{ $product->name }}" class="main-img"
                         style="width:100%; border:1px solid var(--color-border); border-radius:var(--radius-md);">
                    @if ($product->images->count() > 1)
                        <div class="pdp-thumbs">
                            @foreach ($product->images as $img)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($img->path) }}" alt=""
                                     class="thumb" style="object-fit:cover; border:1px solid var(--color-border); border-radius:var(--radius-sm);">
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="placeholder main-img"><span>{{ $product->name }}</span></div>
                    <div class="pdp-thumbs">
                        <div class="placeholder thumb"></div>
                        <div class="placeholder thumb"></div>
                        <div class="placeholder thumb"></div>
                    </div>
                @endif
            </div>

            {{-- Detalhes --}}
            <div class="pdp-body">
                @if ($product->featured)
                    <span class="badge-aptk" style="margin-bottom:16px;">Edição limitada</span>
                @endif

                <h1 class="pdp-name">{{ $product->name }}</h1>

                @if ($product->short_description)
                    <p class="pdp-sub">{{ $product->short_description }}</p>
                @endif

                <div class="pdp-price-row">
                    <span class="pdp-price">R$ {{ number_format($product->price, 2, ',', '.') }}</span>
                    @if ($product->compare_price && $product->compare_price > $product->price)
                        <span class="pdp-compare">R$ {{ number_format($product->compare_price, 2, ',', '.') }}</span>
                    @endif
                </div>

                @if ($product->description)
                    <div class="pdp-desc">{{ $product->description }}</div>
                @endif

                <div class="pdp-specs">
                    @if ($product->sku)
                        <div><span class="k">SKU</span><span class="v">{{ $product->sku }}</span></div>
                    @endif
                    <div>
                        <span class="k">Disponibilidade</span>
                        <span class="v">
                            @if ($product->stock_qty > 0)
                                <span class="stock-ok">Em estoque</span>
                            @else
                                <span class="stock-out">Esgotado</span>
                            @endif
                        </span>
                    </div>
                </div>

                <div class="pdp-buy">
                    @if ($product->stock_qty > 0)
                        <form action="{{ route('cart.add') }}" method="POST" style="display:inline;">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <button type="submit" class="btn-aptk">Adicionar ao carrinho</button>
                        </form>
                    @else
                        <button class="btn-aptk" disabled style="opacity:.5; cursor:not-allowed;">Esgotado</button>
                    @endif
                    <a href="{{ route('catalog') }}" class="btn-aptk btn-aptk--outline">Voltar à loja</a>
                </div>
            </div>
        </div>

        {{-- Relacionados --}}
        @if ($related->isNotEmpty())
            <div style="margin-top:72px;">
                <span class="eyebrow">Você também pode gostar</span>
                <h2 class="section-title" style="margin-bottom:32px;">Da mesma categoria</h2>
                <div class="row g-3">
                    @foreach ($related as $rel)
                        <div class="col-12 col-md-4">
                            <a href="{{ route('product', $rel->slug) }}" class="card-aptk"
                               style="display:flex; flex-direction:column; padding:0; overflow:hidden; text-decoration:none;">
                                <div class="placeholder" style="aspect-ratio:1/1; border:none; border-bottom:1px solid var(--color-border); border-radius:0;"><span>{{ $rel->name }}</span></div>
                                <div style="padding:18px;">
                                    <p style="font-family:var(--font-script); font-size:var(--text-xl); color:var(--color-primary); margin:0 0 8px;">{{ $rel->name }}</p>
                                    <span style="font-family:var(--font-mono); color:var(--color-text);">R$ {{ number_format($rel->price, 2, ',', '.') }}</span>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</section>
@endsection
