{{-- Produto sem foto do pacote novo (leva 08): moldura 2:3, a mesma das
     fotos (800×1200), com o símbolo da marca. `compact` some com o texto
     nas miniaturas pequenas (carrinho, checkout, pedidos, admin). --}}
@props(['compact' => false])
<span {{ $attributes->merge(['class' => 'prod-ph'.($compact ? ' prod-ph--compact' : '')]) }} role="img" aria-label="Produto ainda sem foto">
    <x-brand.symbol class="prod-ph__symbol" />
    @unless ($compact)
        <span class="prod-ph__txt">Produto ainda sem foto</span>
    @endunless
</span>
