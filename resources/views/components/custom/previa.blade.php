{{-- Prévia da garrafa custom (leva 11): imagem da bebida com o texto sobreposto
     na área em branco do rótulo (config/custom.php). Mesmo componente no
     configurador, carrinho, checkout, pedidos e admin. O texto sai sempre
     escapado. CSS em aptk-produto.css (carrega também no admin).
     Uso: <x-custom.previa :p="$item->personalizacao" class="..." /> --}}
@props(['p' => [], 'id' => null])
@php
    $d = app(\App\Services\CustomService::class)->exibir($p ?? []);
    [$ax, $ay, $aw, $ah] = $d['area'];
@endphp
<div {{ $attributes->class('cst-previa') }} @if ($id) id="{{ $id }}" @endif style="aspect-ratio: {{ $d['proporcao'] }};">
  @if ($d['imagem'])
    <img class="cst-previa__img" src="{{ $d['imagem'] }}" alt="Prévia da garrafa personalizada" loading="lazy">
  @endif
  <span class="cst-previa__area" style="left:{{ $ax }}%; top:{{ $ay }}%; width:{{ $aw }}%; height:{{ $ah }}%;">
    <span class="cst-previa__txt {{ $d['fonte_classe'] }}" style="font-size:{{ $d['tamanho'] }}cqw; color:var({{ $d['cor_token'] }});">{{ $d['texto'] }}</span>
  </span>
</div>
