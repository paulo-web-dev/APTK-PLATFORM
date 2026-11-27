@props(['eyebrow' => null, 'produtos' => null])
{{-- Encerramento comercial da Ice4Pros (leva 21): o mesmo banner na LP e no
     portfólio. Faixa turquesa, wordmark em preto (mesma versão do topo) e os
     dois botões em contorno preto, legíveis em todos os estados. --}}
<section {{ $attributes->merge(['class' => 'i4p-section i4p-closing i4p-faixa']) }}>
  <div class="i4p-container">
    <x-ice4pros.wordmark mono class="i4p-closing-logo" />
    @if ($eyebrow)
      <span class="i4p-eyebrow">{{ $eyebrow }}</span>
    @endif
    <h2>Leve gelo em estado de arte para o seu negócio.</h2>
    <p>Conheça as soluções Ice4Pros para bares, restaurantes, hotéis, eventos e revendas. Nosso time está preparado para entender as necessidades do seu negócio e oferecer as melhores condições comerciais.</p>
    <div class="i4p-ctas">
      <a href="{{ whatsapp_link(config('marcas.marcas.ice4pros.whatsapp_mensagem'), 'ice4pros') }}" target="_blank" rel="noopener" class="i4p-btn i4p-btn--contorno-preto">Falar com o comercial</a>
      <a href="{{ $produtos ?? marca_url('ice4pros', 'produtos') }}" class="i4p-btn i4p-btn--contorno-preto">Ver produtos</a>
    </div>
  </div>
</section>
