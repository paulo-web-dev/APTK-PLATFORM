{{-- Ficha da garrafa custom (leva 11): texto e estilo/fonte, ao lado do nome
     do item (carrinho, checkout, pedidos). A cor é fixa (preto) e só aparece
     no bloco do admin. --}}
@props(['p' => []])
@php $d = app(\App\Services\CustomService::class)->exibir($p ?? []); @endphp
<span {{ $attributes->class('cst-ficha') }}>
  <span class="cst-ficha__texto">“{{ $d['texto'] }}”</span>
  <span>Custom · {{ $d['fonte_nome'] }} ({{ $d['fonte'] }})</span>
</span>
