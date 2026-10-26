{{-- Grade de portfólio (leva 14): foto 4:5 + nome da marca. 4 colunas no
     desktop, 2 no celular. Itens de config/collabs.php já filtrados
     (PageController::collabs). --}}
@props(['itens'])
<ul {{ $attributes->merge(['class' => 'portfolio-grid']) }}>
  @foreach ($itens as $item)
    <li>
      <figure class="portfolio-item">
        <img src="{{ asset($item['imagem']) }}" alt="Collab APTK com {{ $item['nome'] }}" loading="lazy">
        <figcaption>{{ $item['nome'] }}</figcaption>
      </figure>
    </li>
  @endforeach
</ul>
