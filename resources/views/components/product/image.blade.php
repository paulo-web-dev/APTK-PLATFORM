{{-- Imagem de produto ou o placeholder — único caminho para renderizar foto
     de produto (leva 08). `image` aceita ProductImage, path do disco public
     ou null. A classe vai na <img> ou na caixa do placeholder, então o
     layout do lugar que chama não muda. --}}
@props(['image' => null, 'alt' => '', 'compact' => false, 'loading' => 'lazy'])
@php
    $path = $image instanceof \App\Models\ProductImage ? $image->path : $image;
@endphp
@if (filled($path))
    <img {{ $attributes }} src="{{ \Illuminate\Support\Facades\Storage::url($path) }}" alt="{{ $alt }}" @if ($loading) loading="{{ $loading }}" @endif>
@else
    <span {{ $attributes->merge(['class' => 'prod-ph-box']) }}>
        <span class="prod-ph-area"><x-product.placeholder :compact="$compact" /></span>
    </span>
@endif
