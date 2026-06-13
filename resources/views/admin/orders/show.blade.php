@extends('layouts.admin')

@php
    $statusLabels = ['pending'=>'Pendente','paid'=>'Pago','shipped'=>'Enviado','delivered'=>'Entregue','cancelled'=>'Cancelado'];
    $payLabels    = ['pix'=>'Pix','cartao'=>'Cartão','boleto'=>'Boleto'];
    $num          = 'APTK-' . str_pad($order->id, 5, '0', STR_PAD_LEFT);
@endphp

@section('admin_title', 'Pedido ' . $num)

@section('content')
    <a href="{{ route('admin.orders.index') }}" class="back-link" style="display:inline-block; margin-bottom:16px;">← Voltar aos pedidos</a>

    <div class="content-head" style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div>
            <h1>Pedido {{ $num }}</h1>
            <p>{{ $order->created_at->format('d/m/Y \à\s H:i') }}</p>
        </div>
        @include('admin.partials.status', ['status' => $order->status])
    </div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif

    <div class="order-grid">
        <div>
            <div class="panel">
                <div class="panel-head">Itens do pedido</div>
                <table class="data-table">
                    <thead>
                        <tr><th>Produto</th><th>SKU</th><th>Qtd</th><th>Unit.</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($order->items as $it)
                            <tr>
                                <td data-label="Produto">{{ $it->product_name }}</td>
                                <td class="td-muted" data-label="SKU">{{ $it->product_sku ?? '—' }}</td>
                                <td class="td-num" data-label="Qtd">{{ $it->qty }}</td>
                                <td class="td-num" data-label="Unit.">R$ {{ number_format($it->unit_price, 2, ',', '.') }}</td>
                                <td class="td-num" data-label="Total">R$ {{ number_format($it->total_price, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($order->notes)
                <div class="panel">
                    <div class="panel-head">Entrega &amp; observações</div>
                    <div class="panel-body"><p class="addr-note">{{ $order->notes }}</p></div>
                </div>
            @endif
        </div>

        <div>
            <div class="panel">
                <div class="panel-head">Resumo</div>
                <div class="panel-body">
                    <div class="kv"><span class="k">Subtotal</span><span class="v">R$ {{ number_format($order->subtotal, 2, ',', '.') }}</span></div>
                    <div class="kv"><span class="k">Frete</span><span class="v">R$ {{ number_format($order->shipping_cost, 2, ',', '.') }}</span></div>
                    <div class="kv"><span class="k">Desconto</span><span class="v">R$ {{ number_format($order->discount, 2, ',', '.') }}</span></div>
                    <div class="kv"><span class="k">Total</span><span class="v" style="color:var(--color-primary);">R$ {{ number_format($order->total, 2, ',', '.') }}</span></div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">Cliente</div>
                <div class="panel-body">
                    <div class="kv"><span class="k">Nome</span><span class="v" style="font-family:var(--font-body);">{{ $order->user->name ?? '—' }}</span></div>
                    <div class="kv"><span class="k">E-mail</span><span class="v" style="font-family:var(--font-body);">{{ $order->user->email ?? '—' }}</span></div>
                    <div class="kv"><span class="k">Pagamento</span><span class="v">{{ $payLabels[$order->payment_method] ?? ($order->payment_method ?? '—') }}</span></div>
                    <div class="kv"><span class="k">Pag. status</span><span class="v">{{ ucfirst($order->payment_status) }}</span></div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">Alterar status</div>
                <div class="panel-body">
                    <form action="{{ route('admin.orders.update-status', $order) }}" method="POST" style="display:flex; flex-direction:column; gap:12px;">
                        @csrf
                        @method('PATCH')
                        <select name="status" class="admin-select">
                            @foreach ($statuses as $st)
                                <option value="{{ $st }}" {{ $order->status === $st ? 'selected' : '' }}>{{ $statusLabels[$st] ?? $st }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="admin-btn">Salvar status</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
