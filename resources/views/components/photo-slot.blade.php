{{-- Bloco reservado para foto que ainda não chegou (leva 10). Só em bloco
     secundário: lugar de destaque usa foto existente. Quem chama deixa ao
     lado o comentário {{-- FOTO: descrição do briefing --}}. `ratio` vira
     aspect-ratio (ex.: "4/5"). --}}
@props(['ratio' => '4/3'])
<span {{ $attributes->merge(['class' => 'photo-slot', 'style' => '--ps-ratio: '.$ratio]) }} role="img" aria-label="Foto em breve">
    <x-brand.symbol class="ps-symbol" />
</span>
