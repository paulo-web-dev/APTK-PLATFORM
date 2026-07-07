@extends('layouts.public')

@section('title', 'Pedido confirmado · APTK Spirits')

@push('styles')
<style>
    .ok { padding-block: 64px; max-width: 720px; margin: 0 auto; }
    .ok-badge { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.12em; text-transform: uppercase; color: var(--color-success); margin-bottom: 16px; display: block; }
    .ok-title { font-family: var(--font-display); font-size: clamp(2rem, 5vw, 3rem); color: var(--color-text); margin: 0 0 12px; }
    .ok-num { font-family: var(--font-mono); font-size: var(--text-lg); color: var(--color-primary); margin-bottom: 28px; }
    .ok-card { border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 28px; margin-bottom: 22px; }
    .ok-card h3 { font-family: var(--font-display); font-size: var(--text-lg); color: var(--color-text); margin: 0 0 18px; }
    .ok-row { display: flex; justify-content: space-between; gap: 12px; padding: 9px 0; border-bottom: 1px solid var(--color-border); font-size: var(--text-sm); color: var(--color-text); }
    .ok-row:last-child { border-bottom: none; }
    .ok-row .q { font-family: var(--font-mono); color: var(--color-text-muted); }
    .ok-total { display: flex; justify-content: space-between; font-family: var(--font-mono); font-size: var(--text-lg); color: var(--color-text); padding-top: 16px; margin-top: 8px; border-top: 1px solid var(--color-border); }
    .ok-meta { display: flex; gap: 32px; flex-wrap: wrap; }
    .ok-meta div { display: flex; flex-direction: column; gap: 4px; }
    .ok-meta .k { font-size: var(--text-xs); color: var(--color-text-muted); letter-spacing: 0.08em; text-transform: uppercase; }
    .ok-meta .v { font-family: var(--font-mono); color: var(--color-text); }
    .ok-note { color: var(--color-text-muted); line-height: 1.7; white-space: pre-line; font-size: var(--text-sm); }
    .ok-actions { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 8px; }

    /* ---- Painel de pagamento (Appmax) ---- */
    .pay-panel { border: 1px solid var(--color-primary-muted); }
    .pay-panel .pp-status { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.12em; text-transform: uppercase; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 16px; }
    .pp-status.waiting { color: var(--color-scotch, var(--color-primary)); }
    .pp-status.paid { color: var(--color-success); }
    .pp-status .dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; animation: ppPulse 1.4s ease infinite; }
    .pp-status.paid .dot { animation: none; }
    @keyframes ppPulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.25; } }
    .pix-grid { display: grid; grid-template-columns: 200px 1fr; gap: 26px; align-items: start; }
    .pix-qr { width: 200px; height: 200px; background: #fff; border-radius: var(--radius-md); border: 1px solid var(--color-border); padding: 10px; display: block; }
    .pix-qr img { width: 100%; height: 100%; display: block; }
    .emv-box { display: flex; gap: 8px; align-items: stretch; margin-top: 10px; }
    .emv-box input { flex: 1; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-sm); color: var(--color-text); padding: 10px 12px; font-family: var(--font-mono); font-size: var(--text-xs); }
    .pp-exp { font-size: var(--text-xs); color: var(--color-text-muted); margin-top: 12px; }
    .boleto-line { background: var(--color-bg); border: 1px dashed var(--color-border); border-radius: var(--radius-sm); padding: 14px 16px; font-family: var(--font-mono); font-size: var(--text-sm); color: var(--color-text); word-break: break-all; margin-bottom: 14px; }
    @media (max-width: 640px) { .pix-grid { grid-template-columns: 1fr; } .pix-qr { margin-inline: auto; } }
</style>
@endpush

@php
    $payLabels = ['pix' => 'Pix', 'cartao' => 'Cartão de crédito', 'boleto' => 'Boleto'];
@endphp

@section('content')
<section class="ok">
    <div class="container-aptk">

        @php $details = $order->payment_details ?? []; @endphp

        @if ($order->isPaid())
            <span class="ok-badge">✓ Pagamento aprovado</span>
            <h1 class="ok-title">Obrigado pela sua compra!</h1>
        @else
            <span class="ok-badge" style="color:var(--color-primary);">Pedido recebido</span>
            <h1 class="ok-title">Falta só o pagamento</h1>
        @endif
        <p class="ok-num">Pedido APTK-{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</p>

        {{-- ================= PAGAMENTO (Appmax) ================= --}}
        @if ($order->payment_method === 'pix' && ! $order->isPaid() && ! empty($details['emv']))
            <div class="ok-card pay-panel" id="pixPanel">
                <span class="pp-status waiting" id="ppStatus"><span class="dot"></span> Aguardando pagamento Pix</span>
                <h3>Pague com Pix para confirmar</h3>
                <div class="pix-grid">
                    <div class="pix-qr">
                        @if (! empty($details['qrcode']))
                            <img src="data:image/png;base64,{{ $details['qrcode'] }}" alt="QR Code Pix">
                        @endif
                    </div>
                    <div>
                        <p style="color:var(--color-text-muted); font-size:var(--text-sm); margin:0 0 6px;">
                            Abra o app do seu banco, escaneie o QR Code ao lado ou use o copia-e-cola:
                        </p>
                        <div class="emv-box">
                            <input type="text" id="pixEmv" value="{{ $details['emv'] }}" readonly onclick="this.select()">
                            <button type="button" class="btn-aptk" id="copyEmv">Copiar</button>
                        </div>
                        @if (! empty($details['expiration']))
                            <p class="pp-exp">Chave válida até {{ \Carbon\Carbon::parse($details['expiration'])->format('d/m/Y H:i') }}. A confirmação aparece aqui automaticamente após o pagamento.</p>
                        @else
                            <p class="pp-exp">A confirmação aparece aqui automaticamente após o pagamento.</p>
                        @endif
                    </div>
                </div>
            </div>
        @elseif ($order->payment_method === 'boleto' && ! $order->isPaid() && ! empty($details['digitable_line']))
            <div class="ok-card pay-panel">
                <span class="pp-status waiting"><span class="dot"></span> Aguardando compensação do boleto</span>
                <h3>Pague o boleto para confirmar</h3>
                <div class="boleto-line">{{ $details['digitable_line'] }}</div>
                <div class="ok-actions" style="margin:0;">
                    @if (! empty($details['pdf']))
                        <a href="{{ $details['pdf'] }}" target="_blank" rel="noopener" class="btn-aptk">Abrir boleto (PDF)</a>
                    @endif
                    <button type="button" class="btn-aptk btn-aptk--outline" id="copyBoleto" data-line="{{ $details['digitable_line'] }}">Copiar linha digitável</button>
                </div>
                @if (! empty($details['due_date']))
                    <p class="pp-exp">Vencimento: {{ \Carbon\Carbon::parse($details['due_date'])->format('d/m/Y') }}. Boletos compensam em até 3 dias úteis.</p>
                @endif
            </div>
        @elseif ($order->payment_method === 'cartao' && $order->isPaid())
            <div class="ok-card pay-panel">
                <span class="pp-status paid"><span class="dot"></span> Pagamento aprovado no cartão</span>
                <h3>Tudo certo com o pagamento</h3>
                <p style="color:var(--color-text-muted); font-size:var(--text-sm); margin:0;">
                    Cobrança em {{ $details['installments'] ?? 1 }}× no cartão de crédito. Já estamos preparando o seu pedido.
                </p>
            </div>
        @endif

        <div class="ok-card">
            <h3>Itens</h3>
            @foreach ($order->items as $item)
                <div class="ok-row">
                    <span><span class="q">{{ $item->qty }}×</span> {{ $item->product_name }}@if ($item->size) <small style="color:var(--color-text-muted);">({{ $item->size }})</small>@endif</span>
                    <span>R$ {{ number_format($item->total_price, 2, ',', '.') }}</span>
                </div>
            @endforeach
            <div class="ok-total"><span>Total</span><span>R$ {{ number_format($order->total, 2, ',', '.') }}</span></div>
        </div>

        <div class="ok-card">
            <h3>Detalhes</h3>
            <div class="ok-meta">
                <div><span class="k">Pagamento</span><span class="v">{{ $payLabels[$order->payment_method] ?? $order->payment_method }}</span></div>
                <div><span class="k">Status</span><span class="v">{{ ucfirst($order->status) }}</span></div>
                <div><span class="k">Pagamento (status)</span><span class="v">{{ ['pending' => 'Aguardando', 'paid' => 'Pago', 'failed' => 'Recusado', 'expired' => 'Expirado', 'refunded' => 'Estornado'][$order->payment_status] ?? ucfirst($order->payment_status) }}</span></div>
            </div>
            @if ($order->notes)
                <p class="ok-note" style="margin-top:20px;">{{ $order->notes }}</p>
            @endif
        </div>

        <div class="ok-actions">
            <a href="{{ route('catalog') }}" class="btn-aptk">Continuar comprando</a>
            <a href="{{ route('dashboard') }}" class="btn-aptk btn-aptk--outline">Minha conta</a>
        </div>

    </div>
</section>
@endsection

@push('scripts')
<script>
  (function () {
    // Copiar copia-e-cola / linha digitável.
    function bindCopy(btnId, getValue) {
      var btn = document.getElementById(btnId);
      if (!btn) return;
      btn.addEventListener('click', function () {
        navigator.clipboard.writeText(getValue()).then(function () {
          var t = btn.textContent;
          btn.textContent = 'Copiado!';
          setTimeout(function () { btn.textContent = t; }, 2000);
        });
      });
    }
    bindCopy('copyEmv', function () { return document.getElementById('pixEmv').value; });
    bindCopy('copyBoleto', function () { return document.getElementById('copyBoleto').getAttribute('data-line'); });

    // Polling do Pix: consulta o status a cada 8s; ao confirmar, recarrega
    // a página (o webhook da Appmax atualiza o pedido no servidor).
    var pixPanel = document.getElementById('pixPanel');
    if (pixPanel) {
      var url = @json(route('checkout.payment-status', $order));
      var timer = setInterval(function () {
        fetch(url, { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (d.paid) { clearInterval(timer); window.location.reload(); }
          })
          .catch(function () {});
      }, 8000);
      // Para de consultar depois de 30 min (chave já terá expirado).
      setTimeout(function () { clearInterval(timer); }, 30 * 60 * 1000);
    }
  })();
</script>
@endpush
