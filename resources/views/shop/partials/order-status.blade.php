@php
    // Rótulo: mapa central Order::STATUS_LABELS (mesmo texto do admin).
    $exibido = isset($order) ? $order->statusExibido() : (($status ?? '') === 'paid' ? 'processing' : ($status ?? ''));
    $cls = [
        'pending'    => 's-pending',
        'processing' => 's-paid',
        'shipped'    => 's-shipped',
        'delivered'  => 's-delivered',
        'cancelled'  => 's-cancelled',
        'refunded'   => 's-cancelled',
    ][$exibido] ?? 's-pending';
@endphp
<span class="ord-status {{ $cls }}">{{ \App\Models\Order::rotuloStatus($exibido) }}</span>
