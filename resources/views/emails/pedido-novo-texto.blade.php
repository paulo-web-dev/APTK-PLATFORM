Novo pedido {!! $numero !!}
{!! $data !!} (horário de Brasília)

CLIENTE
{!! $cliente['nome'] !!}
{!! $cliente['email'] !!}
{!! $cliente['telefone'] !!}

ENTREGA
@if ($destinatario)
{!! $destinatario !!}
@endif
@foreach ($endereco as $parte)
{!! $parte !!}
@endforeach
Frete: {!! $frete['nome'] !!} · {!! $frete['valor'] !!}@if ($frete['prazo']) · {!! $frete['prazo'] !!}@endif


ITENS
@foreach ($itens as $it)
- {!! $it['nome'] !!}@if ($it['volume']) · {!! $it['volume'] !!}@endif — {!! $it['qtd'] !!} × {!! $it['unitario'] !!} = {!! $it['subtotal'] !!}
@if ($it['custom'])
  GARRAFA PERSONALIZADA
  Texto: "{!! $it['custom']['texto'] !!}"
  Bebida: {!! $it['custom']['bebida'] !!}
  Fonte: {!! $it['custom']['fonte'] !!}
  Cor: {!! $it['custom']['cor'] !!}
@endif
@endforeach

Subtotal: {!! $subtotal !!}
Desconto: {!! $desconto !!}
Frete: {!! $frete_valor !!}
TOTAL: {!! $total !!}

PAGAMENTO
{!! $pagamento['forma'] !!}@if ($pagamento['parcelas']) · {!! $pagamento['parcelas'] !!}@endif

Status: {!! $pagamento['status'] !!}@if ($pago_em) · {!! $pago_em !!}@endif


Ver pedido no admin: {!! $link_admin !!}
