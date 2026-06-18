@props(['tag' => false])
<span {{ $attributes->merge(['class' => 'aptk-logo']) }}>
  <x-brand.symbol class="aptk-logo__mark" />
  <span class="aptk-logo__word">APTK</span>
  @if($tag)<span class="aptk-logo__tag">Spirits</span>@endif
</span>
