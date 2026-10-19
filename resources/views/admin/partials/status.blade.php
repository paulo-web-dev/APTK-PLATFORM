@php
    // Rótulo: mapa central Order::STATUS_LABELS. Passe 'order' (preferível:
    // "Estornado" depende do pagamento) ou só 'status'.
    $exibido = isset($order) ? $order->statusExibido() : (($status ?? '') === 'paid' ? 'processing' : ($status ?? ''));
    $cls = [
        'pending'    => 'status--pendente',
        'processing' => 'status--pago',
        'shipped'    => 'status--enviado',
        'delivered'  => 'status--entregue',
        'cancelled'  => 'status--cancelado',
        'refunded'   => 'status--cancelado',
    ][$exibido] ?? 'status--pendente';
@endphp
<span class="status {{ $cls }}">{{ \App\Models\Order::rotuloStatus($exibido) }}</span>
