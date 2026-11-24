{{-- Forms do cupom FORA de qualquer outro form (no checkout o resumo fica
     dentro do #checkoutForm; os campos do cupom apontam pra cá via form=""). --}}
<form id="cupomAplicarForm" action="{{ route('cart.coupon.apply') }}" method="POST" hidden>@csrf</form>
<form id="cupomRemoverForm" action="{{ route('cart.coupon.remove') }}" method="POST" hidden>@csrf @method('DELETE')</form>
