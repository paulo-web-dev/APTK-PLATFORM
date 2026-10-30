@extends('layouts.public')

@section('title', $product->name . ' · APTK Spirits')

@push('styles')
<style>
    .pdp { padding-block: 48px; }
    .pdp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 56px; align-items: start; }
    .pdp-gallery .main-img { display: block; width: 100%; aspect-ratio: 1 / 1; object-fit: contain; object-position: center; background: var(--media-bg, var(--color-bg-media)); padding: 22px; border: 1px solid var(--color-border); border-radius: var(--radius-md); }
    .pdp-thumbs { display: flex; gap: 12px; margin-top: 14px; }
    .pdp-thumbs .thumb { width: 72px; height: 72px; flex-shrink: 0; padding: 0; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--media-bg, var(--color-bg-media)); cursor: pointer; overflow: hidden; transition: border-color .2s ease; }
    .pdp-thumbs .thumb img { width: 100%; height: 100%; object-fit: contain; display: block; background: var(--media-bg, var(--color-bg-media)); }
    .pdp-thumbs .thumb:hover { border-color: var(--color-primary-muted); }
    .pdp-thumbs .thumb.is-active { border-color: var(--color-primary); }
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
    .pdp-specs .v.sizes { display: flex; gap: 6px; flex-wrap: wrap; }
    /* Seletor de volume (leva 01): cliente escolhe o ML e o preço muda. */
    .size-chip-btn { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.06em; color: var(--color-text-muted); background: transparent; border: 1px solid var(--color-border); border-radius: 100px; padding: 7px 14px; cursor: pointer; transition: color .2s ease, border-color .2s ease, background-color .2s ease; }
    .size-chip-btn:hover { color: var(--color-text); border-color: var(--color-primary-muted); }
    .size-chip-btn.is-active { background: var(--color-primary); border-color: var(--color-primary); color: var(--color-text-inverse); }
    /* Volume esgotado continua visível, mas inerte (estoque por volume, fase 1.5). */
    .size-chip-btn.is-out { opacity: .45; text-decoration: line-through; cursor: not-allowed; }
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
            @php
                // Pré-seleciona um volume COM estoque quando o padrão esgotou.
                $selectedSize = $product->defaultAvailableSize();
                // Galeria por volume (leva 18): as fotos do volume, senão as gerais.
                $fotos = $product->fotosDoVolume($selectedSize);
                $paraJs = fn ($lista) => $lista->map(fn ($img) => [
                    'src' => \Illuminate\Support\Facades\Storage::url($img->path),
                    'fundo' => $img->corFundo(),
                ])->values();
                $fotosPorVolume = collect($product->sizes ?: [])
                    ->mapWithKeys(fn ($sz) => [$sz => $paraJs($product->fotosDoVolume($sz))]);
            @endphp

            {{-- Galeria --}}
            <div class="pdp-gallery">
                @if ($fotos->isNotEmpty())
                    <x-product.image id="pdpMain" class="main-img" :image="$fotos->first()" :alt="$product->name" loading="" />
                    <div class="pdp-thumbs" id="pdpThumbs" @if ($fotos->count() < 2) hidden @endif>
                        @foreach ($fotos as $img)
                            <button type="button" class="thumb {{ $loop->first ? 'is-active' : '' }}"
                                    data-src="{{ \Illuminate\Support\Facades\Storage::url($img->path) }}"
                                    data-fundo="{{ $img->corFundo() }}"
                                    aria-label="Ver imagem {{ $loop->iteration }}">
                                <x-product.image :image="$img" alt="" />
                            </button>
                        @endforeach
                    </div>
                    @if ($fotosPorVolume->isNotEmpty())
                        <script type="application/json" id="pdpFotos">@json($fotosPorVolume)</script>
                    @endif
                @else
                    <x-product.image class="main-img" :alt="$product->name" />
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
                    <span class="pdp-price" id="pdpPrice">R$ {{ number_format($product->priceForSize($selectedSize), 2, ',', '.') }}</span>
                    @if ($product->compare_price && $product->compare_price > $product->price)
                        <span class="pdp-compare">R$ {{ number_format($product->compare_price, 2, ',', '.') }}</span>
                    @endif
                </div>

                @if ($product->description)
                    <div class="pdp-desc">{{ $product->description }}</div>
                @endif

                <div class="pdp-specs">
                    @if ($product->base)
                        <div><span class="k">Base</span><span class="v">{{ $product->base }}</span></div>
                    @endif
                    @if ($product->abv)
                        <div><span class="k">Teor</span><span class="v">{{ $product->abv }}% vol.</span></div>
                    @endif
                    @if ($product->hasSizes())
                        <div>
                            <span class="k">Volume</span>
                            <span class="v sizes" id="pdpSizePicker">
                                @foreach ($product->sizeOptions() as $sz => $szPrice)
                                    @php $esgotado = $product->isSizeOutOfStock($sz); @endphp
                                    <button type="button"
                                            class="size-chip-btn {{ $sz === $selectedSize ? 'is-active' : '' }} {{ $esgotado ? 'is-out' : '' }}"
                                            @disabled($esgotado)
                                            data-size="{{ $sz }}"
                                            data-price="{{ number_format($szPrice, 2, ',', '.') }}">{{ $sz }}{{ $esgotado ? ' — esgotado' : '' }}</button>
                                @endforeach
                            </span>
                        </div>
                    @endif
                    @if ($product->sku)
                        <div><span class="k">SKU</span><span class="v">{{ $product->sku }}</span></div>
                    @endif
                    <div>
                        <span class="k">Disponibilidade</span>
                        <span class="v">
                            @if (! $product->isSoldOut())
                                <span class="stock-ok">Em estoque</span>
                            @else
                                <span class="stock-out">Esgotado</span>
                            @endif
                        </span>
                    </div>
                </div>

                <div class="pdp-buy">
                    @if (! $product->isSoldOut())
                        <form action="{{ route('cart.add') }}" method="POST" style="display:inline;">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <input type="hidden" name="size" id="pdpSizeInput" value="{{ $selectedSize }}">
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
                                <x-product.image :image="$rel->fotoPadrao()" :alt="$rel->name"
                                    style="aspect-ratio:1/1; width:calc(100% - 20px); margin:10px 10px 0; object-fit:contain; background:var(--media-bg, var(--color-bg-media)); border:none; border-radius:var(--radius-md); padding:16px;" />
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

@push('scripts')
<script>
  // Galeria: miniatura troca a foto principal; o seletor de volume troca a
  // galeria inteira (window.pdpGaleria, leva 18). Moldura acompanha a foto
  // (fundo vazio = fundo padrão).
  (function () {
    var main = document.getElementById('pdpMain');
    var box = document.getElementById('pdpThumbs');
    if (!main || !box) return;
    var fundo = function (f) { return f || 'var(--color-bg-media)'; };

    var mostrar = function (btn) {
      main.src = btn.getAttribute('data-src');
      main.style.setProperty('--media-bg', fundo(btn.getAttribute('data-fundo')));
      box.querySelectorAll('.thumb').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
    };
    box.addEventListener('click', function (e) {
      var btn = e.target.closest('.thumb');
      if (btn) mostrar(btn);
    });

    var dados = document.getElementById('pdpFotos');
    var porVolume = dados ? JSON.parse(dados.textContent) : {};
    window.pdpGaleria = function (volume) {
      var fotos = porVolume[volume];
      if (!fotos || !fotos.length) return;
      box.innerHTML = '';
      fotos.forEach(function (f, i) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'thumb';
        btn.setAttribute('data-src', f.src);
        btn.setAttribute('data-fundo', f.fundo || '');
        btn.setAttribute('aria-label', 'Ver imagem ' + (i + 1));
        var img = document.createElement('img');
        img.src = f.src;
        img.alt = '';
        if (f.fundo) img.style.setProperty('--media-bg', f.fundo);
        btn.appendChild(img);
        box.appendChild(btn);
      });
      box.hidden = fotos.length < 2;
      mostrar(box.firstChild);
    };
  })();

  // Seletor de volume: atualiza preço exibido + input enviado ao carrinho.
  (function () {
    var picker = document.getElementById('pdpSizePicker');
    if (!picker) return;
    var priceEl = document.getElementById('pdpPrice');
    var sizeInput = document.getElementById('pdpSizeInput');
    picker.querySelectorAll('.size-chip-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        picker.querySelectorAll('.size-chip-btn').forEach(function (b) { b.classList.remove('is-active'); });
        btn.classList.add('is-active');
        if (priceEl) priceEl.textContent = 'R$ ' + btn.getAttribute('data-price');
        if (sizeInput) sizeInput.value = btn.getAttribute('data-size');
        if (window.pdpGaleria) window.pdpGaleria(btn.getAttribute('data-size'));
      });
    });
  })();
</script>
@endpush
