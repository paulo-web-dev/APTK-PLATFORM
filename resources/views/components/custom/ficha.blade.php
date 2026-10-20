{{-- Ficha da garrafa custom (leva 11): texto, estilo/fonte e cor, ao lado do
     nome do item (carrinho, checkout, pedidos). O admin tem bloco próprio. --}}
@props(['p' => []])
@php $d = app(\App\Services\CustomService::class)->exibir($p ?? []); @endphp
<span {{ $attributes->class('cst-ficha') }}>
  <span class="cst-ficha__texto">“{{ $d['texto'] }}”</span>
  <span>Custom · {{ $d['fonte_nome'] }} ({{ $d['fonte'] }})</span>
  <span><span class="cst-cor-dot" style="background:var({{ $d['cor_token'] }});"></span>{{ $d['cor_nome'] }}</span>
</span>
