{{-- Logo Ice4Pros com a caveira, inline: fill="currentColor", a cor vem
     do `color` do pai (preto na faixa turquesa, branco no fundo preto). --}}
@php
    $svg = file_get_contents(public_path('img/marcas/ice4pros/ice4pros-caveira.svg'));
    $svg = preg_replace('/<svg\b/', '<svg '.$attributes->merge(['class' => 'i4p-caveira']), $svg, 1);
@endphp
{!! $svg !!}
