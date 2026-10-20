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

    /* ---- Identificação dentro do checkout (leva 09) — mobile primeiro ---- */
    .cko [hidden] { display: none !important; } /* classes com display não vencem o hidden */
    .ident-intro { color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.6; margin: -8px 0 20px; }
    .ident-msg { color: var(--color-text); font-size: var(--text-sm); line-height: 1.6; margin: 0 0 18px; }
    .ident-email { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; font-family: var(--font-mono); font-size: var(--text-sm); color: var(--color-text); margin-bottom: 18px; word-break: break-all; }
    .ident-link { background: none; border: 0; padding: 0; color: var(--color-primary); font-size: var(--text-sm); cursor: pointer; text-decoration: underline; white-space: nowrap; }
    .ident-err { color: var(--color-danger); font-size: var(--text-sm); margin: 0 0 14px; }
    .ident-check { display: flex; align-items: flex-start; gap: 10px; font-size: var(--text-sm); color: var(--color-text); margin: 4px 0 20px; cursor: pointer; line-height: 1.5; }
    .ident-check input { margin-top: 3px; width: 18px; height: 18px; accent-color: var(--color-primary); flex-shrink: 0; }
    .senha-wrap { position: relative; }
    .senha-wrap input { padding-right: 84px; }
    .senha-ver { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: 0; color: var(--color-text-muted); font-family: var(--font-mono); font-size: var(--text-xs); text-transform: uppercase; letter-spacing: 0.06em; cursor: pointer; padding: 6px; }
    .ident-foot { display: flex; justify-content: flex-end; margin-top: 14px; }
    .conta-linha { font-size: var(--text-sm); color: var(--color-text-muted); margin: -8px 0 18px; }
    .conta-linha strong { color: var(--color-text); font-weight: 400; font-family: var(--font-mono); }
    .maior-idade { margin: 18px 0 0; }
    .field input[type="email"], .field input[type="password"] { min-height: 46px; font-size: 16px; } /* 16px: iOS não dá zoom no foco */
    /* Resumo compacto no topo, só no celular. */
    .cko-mini { display: none; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-bg-elevated); margin-bottom: 20px; }
    .cko-mini summary { display: flex; justify-content: space-between; gap: 12px; padding: 14px 16px; cursor: pointer; font-size: var(--text-sm); color: var(--color-text); list-style: none; }
    .cko-mini summary::-webkit-details-marker { display: none; }
    .cko-mini summary .v { font-family: var(--font-mono); }
    .cko-mini .mini-itens { padding: 0 16px 12px; }
    @media (max-width: 820px) {
        .cko-mini { display: block; }
        .cko-card { padding: 22px 18px; }
    }
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
            {{-- Celular: o que está sendo comprado fica à vista no topo. --}}
            <details class="cko-mini">
                <summary>
                    <span>Seu pedido · {{ $items->sum('qty') }} {{ $items->sum('qty') === 1 ? 'item' : 'itens' }}</span>
                    <span class="v">R$ {{ number_format($total, 2, ',', '.') }}</span>
                </summary>
                <div class="mini-itens">
                    @foreach ($items as $row)
                        <div class="sum-item">
                            <span><span class="q">{{ $row->qty }}×</span> {{ $row->product->name }}@if ($row->size) <small>({{ $row->size }})</small>@endif
                                @if ($row->personalizacao) <x-custom.ficha :p="$row->personalizacao" /> @endif</span>
                            <span>R$ {{ number_format($row->subtotal, 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
            </details>

            <div class="cko-layout">

                {{-- Coluna esquerda: dados --}}
                <div>
                    @if ($identificar)
                        {{-- Identificação (visitante). Campos SEM name: nada daqui vai
                             no POST do pedido; tudo por AJAX nas rotas
                             checkout.identificacao.*. Ao concluir, este bloco some e
                             o #dadosPedido aparece. --}}
                        <div class="cko-card" id="identificacao"
                             data-url-email="{{ route('checkout.identificacao.email') }}"
                             data-url-entrar="{{ route('checkout.identificacao.entrar') }}"
                             data-url-cadastrar="{{ route('checkout.identificacao.cadastrar') }}">
                            <h3>Identificação</h3>
                            <p class="ident-intro">Precisamos da sua conta para você acompanhar o pedido e a entrega.</p>
                            <p class="ident-err" id="identErro" role="alert" hidden></p>

                            {{-- 1. e-mail --}}
                            <div data-etapa="email">
                                <div class="field">
                                    <label for="identEmail">E-mail</label>
                                    <input type="email" id="identEmail" autocomplete="email" inputmode="email" placeholder="seu@email.com">
                                </div>
                                <button type="button" class="btn-aptk btn-aptk--block" data-acao="continuar">Continuar</button>
                            </div>

                            {{-- 2a. já tem conta --}}
                            <div data-etapa="senha" hidden>
                                <p class="ident-msg">Que bom te ver de novo! Digite sua senha para continuar.</p>
                                <div class="ident-email"><span data-email-atual></span><button type="button" class="ident-link" data-acao="trocar">Trocar e-mail</button></div>
                                <div class="field">
                                    <label for="identSenha">Senha</label>
                                    <div class="senha-wrap">
                                        <input type="password" id="identSenha" autocomplete="current-password">
                                        <button type="button" class="senha-ver" data-ver="identSenha">Mostrar</button>
                                    </div>
                                </div>
                                <button type="button" class="btn-aptk btn-aptk--block" data-acao="entrar">Entrar e continuar</button>
                                <div class="ident-foot">
                                    <a href="{{ route('password.request') }}" target="_blank" rel="noopener" class="ident-link">Esqueci minha senha</a>
                                </div>
                            </div>

                            {{-- 2b. conta nova --}}
                            <div data-etapa="cadastro" hidden>
                                <p class="ident-msg">Crie sua conta para finalizar o pedido. Leva menos de um minuto.</p>
                                <div class="ident-email"><span data-email-atual></span><button type="button" class="ident-link" data-acao="trocar">Trocar e-mail</button></div>
                                <div class="field">
                                    <label for="identNome">Nome</label>
                                    <input type="text" id="identNome" autocomplete="name">
                                </div>
                                <div class="field">
                                    <label for="identNovaSenha">Crie uma senha</label>
                                    <div class="senha-wrap">
                                        <input type="password" id="identNovaSenha" autocomplete="new-password">
                                        <button type="button" class="senha-ver" data-ver="identNovaSenha">Mostrar</button>
                                    </div>
                                    <span class="pay-hint">Mínimo de 8 caracteres.</span>
                                </div>
                                <label class="ident-check">
                                    <input type="checkbox" id="identMaiorIdade">
                                    <span>Declaro ter 18 anos ou mais</span>
                                </label>
                                <button type="button" class="btn-aptk btn-aptk--block" data-acao="cadastrar">Criar conta e continuar</button>
                            </div>
                        </div>
                    @endif

                    <div id="dadosPedido" @if ($identificar) hidden @endif>
                    <div class="cko-card">
                        <h3>Entrega</h3>
                        <p class="conta-linha" id="contaLinha" @guest hidden @endguest>Comprando como <strong id="contaEmail">{{ auth()->user()?->email }}</strong></p>

                        <div class="field">
                            <label for="name">Nome do destinatário</label>
                            <input type="text" id="name" name="name" value="{{ old('name', auth()->user()?->name) }}">
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
                    </div>{{-- /#dadosPedido --}}
                </div>

                {{-- Coluna direita: resumo --}}
                <aside class="cko-summary">
                    <h3>Seu pedido</h3>
                    @foreach ($items as $row)
                        <div class="sum-item">
                            <span class="sum-item-name">
                                @if ($row->personalizacao)
                                    <x-custom.previa class="cst-previa--thumb sum-thumb" :p="$row->personalizacao" />
                                @else
                                    <x-product.image class="prod-thumb sum-thumb" :image="$row->product->primaryImage" :alt="$row->product->name" compact />
                                @endif
                                <span><span class="q">{{ $row->qty }}×</span> {{ $row->product->name }}@if ($row->size) <small style="color:var(--color-text-muted);">({{ $row->size }})</small>@endif
                                    @if ($row->personalizacao) <x-custom.ficha :p="$row->personalizacao" /> @endif</span>
                            </span>
                            <span>R$ {{ number_format($row->subtotal, 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                    <div class="sum-lines">
                        <div class="sum-line"><span>Subtotal</span><span>R$ {{ number_format($total, 2, ',', '.') }}</span></div>
                        <div class="sum-line"><span id="sumFreteNome">Frete</span><span id="sumFrete">a calcular</span></div>
                    </div>
                    <div class="sum-total"><span>Total</span><span id="sumTotal" data-subtotal="{{ number_format($total, 2, '.', '') }}">R$ {{ number_format($total, 2, ',', '.') }}</span></div>
                    {{-- 18+: conta antiga sem a declaração (ou visitante que entrar
                         com uma). Desabilitada = não vai no POST; o JS liga se o
                         login disser que precisa. --}}
                    @if ($precisaMaiorIdade)
                        <label class="ident-check maior-idade" id="maiorIdadeBox" @if ($identificar) hidden @endif>
                            <input type="checkbox" name="maior_idade" id="maior_idade" value="1" {{ old('maior_idade') ? 'checked' : '' }} @if ($identificar) disabled @else required @endif>
                            <span>Declaro ter 18 anos ou mais</span>
                        </label>
                        @error('maior_idade') <span class="ident-err" style="display:block;">{{ $message }}</span> @enderror
                    @endif
                    <button type="submit" id="confirmarPedido" class="btn-aptk btn-aptk--block" style="margin-top:20px;" @if ($identificar) hidden @endif>Confirmar pedido</button>
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
@if ($identificar)
<script>
  // Identificação dentro do checkout (leva 09): e-mail → senha ou cadastro,
  // tudo por AJAX; ao concluir, mostra o formulário com nome e e-mail.
  (function () {
    var box = document.getElementById('identificacao');
    var form = document.getElementById('checkoutForm');
    if (!box || !form) return;

    var erroEl = document.getElementById('identErro');
    var emailEl = document.getElementById('identEmail');
    var emailAtual = '';
    var ocupado = false;

    // Antes de identificar, o pedido não sai (Enter num campo, etc.).
    // Fase de captura: roda antes de qualquer outro listener de submit.
    form.addEventListener('submit', function (e) {
      if (!box.hidden) { e.preventDefault(); e.stopImmediatePropagation(); }
    }, true);

    function etapa(nome) {
      box.querySelectorAll('[data-etapa]').forEach(function (el) { el.hidden = el.dataset.etapa !== nome; });
      box.querySelectorAll('[data-email-atual]').forEach(function (el) { el.textContent = emailAtual; });
      erro('');
      var foco = { email: emailEl, senha: document.getElementById('identSenha'), cadastro: document.getElementById('identNome') }[nome];
      if (foco) foco.focus();
    }

    function erro(texto) {
      erroEl.textContent = texto || '';
      erroEl.hidden = !texto;
    }

    function csrf() { return form.querySelector('input[name="_token"]').value; }

    function enviar(url, dados, botao) {
      if (ocupado) return Promise.reject();
      ocupado = true;
      var rotulo = botao.textContent;
      botao.disabled = true;
      botao.textContent = 'Aguarde…';
      erro('');

      return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(dados),
      }).then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (j) {
          if (r.status === 429) throw 'Muitas tentativas. Aguarde um minuto e tente de novo.';
          if (r.status === 419) throw 'Sua sessão expirou. Recarregue a página para continuar.';
          if (!r.ok) {
            var e = j.errors || {};
            var primeiro = Object.keys(e)[0];
            throw (primeiro ? e[primeiro][0] : (j.message || 'Não foi possível continuar agora. Tente de novo.'));
          }
          return j;
        });
      }).catch(function (msg) {
        erro(typeof msg === 'string' ? msg : 'Sem conexão. Confira a internet e tente de novo.');
        throw msg;
      }).finally(function () {
        ocupado = false;
        botao.disabled = false;
        botao.textContent = rotulo;
      });
    }

    function concluir(j) {
      // Mesmo token de sessão (o login migra a sessão), mas sincroniza.
      if (j.csrf) {
        form.querySelectorAll('input[name="_token"]').forEach(function (i) { i.value = j.csrf; });
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', j.csrf);
      }

      var nome = document.getElementById('name');
      if (nome && !nome.value) nome.value = j.nome || '';
      document.getElementById('contaEmail').textContent = j.email || '';
      document.getElementById('contaLinha').hidden = false;

      var mi = document.getElementById('maiorIdadeBox');
      var miInput = document.getElementById('maior_idade');
      if (mi && miInput) {
        mi.hidden = !j.precisa_maior_idade;
        miInput.disabled = !j.precisa_maior_idade;
        miInput.required = !!j.precisa_maior_idade;
      }

      box.hidden = true;
      document.getElementById('dadosPedido').hidden = false;
      document.getElementById('confirmarPedido').hidden = false;
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      var cpf = document.getElementById('cpf');
      if (cpf) cpf.focus({ preventScroll: true });
    }

    var acoes = {
      continuar: function (b) {
        var email = emailEl.value.trim();
        if (!email || !emailEl.checkValidity()) { erro('Confira o e-mail digitado.'); emailEl.focus(); return; }
        enviar(box.dataset.urlEmail, { email: email }, b).then(function (j) {
          emailAtual = email;
          etapa(j.conta ? 'senha' : 'cadastro');
        }, function () {});
      },
      entrar: function (b) {
        enviar(box.dataset.urlEntrar, { email: emailAtual, password: document.getElementById('identSenha').value }, b)
          .then(concluir, function () {});
      },
      cadastrar: function (b) {
        var mi = document.getElementById('identMaiorIdade');
        if (!mi.checked) { erro('Para comprar, confirme que você tem 18 anos ou mais.'); mi.focus(); return; }
        enviar(box.dataset.urlCadastrar, {
          name: document.getElementById('identNome').value.trim(),
          email: emailAtual,
          password: document.getElementById('identNovaSenha').value,
          maior_idade: true,
        }, b).then(concluir, function () {});
      },
      trocar: function () { etapa('email'); },
    };

    box.addEventListener('click', function (e) {
      var b = e.target.closest('[data-acao]');
      if (b && acoes[b.dataset.acao]) { e.preventDefault(); acoes[b.dataset.acao](b); }

      var ver = e.target.closest('[data-ver]');
      if (ver) {
        var campo = document.getElementById(ver.dataset.ver);
        var mostrar = campo.type === 'password';
        campo.type = mostrar ? 'text' : 'password';
        ver.textContent = mostrar ? 'Ocultar' : 'Mostrar';
      }
    });

    // Enter avança a etapa visível (e nunca envia o formulário do pedido).
    box.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' || e.target.tagName !== 'INPUT' || e.target.type === 'checkbox') return;
      e.preventDefault();
      var atual = box.querySelector('[data-etapa]:not([hidden]) [data-acao]:not([data-acao="trocar"])');
      if (atual) atual.click();
    });
  })();
</script>
@endif
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
