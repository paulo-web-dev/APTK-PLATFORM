@php
    $map = [
        'pending'   => ['Pendente',  'status--pendente'],
        'paid'      => ['Pago',      'status--pago'],
        'shipped'   => ['Enviado',   'status--enviado'],
        'delivered' => ['Entregue',  'status--entregue'],
        'cancelled' => ['Cancelado', 'status--cancelado'],
    ];
    [$label, $cls] = $map[$status] ?? [ucfirst($status), 'status--pendente'];
@endphp
<span class="status {{ $cls }}">{{ $label }}</span>
