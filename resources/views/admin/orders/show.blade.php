@extends('layouts.admin')

@php
    $payLabels    = ['pix'=>'Pix','cartao'=>'Cartão','boleto'=>'Boleto'];
    $num          = 'APTK-' . str_pad($order->id, 5, '0', STR_PAD_LEFT);
    // Dados pra cadastro na Linx: o do pedido (snapshot) manda; pedido antigo cai no cadastro.
    $cpfCliente   = cpf_formatado($order->ship_cpf ?: $order->user?->cpf);
    $telCliente   = $order->ship_phone ?: $order->user?->phone;
@endphp

@section('admin_title', 'Pedido ' . $num)

@push('styles')
<style>
    .adm-cst { border-color: var(--color-primary); }
    .adm-cst__body { display: grid; grid-template-columns: 220px 1fr; gap: 24px; align-items: center; }
    .adm-cst__previa { width: 100%; }
    .adm-copiar { display: inline-flex; align-items: center; gap: 8px; }
    .adm-copiar button { font: inherit; font-size: var(--text-xs); padding: 2px 8px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: transparent; color: var(--color-text-muted); cursor: pointer; }
    .adm-copiar button:hover { color: var(--color-text); border-color: var(--color-text); }
    .adm-cst__texto { font-size: 1.6rem; line-height: 1.25; color: var(--color-text); margin: 0 0 14px; overflow-wrap: anywhere; }
    @media (max-width: 640px) { .adm-cst__body { grid-template-columns: 1fr; } .adm-cst__previa { max-width: 240px; } }
</style>
@endpush

@section('content')
    <a href="{{ route('admin.orders.index') }}" class="back-link" style="display:inline-block; margin-bottom:16px;">← Voltar aos pedidos</a>

    <div class="content-head" style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div>
            <h1>Pedido {{ $num }}</h1>
            <p>{{ data_br($order->created_at) }}</p>
        </div>
        @include('admin.partials.status', ['order' => $order])
    </div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert-ok" style="color:var(--color-danger); border-left-color:var(--color-danger);">{{ session('error') }}</div>
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
                                <td data-label="Produto">{{ $it->product_name }}@if ($it->size) <span class="td-muted">· {{ $it->size }}</span>@endif @if ($it->isCustom()) <span class="cst-selo">Custom</span>@endif</td>
                                <td class="td-muted" data-label="SKU">{{ $it->product_sku ?? '—' }}</td>
                                <td class="td-num" data-label="Qtd">{{ $it->qty }}</td>
                                <td class="td-num" data-label="Unit.">R$ {{ number_format($it->unit_price, 2, ',', '.') }}</td>
                                <td class="td-num" data-label="Total">R$ {{ number_format($it->total_price, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Garrafa custom (leva 11): o que vai impresso no rótulo, em destaque. --}}
            @foreach ($order->items->filter->isCustom() as $it)
                @php $d = app(\App\Services\CustomService::class)->exibir($it->personalizacao); @endphp
                <div class="panel adm-cst">
                    <div class="panel-head">Personalização · {{ $it->nomeExibido() }} · {{ $it->qty }}× {{ $it->size }}</div>
                    <div class="panel-body adm-cst__body">
                        <x-custom.previa class="adm-cst__previa" :p="$it->personalizacao" />
                        <div class="adm-cst__dados">
                            <p class="adm-cst__texto">“{{ $d['texto'] }}”</p>
                            <div class="kv"><span class="k">Bebida</span><span class="v">{{ $it->nomeExibido() }} <span class="td-muted">({{ $d['bebida'] }})</span></span></div>
                            <div class="kv"><span class="k">Fonte</span><span class="v">{{ $d['fonte'] }} <span class="td-muted">· {{ $d['fonte_nome'] }}</span></span></div>
                            <div class="kv"><span class="k">Cor</span><span class="v"><span class="cst-cor-dot" style="background:var({{ $d['cor_token'] }});"></span>{{ $d['cor_nome'] }}@if ($d['cor_hex']) <span class="td-muted">· {{ $d['cor_hex'] }}</span>@endif</span></div>
                            <div class="kv"><span class="k">Caracteres</span><span class="v">{{ mb_strlen($d['texto']) }}</span></div>
                        </div>
                    </div>
                </div>
            @endforeach

            @if ($order->notes)
                <div class="panel">
                    <div class="panel-head">Entrega &amp; observações</div>
                    <div class="panel-body"><p class="addr-note">{{ $order->notes }}</p></div>
                </div>
            @endif
        </div>

        <div>
            @if ($order->appmax_order_id || filled($order->payment_details['erro_gateway'] ?? null) || $order->incidentes())
            <div class="panel">
                <div class="panel-head">Pagamento · Appmax</div>
                <div class="panel-body">
                    @foreach ($order->incidentes() as $incidente)
                        <div class="alert-ok" style="color:var(--color-danger); border-left-color:var(--color-danger); margin-bottom:12px;">
                            <strong>Incidente ({{ $incidente['em'] }}):</strong> {{ $incidente['texto'] }}
                        </div>
                    @endforeach
                    @if (\App\Services\ReconciliacaoPagamento::precisaConferir($order))
                        <div class="alert-ok" style="color:var(--color-danger); border-left-color:var(--color-danger); margin-bottom:12px;">
                            Pagamento a conferir: passou a janela de reconciliação automática sem resposta conclusiva da Appmax. Confira no painel da Appmax ou rode <code>php artisan appmax:diagnostico --pedido={{ $order->id }}</code>.
                        </div>
                    @endif
                    @if ($order->pagamentoEmVerificacao())
                        <div class="alert-ok" style="margin-bottom:12px;">
                            Pagamento em verificação: a cobrança do cartão não teve resposta da Appmax. A consulta automática (a cada 15 min) ou o webhook resolve.
                        </div>
                    @endif
                    @if ($order->appmax_order_id)
                        <div class="kv"><span class="k">Pedido Appmax</span><span class="v">#{{ $order->appmax_order_id }}</span></div>
                    @endif
                    @if ($order->appmax_pay_reference)
                        <div class="kv"><span class="k">Referência</span><span class="v">{{ $order->appmax_pay_reference }}</span></div>
                    @endif
                    <div class="kv"><span class="k">Status pgto.</span><span class="v">{{ ['pending' => 'Aguardando', 'paid' => 'Pago', 'failed' => 'Recusado', 'expired' => 'Expirado', 'refunded' => 'Estornado', 'chargeback' => 'Chargeback'][$order->payment_status] ?? ucfirst($order->payment_status) }}</span></div>
                    @if ($order->paid_at)
                        <div class="kv"><span class="k">Pago em</span><span class="v">{{ data_br($order->paid_at) }}</span></div>
                    @endif
                    @if (($order->payment_details['type'] ?? null) === 'cartao')
                        <div class="kv"><span class="k">Parcelas</span><span class="v">{{ $order->payment_details['installments'] ?? 1 }}×</span></div>
                    @endif
                    @if (filled($order->payment_details['erro_gateway'] ?? null))
                        {{-- Texto cru da Appmax: só aqui, nunca na tela do cliente. --}}
                        <div class="kv"><span class="k">Erro do gateway</span><span class="v" style="font-family:var(--font-mono); font-size:var(--text-xs); white-space:normal; text-align:right;">{{ $order->payment_details['erro_gateway'] }}</span></div>
                    @endif

                    @if ($order->isPaid())
                        <form action="{{ route('admin.orders.refund', $order) }}" method="POST" style="margin-top:14px;"
                              onsubmit="return confirm('Estornar o valor TOTAL deste pedido na Appmax?');">
                            @csrf
                            <button type="submit" class="btn-aptk btn-aptk--outline" style="width:100%; color:var(--color-danger); border-color:var(--color-danger);">Estornar via Appmax</button>
                        </form>
                    @endif
                </div>
            </div>
            @endif

            <div class="panel">
                <div class="panel-head">Resumo</div>
                <div class="panel-body">
                    <div class="kv"><span class="k">Subtotal</span><span class="v">R$ {{ number_format($order->subtotal, 2, ',', '.') }}</span></div>
                    <div class="kv"><span class="k">Frete</span><span class="v">R$ {{ number_format($order->shipping_cost, 2, ',', '.') }}</span></div>
                    <div class="kv"><span class="k">Opção de frete</span><span class="v">{{ $order->shippingLabel() ?? '—' }}</span></div>
                    <div class="kv"><span class="k">Desconto</span><span class="v">R$ {{ number_format($order->discount, 2, ',', '.') }}</span></div>
                    <div class="kv"><span class="k">Total</span><span class="v" style="color:var(--color-primary);">R$ {{ number_format($order->total, 2, ',', '.') }}</span></div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">Cliente</div>
                <div class="panel-body">
                    <div class="kv"><span class="k">Nome</span><span class="v" style="font-family:var(--font-body);">{{ $order->user->name ?? '—' }}</span></div>
                    <div class="kv"><span class="k">CPF</span><span class="v">@if ($cpfCliente)<span class="adm-copiar"><span data-copiar-valor>{{ $cpfCliente }}</span><button type="button" data-copiar>Copiar</button></span>@else — @endif</span></div>
                    <div class="kv"><span class="k">Telefone</span><span class="v">@if ($telCliente)<span class="adm-copiar"><span data-copiar-valor>{{ $telCliente }}</span><button type="button" data-copiar>Copiar</button></span>@else — @endif</span></div>
                    <div class="kv"><span class="k">E-mail</span><span class="v" style="font-family:var(--font-body);">@if ($order->user?->email)<span class="adm-copiar"><span data-copiar-valor>{{ $order->user->email }}</span><button type="button" data-copiar>Copiar</button></span>@else — @endif</span></div>
                    <div class="kv"><span class="k">Pagamento</span><span class="v">{{ $payLabels[$order->payment_method] ?? ($order->payment_method ?? '—') }}</span></div>
                    <div class="kv"><span class="k">Pag. status</span><span class="v">{{ ucfirst($order->payment_status) }}</span></div>
                </div>
            </div>

            {{-- Aviso de pedido por e-mail para a equipe (leva 16). --}}
            <div class="panel">
                <div class="panel-head">Aviso por e-mail</div>
                <div class="panel-body">
                    @if ($order->notificado_em)
                        <p class="addr-note" style="margin:0 0 12px;">Aviso enviado em {{ data_br($order->notificado_em) }}</p>
                    @elseif (! $order->notificacao_erro)
                        <p class="addr-note" style="margin:0 0 12px;">Ainda não enviado. O aviso sai sozinho quando o pagamento é confirmado.</p>
                    @endif
                    @if ($order->notificacao_erro)
                        <div class="alert-ok" style="color:var(--color-danger); border-left-color:var(--color-danger); margin-bottom:12px; overflow-wrap:anywhere;">
                            <strong>Erro no último envio ({{ data_br($order->notificacao_erro_em) }}):</strong> {{ $order->notificacao_erro }}
                        </div>
                    @endif
                    <form action="{{ route('admin.orders.notify', $order) }}" method="POST"
                          onsubmit="return confirm('Enviar o aviso deste pedido por e-mail para a equipe?');">
                        @csrf
                        <button type="submit" class="admin-btn" style="width:100%;">Enviar aviso via e-mail</button>
                    </form>
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
                                <option value="{{ $st }}" {{ $order->statusExibido() === $st ? 'selected' : '' }}>{{ \App\Models\Order::rotuloStatus($st) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="admin-btn">Salvar status</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
  // Copiar CPF/telefone/e-mail (cadastro na Linx).
  document.querySelectorAll('[data-copiar]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var valor = btn.parentNode.querySelector('[data-copiar-valor]').textContent.trim();
      navigator.clipboard.writeText(valor).then(function () {
        btn.textContent = 'Copiado';
        setTimeout(function () { btn.textContent = 'Copiar'; }, 1500);
      });
    });
  });
</script>
@endpush
