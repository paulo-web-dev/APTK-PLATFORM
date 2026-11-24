@extends('layouts.admin')

@section('admin_title', 'Cupom '.$coupon->code)

@section('content')
    <a href="{{ route('admin.coupons.index') }}" class="back-link" style="display:inline-block; margin-bottom:16px;">← Voltar aos cupons</a>

    <div class="content-head" style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div>
            <h1>Cupom {{ $coupon->code }}</h1>
            <p>{{ $coupon->name ?: 'Sem descrição interna' }}</p>
        </div>
        <a href="{{ route('admin.coupons.edit', $coupon) }}" class="admin-btn">Editar</a>
    </div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif

    <div class="order-grid">
        <div>
            <div class="panel">
                <div class="panel-head">Resultado</div>
                <div class="panel-body">
                    <div class="kv"><span class="k">Usos</span><span class="v">{{ $usos }}{{ $coupon->max_uses ? ' / '.$coupon->max_uses : '' }}</span></div>
                    <div class="kv"><span class="k">Pedidos pagos</span><span class="v">{{ $pagos }}</span></div>
                    <div class="kv"><span class="k">Faturamento (pagos)</span><span class="v">{{ moeda_br($faturamento) }}</span></div>
                    <div class="kv"><span class="k">Desconto concedido (pagos)</span><span class="v">{{ moeda_br($desconto) }}</span></div>
                    <p class="td-muted" style="font-size:var(--text-xs); margin:12px 0 0;">Uso = pedido com o cupom que não foi cancelado (inclui Pix/boleto aguardando pagamento).</p>
                    <a href="{{ route('admin.orders.index', ['cupom' => $coupon->code]) }}" class="admin-btn admin-btn--ghost" style="margin-top:14px;">Ver todos os pedidos com o cupom</a>
                </div>
            </div>

            <div class="data-card" style="margin-top:20px;">
                <table class="data-table">
                    <thead><tr><th>Pedido</th><th>Cliente</th><th>Data</th><th>Desconto</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($pedidos as $o)
                            <tr>
                                <td class="td-id" data-label="Pedido"><a href="{{ route('admin.orders.show', $o) }}" style="color:inherit;">APTK-{{ str_pad($o->id, 5, '0', STR_PAD_LEFT) }}</a></td>
                                <td data-label="Cliente">{{ $o->user->name ?? $o->ship_name ?? '—' }}</td>
                                <td class="td-muted" data-label="Data">{{ data_br($o->created_at) }}</td>
                                <td class="td-num" data-label="Desconto">−{{ moeda_br($o->discount) }}</td>
                                <td class="td-num" data-label="Total">{{ moeda_br($o->total) }}</td>
                                <td data-label="Status">@include('admin.partials.status', ['order' => $o])</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="td-muted" style="text-align:center; padding:36px;">Nenhum pedido com este cupom ainda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <div class="panel">
                <div class="panel-head">Regras</div>
                <div class="panel-body">
                    <div class="kv"><span class="k">Situação</span><span class="v">{{ ucfirst($coupon->situacao()) }}</span></div>
                    <div class="kv"><span class="k">Desconto</span><span class="v">{{ $coupon->rotuloValor() }} sobre os produtos</span></div>
                    <div class="kv"><span class="k">Válido de</span><span class="v">{{ $coupon->starts_at ? data_br($coupon->starts_at) : '—' }}</span></div>
                    <div class="kv"><span class="k">Válido até</span><span class="v">{{ $coupon->expires_at ? data_br($coupon->expires_at) : '—' }}</span></div>
                    <div class="kv"><span class="k">Só primeira compra</span><span class="v">{{ $coupon->first_purchase_only ? 'Sim' : 'Não' }}</span></div>
                    <div class="kv"><span class="k">Usos por cliente</span><span class="v">{{ $coupon->max_per_customer ?? 'ilimitado' }}</span></div>
                    <div class="kv"><span class="k">Limite total</span><span class="v">{{ $coupon->max_uses ?? 'ilimitado' }}</span></div>
                    <div class="kv"><span class="k">Pedido mínimo</span><span class="v">{{ $coupon->min_order > 0 ? moeda_br($coupon->min_order) : '—' }}</span></div>
                    <div class="kv"><span class="k">Link</span><span class="v" style="font-size:var(--text-xs); word-break:break-all;">{{ rtrim(config('app.url'), '/') }}/?cupom={{ $coupon->code }}</span></div>
                </div>
            </div>
        </div>
    </div>
@endsection
