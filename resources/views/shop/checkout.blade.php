@extends('layouts.public')

@section('title', 'Checkout · APTK Spirits')

@push('styles')
<style>
    .cko { padding-block: 48px; }
    .cko-layout { display: grid; grid-template-columns: 1fr 340px; gap: 40px; align-items: start; }
    .cko-card { border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 28px; }
    .cko-card + .cko-card { margin-top: 22px; }
    .cko-card h3 { font-family: var(--font-display); font-size: var(--text-xl); color: var(--color-text); margin: 0 0 20px; }
    .field { margin-bottom: 16px; }
    .field label { display: block; font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.06em; text-transform: uppercase; color: var(--color-text-muted); margin-bottom: 7px; }
    .field input, .field textarea { width: 100%; background: var(--color-bg); border: 1px solid var(--color-border); color: var(--color-text); border-radius: var(--radius-sm); padding: 11px 13px; font-family: var(--font-body); font-size: var(--text-sm); }
    .field input:focus, .field textarea:focus { outline: none; border-color: var(--color-primary-muted); }
    .field .err { color: var(--color-danger); font-size: var(--text-xs); margin-top: 6px; display: block; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .grid-3 { display: grid; grid-template-columns: 1.4fr 0.6fr 1fr; gap: 16px; }
    .pay-opts { display: flex; flex-direction: column; gap: 10px; }
    .pay-opt { display: flex; align-items: center; gap: 12px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 13px 15px; cursor: pointer; transition: border-color .2s ease; }
    .pay-opt:hover { border-color: var(--color-primary-muted); }
    .pay-opt input { accent-color: var(--color-primary); }
    .pay-opt span { font-family: var(--font-body); color: var(--color-text); }
    .cko-summary { border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 26px; background: var(--color-bg-elevated); position: sticky; top: 20px; }
    .cko-summary h3 { font-family: var(--font-display); font-size: var(--text-xl); color: var(--color-text); margin: 0 0 18px; }
    .sum-item { display: flex; justify-content: space-between; gap: 12px; font-size: var(--text-sm); color: var(--color-text-muted); padding: 7px 0; }
    .sum-item .q { font-family: var(--font-mono); }
    .sum-item-name { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .sum-thumb { width: 40px; height: 40px; }
    .sum-item > span:last-child { white-space: nowrap; }
    .sum-total { display: flex; justify-content: space-between; font-family: var(--font-mono); font-size: var(--text-lg); color: var(--color-text); padding: 16px 0 4px; margin-top: 10px; border-top: 1px solid var(--color-border); }
    @media (max-width: 820px) {
        .cko-layout { grid-template-columns: 1fr; }
        .cko-summary { position: static; }
        .grid-2, .grid-3 { grid-template-columns: 1fr; }
    }
    .pay-error { font-family: var(--font-mono); font-size: var(--text-sm); color: var(--color-danger); border: 1px solid var(--color-border); border-left: 3px solid var(--color-danger); border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 24px; }
    .card-fields { margin-top: 20px; padding-top: 20px; border-top: 1px dashed var(--color-border); }
    .card-fields select { width: 100%; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-sm); color: var(--color-text); padding: 11px 12px; font-family: var(--font-body); }
    .pay-hint { display: block; font-size: var(--text-xs); color: var(--color-text-muted); margin-top: 6px; }
    .frete-opt { justify-content: space-between; }
    .frete-opt .frete-nome { display: flex; align-items: center; gap: 12px; }
    .frete-opt .frete-prazo { display: block; font-size: var(--text-xs); color: var(--color-text-muted); }
    .frete-opt .frete-valor { font-family: var(--font-mono); color: var(--color-text); }
    .frete-msg { font-size: var(--text-sm); color: var(--color-text-muted); margin: 0; }
    .frete-msg.is-error { color: var(--color-danger); }
    .sum-line { display: flex; justify-content: space-between; gap: 12px; font-size: var(--text-sm); color: var(--color-text-muted); padding: 5px 0; }
    .sum-line span:last-child { font-family: var(--font-mono); }
    .sum-lines { margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--color-border); }
</style>
@endpush

@section('content')
<section class="cko">
    <div class="container-aptk">

        <span class="eyebrow">Checkout</span>
        <h1 class="section-title" style="margin-bottom:32px;">Finalizar compra</h1>
        @if (session('payment_error'))
            <div class="pay-error">{{ session('payment_error') }}</div>
        @endif


        {{-- Só data-appmax-customer (coleta de IP). A tokenização roda no
             form separado #appmaxCardForm, no fim da página: os dados crus
             do cartão nunca fazem parte DESTE form. --}}
        <form id="checkoutForm" data-appmax-customer action="{{ route('checkout.store') }}" method="POST">
            @csrf
            {{-- IP coletado pelo Appmax JS (obrigatório p/ homologação — doc 3.1).
                 Preenchido pelo AppmaxScripts.init; fallback: IP do request no servidor. --}}
            <input type="hidden" name="appmax_ip" id="appmax_ip" value="">
            {{-- Token do cartão: vem do onTokenize({ token }) do Appmax JS. --}}
            <input type="hidden" name="appmax_token" id="appmax_token" value="">
            <div class="cko-layout">

                {{-- Coluna esquerda: dados --}}
                <div>
                    <div class="cko-card">
                        <h3>Entrega</h3>

                        <div class="field">
                            <label for="name">Nome do destinatário</label>
                            <input type="text" id="name" name="name" value="{{ old('name', auth()->user()->name) }}">
                            @error('name') <span class="err">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid-2">
                            <div class="field">
                                <label for="cpf">CPF (titular do pagamento)</label>
                                <input type="text" id="cpf" name="cpf" value="{{ old('cpf') }}" placeholder="000.000.000-00" inputmode="numeric" maxlength="14">
                                @error('cpf') <span class="err">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label for="phone">Telefone / WhatsApp</label>
                                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="(11) 90000-0000" maxlength="20">
                                @error('phone') <span class="err">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid-2">
                            <div class="field">
                                <label for="zipcode">CEP</label>
                                <input type="text" id="zipcode" name="zipcode" value="{{ old('zipcode') }}" placeholder="00000-000" inputmode="numeric" maxlength="9">
                                @error('zipcode') <span class="err">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid-3">
                            <div class="field">
                                <label for="street">Rua</label>
                                <input type="text" id="street" name="street" value="{{ old('street') }}">
                                @error('street') <span class="err">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label for="number">Número</label>
                                <input type="text" id="number" name="number" value="{{ old('number') }}">
                                @error('number') <span class="err">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label for="complement">Complemento</label>
                                <input type="text" id="complement" name="complement" value="{{ old('complement') }}">
                                @error('complement') <span class="err">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid-3">
                            <div class="field">
                                <label for="neighborhood">Bairro</label>
                                <input type="text" id="neighborhood" name="neighborhood" value="{{ old('neighborhood') }}">
                                @error('neighborhood') <span class="err">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label for="city">Cidade</label>
                                <input type="text" id="city" name="city" value="{{ old('city') }}">
                                @error('city') <span class="err">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label for="state">UF</label>
                                <input type="text" id="state" name="state" value="{{ old('state') }}" maxlength="2" style="text-transform:uppercase;">
                                @error('state') <span class="err">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Frete: opções cotadas pelo CEP (POST /frete/cotar). O valor
                         mostrado aqui é só exibição — o servidor recalcula no envio. --}}
                    <div class="cko-card">
                        <h3>Frete</h3>
                        <div id="freteOpcoes" class="pay-opts" data-url="{{ route('frete.cotar') }}" data-old="{{ old('shipping_method') }}"></div>
                        <p id="freteMsg" class="frete-msg">Informe o CEP de entrega para ver as opções.</p>
                        @error('shipping_method') <span class="err" style="margin-top:10px;">{{ $message }}</span> @enderror
                    </div>

                    <div class="cko-card">
                        <h3>Pagamento</h3>
                        <div class="pay-opts">
                            <label class="pay-opt">
                                <input type="radio" name="payment_method" value="pix" {{ old('payment_method', 'pix') === 'pix' ? 'checked' : '' }}>
                                <span>Pix</span>
                            </label>
                            <label class="pay-opt">
                                <input type="radio" name="payment_method" value="cartao" {{ old('payment_method') === 'cartao' ? 'checked' : '' }}>
                                <span>Cartão de crédito</span>
                            </label>
                            <label class="pay-opt">
                                <input type="radio" name="payment_method" value="boleto" {{ old('payment_method') === 'boleto' ? 'checked' : '' }}>
                                <span>Boleto</span>
                            </label>
                        </div>
                        @error('payment_method') <span class="err" style="margin-top:10px;">{{ $message }}</span> @enderror

                        {{-- Dados do cartão — só aparecem com "Cartão de crédito" marcado.
                             Número, validade e CVV SEM name: nunca vão no submit. --}}
                        <div id="cardFields" class="card-fields" hidden>
                            <span class="err" id="cardError" role="alert" @if (! $errors->has('appmax_token')) hidden @endif>{{ $errors->first('appmax_token') }}</span>
                            <div class="grid-2">
                                <div class="field">
                                    <label for="card_number">Número do cartão</label>
                                    <input type="text" id="card_number" placeholder="0000 0000 0000 0000" inputmode="numeric" maxlength="19" autocomplete="cc-number">
                                </div>
                                <div class="field">
                                    <label for="card_name">Nome impresso no cartão</label>
                                    <input type="text" id="card_name" name="card_name" value="{{ old('card_name') }}" autocomplete="cc-name" style="text-transform:uppercase;">
                                    @error('card_name') <span class="err">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="grid-3">
                                <div class="field">
                                    <label for="card_expiry">Validade (MM/AA)</label>
                                    <input type="text" id="card_expiry" placeholder="09/28" inputmode="numeric" maxlength="5" autocomplete="cc-exp">
                                </div>
                                <div class="field">
                                    <label for="card_cvv">CVV</label>
                                    <input type="text" id="card_cvv" placeholder="123" inputmode="numeric" maxlength="4" autocomplete="cc-csc">
                                </div>
                                <div class="field">
                                    <label for="installments">Parcelas</label>
                                    {{-- Sem juros (a loja absorve). As opções são remontadas pelo
                                         JS quando o frete muda o total; o servidor revalida. --}}
                                    <select id="installments" name="installments" data-max="{{ $maxInstallments }}" data-minimo="{{ number_format($minInstallmentValue, 2, '.', '') }}" data-old="{{ old('installments', 1) }}">
                                        @for ($i = 1; $i <= \App\Services\AppmaxService::maxParcelas($total); $i++)
                                            <option value="{{ $i }}" {{ (int) old('installments', 1) === $i ? 'selected' : '' }}>
                                                {{ $i }}x de R$ {{ number_format($total / $i, 2, ',', '.') }}{{ $i === 1 ? ' à vista' : ' sem juros' }}
                                            </option>
                                        @endfor
                                    </select>
                                    @error('installments') <span class="err">{{ $message }}</span> @enderror
                                    <span class="pay-hint">Até {{ $maxInstallments }}x sem juros, parcela mínima de R$ {{ number_format($minInstallmentValue, 2, ',', '.') }}.</span>
                                </div>
                            </div>
                        </div>

                        <div class="field" style="margin-top:20px;">
                            <label for="notes">Observações (opcional)</label>
                            <textarea id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                            @error('notes') <span class="err">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                {{-- Coluna direita: resumo --}}
                <aside class="cko-summary">
                    <h3>Seu pedido</h3>
                    @foreach ($items as $row)
                        <div class="sum-item">
                            <span class="sum-item-name">
                                <x-product.image class="prod-thumb sum-thumb" :image="$row->product->primaryImage" :alt="$row->product->name" compact />
                                <span><span class="q">{{ $row->qty }}×</span> {{ $row->product->name }}@if ($row->size) <small style="color:var(--color-text-muted);">({{ $row->size }})</small>@endif</span>
                            </span>
                            <span>R$ {{ number_format($row->subtotal, 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                    <div class="sum-lines">
                        <div class="sum-line"><span>Subtotal</span><span>R$ {{ number_format($total, 2, ',', '.') }}</span></div>
                        <div class="sum-line"><span id="sumFreteNome">Frete</span><span id="sumFrete">a calcular</span></div>
                    </div>
                    <div class="sum-total"><span>Total</span><span id="sumTotal" data-subtotal="{{ number_format($total, 2, '.', '') }}">R$ {{ number_format($total, 2, ',', '.') }}</span></div>
                    <button type="submit" class="btn-aptk btn-aptk--block" style="margin-top:20px;">Confirmar pedido</button>
                    <a href="{{ route('cart.index') }}" class="btn-aptk btn-aptk--outline btn-aptk--block" style="margin-top:10px;">Voltar ao carrinho</a>
                </aside>

            </div>
        </form>

        {{-- Form SÓ da tokenização (data-appmax-checkout): o Appmax JS lê estes
             names via FormData (card-number, card-holder-name, exp-month,
             exp-year, cvv). Nunca é enviado ao nosso servidor — o submit dele
             é sempre cancelado; quem vai pro servidor é o #checkoutForm, só
             com o token. --}}
        <form id="appmaxCardForm" data-appmax-checkout action="#" method="POST" hidden aria-hidden="true">
            <input type="hidden" name="card-number" id="mx_card_number" value="">
            <input type="hidden" name="card-holder-name" id="mx_card_holder" value="">
            <input type="hidden" name="exp-month" id="mx_exp_month" value="">
            <input type="hidden" name="exp-year" id="mx_exp_year" value="">
            <input type="hidden" name="cvv" id="mx_cvv" value="">
        </form>

    </div>
</section>
@endsection

@push('scripts')
{{-- Appmax JS (obrigatório — doc "Appmax JS"): coleta de IP + tokenização.
       · Script por AMBIENTE (config appmax.script_urls), casando com o
         external_id da instalação — misturar dá 404 Merchant not found.
       · init({ externalId, onIp, onTokenize, onError }): onIp recebe { ip },
         onTokenize recebe { token }.
       · data-appmax-checkout fica no #appmaxCardForm (só os espelhos com os
         names que o SDK lê); o #checkoutForm vai ao servidor só com o token.
       · Script fora do ar ou tokenização falhou: NÃO envia. Sem fallback
         com dado cru — o servidor recusa cartão sem token. --}}
<script src="{{ $appmaxScriptUrl }}"></script>
<script>
  (function () {
    var form = document.getElementById('checkoutForm');
    var cardForm = document.getElementById('appmaxCardForm');
    if (!form || !cardForm) return;

    var MSG_FALHA = 'Não foi possível validar o cartão. Confira os dados e tente de novo, ou escolha Pix ou boleto.';
    var errEl = document.getElementById('cardError');
    var tokenEl = document.getElementById('appmax_token');
    var pronto = false;      // init da Appmax rodou
    var aguardando = false;  // tokenização em andamento
    var prazo = null;

    function currentMethod() {
      var m = document.querySelector('input[name="payment_method"]:checked');
      return m ? m.value : null;
    }

    function mostrarErro(texto) {
      if (!errEl) return;
      errEl.textContent = texto || '';
      errEl.hidden = !texto;
      if (texto) errEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function falhou(motivo) {
      if (motivo) console.error('Appmax JS:', motivo);
      aguardando = false;
      clearTimeout(prazo);
      if (tokenEl) tokenEl.value = '';
      mostrarErro(MSG_FALHA);
    }

    // Submit do checkout: Pix/boleto seguem direto. Cartão nunca segue
    // daqui — só depois do token, pelo form.submit() nativo.
    form.addEventListener('submit', function (e) {
      if (currentMethod() !== 'cartao') return;
      e.preventDefault();
      if (aguardando) return;
      if (typeof form.reportValidity === 'function' && !form.reportValidity()) return;

      mostrarErro('');
      if (tokenEl) tokenEl.value = '';
      if (!pronto) { falhou('AppmaxScripts não carregado/iniciado'); return; }

      aguardando = true;
      prazo = setTimeout(function () { if (aguardando) falhou('tokenização sem resposta em 30s'); }, 30000);

      if (typeof cardForm.requestSubmit === 'function') {
        cardForm.requestSubmit();
      } else {
        cardForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
      }
    });

    function onIp(data) {
      var ip = document.getElementById('appmax_ip');
      if (ip && data && data.ip) ip.value = data.ip;
    }

    function onTokenize(data) {
      if (!aguardando) return;
      var token = data && data.token;
      if (!token) { falhou('onTokenize sem token'); return; }
      aguardando = false;
      clearTimeout(prazo);
      tokenEl.value = token;
      form.submit(); // nativo: não redispara o listener acima
    }

    function onError(err) {
      // IP_FETCH_FAILED etc. fora de um submit não bloqueiam nada aqui.
      if (aguardando) falhou(err && (err.code + ' ' + (err.message || '')));
      else console.error('Appmax JS:', err);
    }

    function boot() {
      if (!window.AppmaxScripts) { console.error('AppmaxScripts não carregado.'); return; }
      try {
        window.AppmaxScripts.init({
          externalId: @json($appmaxExternalId ?? null),
          onIp: onIp,
          onTokenize: onTokenize,
          onError: onError,
        });
        pronto = true;
      } catch (e) {
        console.error('Appmax JS: init falhou', e);
      }

      // Registrado DEPOIS do init: o listener da Appmax já leu o FormData;
      // aqui só garantimos que este form nunca navega.
      cardForm.addEventListener('submit', function (e) { e.preventDefault(); });
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', boot);
    } else {
      boot();
    }
  })();
</script>
<script>
  // Checkout: alterna os campos do cartão + máscaras + espelhos do Appmax JS.
  (function () {
    var radios = document.querySelectorAll('input[name="payment_method"]');
    var cardBox = document.getElementById('cardFields');
    function toggleCard() {
      var m = document.querySelector('input[name="payment_method"]:checked');
      var isCard = m && m.value === 'cartao';
      if (cardBox) cardBox.hidden = !isCard;
      ['card_number', 'card_name', 'card_expiry', 'card_cvv'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.required = isCard;
      });
    }
    radios.forEach(function (r) { r.addEventListener('change', toggleCard); });
    toggleCard();

    // Espelhos (names com hífen que o Appmax JS lê no FormData)
    function mirror(srcId, dstId, fn) {
      var src = document.getElementById(srcId);
      var dst = document.getElementById(dstId);
      if (!src || !dst) return;
      src.addEventListener('input', function () { dst.value = fn ? fn(src.value) : src.value; });
    }
    mirror('card_number', 'mx_card_number', function (v) { return v.replace(/\D/g, ''); });
    mirror('card_name', 'mx_card_holder');
    mirror('card_cvv', 'mx_cvv');

    function mask(el, fn) { if (el) el.addEventListener('input', function () { el.value = fn(el.value); }); }
    mask(document.getElementById('cpf'), function (v) {
      v = v.replace(/\D/g, '').slice(0, 11);
      return v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    });
    mask(document.getElementById('card_number'), function (v) {
      return v.replace(/\D/g, '').slice(0, 16).replace(/(\d{4})(?=\d)/g, '$1 ');
    });
    mask(document.getElementById('card_expiry'), function (v) {
      v = v.replace(/\D/g, '').slice(0, 4);
      var mo = document.getElementById('mx_exp_month');
      var yr = document.getElementById('mx_exp_year');
      if (mo) mo.value = v.slice(0, 2);
      if (yr) yr.value = v.slice(2, 4);
      return v.length > 2 ? v.slice(0, 2) + '/' + v.slice(2) : v;
    });
    mask(document.getElementById('card_cvv'), function (v) { return v.replace(/\D/g, '').slice(0, 4); });
  })();
</script>
<script>
  // Frete: cota pelo CEP e atualiza resumo + parcelas. Só exibição — o
  // pedido é fechado com o frete recalculado no servidor.
  (function () {
    var box = document.getElementById('freteOpcoes');
    var msg = document.getElementById('freteMsg');
    var cep = document.getElementById('zipcode');
    var form = document.getElementById('checkoutForm');
    if (!box || !cep || !form) return;

    var totalEl = document.getElementById('sumTotal');
    var freteEl = document.getElementById('sumFrete');
    var freteNomeEl = document.getElementById('sumFreteNome');
    var subtotal = parseFloat(totalEl.dataset.subtotal) || 0;
    var ultimo = null;
    var escolhaAnterior = box.dataset.old || null;

    function brl(v) { return 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }

    function aviso(texto, erro) {
      msg.textContent = texto;
      msg.classList.toggle('is-error', !!erro);
      msg.hidden = !texto;
    }

    // Parcelas sem juros: até data-max, nenhuma abaixo de data-minimo
    // (mesma regra do AppmaxService::maxParcelas, que revalida no servidor).
    var parcelasEl = document.getElementById('installments');

    function montarParcelas(total) {
      if (!parcelasEl) return;
      var max = parseInt(parcelasEl.dataset.max, 10) || 1;
      var minimoCent = Math.round((parseFloat(parcelasEl.dataset.minimo) || 0) * 100);
      var permitidas = minimoCent > 0 ? Math.floor(Math.round(total * 100) / minimoCent) : max;
      permitidas = Math.max(1, Math.min(max, permitidas));
      var escolhida = parseInt(parcelasEl.value || parcelasEl.dataset.old, 10) || 1;

      parcelasEl.innerHTML = '';
      for (var n = 1; n <= permitidas; n++) {
        var op = document.createElement('option');
        op.value = n;
        op.textContent = n + 'x de ' + brl(total / n) + (n === 1 ? ' à vista' : ' sem juros');
        op.selected = n === Math.min(escolhida, permitidas);
        parcelasEl.appendChild(op);
      }
    }

    function atualizarTotais(valor, nome) {
      var total = subtotal + (valor || 0);
      freteEl.textContent = valor === null ? 'a calcular' : brl(valor);
      freteNomeEl.textContent = nome ? 'Frete (' + nome + ')' : 'Frete';
      totalEl.textContent = brl(total);
      montarParcelas(total);
    }

    function render(opcoes) {
      box.innerHTML = '';
      opcoes.forEach(function (o, i) {
        var label = document.createElement('label');
        label.className = 'pay-opt frete-opt';
        var nome = document.createElement('span');
        nome.className = 'frete-nome';
        var input = document.createElement('input');
        input.type = 'radio';
        input.name = 'shipping_method';
        input.value = o.codigo;
        input.required = true;
        input.checked = escolhaAnterior ? escolhaAnterior === o.codigo : i === 0;
        input.addEventListener('change', function () { escolhaAnterior = o.codigo; atualizarTotais(o.valor, o.nome); });
        var txt = document.createElement('span');
        txt.textContent = o.nome;
        var prazo = document.createElement('small');
        prazo.className = 'frete-prazo';
        // Prazo 0 = opção de teste (frete isento): sem "até 0 dias".
        prazo.textContent = o.prazo_dias > 0 ? 'até ' + o.prazo_dias + (o.prazo_dias === 1 ? ' dia útil' : ' dias úteis') : '';
        txt.appendChild(prazo);
        nome.appendChild(input);
        nome.appendChild(txt);
        var valor = document.createElement('span');
        valor.className = 'frete-valor';
        valor.textContent = brl(o.valor);
        label.appendChild(nome);
        label.appendChild(valor);
        box.appendChild(label);
        if (input.checked) atualizarTotais(o.valor, o.nome);
      });
    }

    function cotar() {
      var digitos = cep.value.replace(/\D/g, '');
      if (digitos.length !== 8) {
        ultimo = null;
        box.innerHTML = '';
        atualizarTotais(null);
        aviso('Informe o CEP de entrega para ver as opções.');
        return;
      }
      if (digitos === ultimo) return;
      ultimo = digitos;
      aviso('Calculando o frete…');

      fetch(box.dataset.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value },
        body: JSON.stringify({ zipcode: digitos.slice(0, 5) + '-' + digitos.slice(5) }),
      })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
        .then(function (res) {
          if (digitos !== ultimo) return; // chegou resposta de um CEP antigo
          if (!res.ok) {
            var e = res.body.errors || {};
            box.innerHTML = '';
            atualizarTotais(null);
            aviso((e.zipcode || e.cart || [res.body.message || 'Não foi possível calcular o frete.'])[0], true);
            return;
          }
          aviso('');
          render(res.body.opcoes || []);
        })
        .catch(function () {
          ultimo = null;
          aviso('Não foi possível calcular o frete agora. Tente de novo em instantes.', true);
        });
    }

    cep.addEventListener('input', function () {
      var v = cep.value.replace(/\D/g, '').slice(0, 8);
      cep.value = v.length > 5 ? v.slice(0, 5) + '-' + v.slice(5) : v;
      cotar();
    });
    cotar();
  })();
</script>
@endpush
