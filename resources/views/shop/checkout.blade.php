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
    .sum-total { display: flex; justify-content: space-between; font-family: var(--font-mono); font-size: var(--text-lg); color: var(--color-text); padding: 16px 0 4px; margin-top: 10px; border-top: 1px solid var(--color-border); }
    @media (max-width: 820px) {
        .cko-layout { grid-template-columns: 1fr; }
        .cko-summary { position: static; }
        .grid-2, .grid-3 { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
<section class="cko">
    <div class="container-aptk">

        <span class="eyebrow">Checkout</span>
        <h1 class="section-title" style="margin-bottom:32px;">Finalizar compra</h1>

        <form action="{{ route('checkout.store') }}" method="POST">
            @csrf
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
                                <label for="zipcode">CEP</label>
                                <input type="text" id="zipcode" name="zipcode" value="{{ old('zipcode') }}" placeholder="00000-000">
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
                            <span><span class="q">{{ $row->qty }}×</span> {{ $row->product->name }}@if ($row->size) <small style="color:var(--color-text-muted);">({{ $row->size }})</small>@endif</span>
                            <span>R$ {{ number_format($row->subtotal, 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                    <div class="sum-total"><span>Total</span><span>R$ {{ number_format($total, 2, ',', '.') }}</span></div>
                    <button type="submit" class="btn-aptk btn-aptk--block" style="margin-top:20px;">Confirmar pedido</button>
                    <a href="{{ route('cart.index') }}" class="btn-aptk btn-aptk--outline btn-aptk--block" style="margin-top:10px;">Voltar ao carrinho</a>
                </aside>

            </div>
        </form>

    </div>
</section>
@endsection
