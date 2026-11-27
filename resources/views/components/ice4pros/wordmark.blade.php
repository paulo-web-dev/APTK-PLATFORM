@props(['mono' => false])
{{-- Wordmark Ice4Pros inline (leva 21). Original: branco com o "4" turquesa.
     mono = tudo em currentColor (preto sobre a faixa turquesa, onde o "4"
     turquesa sumiria). --}}
@php
    $svg = file_get_contents(public_path('img/marcas/ice4pros/ice4pros-wordmark.svg'));
    if ($mono) {
        $svg = preg_replace('/fill="#[0-9A-Fa-f]{6}"/', 'fill="currentColor"', $svg);
    }
    $svg = str_replace('aria-label="ICE4PROs"', 'aria-label="Ice4Pros"', $svg);
    $svg = preg_replace('/<svg\b/', '<svg '.$attributes->merge(['class' => 'i4p-wordmark']), $svg, 1);
@endphp
{!! $svg !!}
