@php
    $m = [
        'pending'   => ['Pendente',  's-pending'],
        'paid'      => ['Pago',      's-paid'],
        'shipped'   => ['Enviado',   's-shipped'],
        'delivered' => ['Entregue',  's-delivered'],
        'cancelled' => ['Cancelado', 's-cancelled'],
    ];
    [$label, $cls] = $m[$status] ?? [ucfirst($status), 's-pending'];
@endphp
<span class="ord-status {{ $cls }}">{{ $label }}</span>
