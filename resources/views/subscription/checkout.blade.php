<x-app-layout>
    @push('styles')
    <style>
        .sub-checkout { display: grid; grid-template-columns: 1.5fr 1fr; gap: 32px; align-items: start; }
        .sub-card { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 28px; }
        .sub-card h2 { font-family: var(--font-display); font-size: var(--text-xl); margin: 0 0 18px; }
        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: var(--text-sm); color: var(--color-text); margin-bottom: 6px; font-weight: 500; }
        .field input, .field textarea { width: 100%; background: var(--color-bg-elevated); border: 1px solid var(--color-border); color: var(--color-text); border-radius: var(--radius-md); padding: 11px 14px; font-family: var(--font-body); font-size: var(--text-sm); }
        .field input:focus, .field textarea:focus { outline: none; border-color: var(--color-primary); }
        .field .err { color: var(--color-danger); font-size: var(--text-xs); margin-top: 5px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .row-cep { display: grid; grid-template-columns: 160px 1fr; gap: 14px; }
        .pay-label { display: block; font-size: var(--text-sm); color: var(--color-text); margin: 4px 0 8px; font-weight: 500; }
        .pay-opts { display: flex; gap: 10px; flex-wrap: wrap; }
        .pay-opt { flex: 1; min-width: 110px; position: relative; }
        .pay-opt input { position: absolute; opacity: 0; pointer-events: none; }
        .pay-opt label { display: block; text-align: center; border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 12px; cursor: pointer; font-size: var(--text-sm); color: var(--color-text-muted); transition: border-color .2s ease, color .2s ease, background-color .2s ease; }
        .pay-opt input:checked + label { border-color: var(--color-primary); color: var(--color-text); background: var(--gold-faint); }

        .sub-summary { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 28px; position: sticky; top: 90px; }
        .sub-summary .s-eyebrow { margin-bottom: 14px; }
        .sub-summary .s-plan { font-family: var(--font-script); font-size: var(--text-2xl); color: var(--color-primary); }
        .sub-summary .s-price { font-family: var(--font-mono); font-size: var(--text-3xl); color: var(--color-text); margin: 8px 0 2px; }
        .sub-summary .s-cycle { font-size: var(--text-xs); color: var(--color-text-muted); margin-bottom: 18px; }
        .sub-summary ul { list-style: none; margin: 0 0 18px; padding: 0; display: flex; flex-direction: column; gap: 8px; }
        .sub-summary li { color: var(--color-text-muted); font-size: var(--text-sm); padding-left: 20px; position: relative; }
        .sub-summary li::before { content: ""; position: absolute; left: 0; top: 6px; width: 8px; height: 8px; border-radius: 50%; border: 2px solid var(--color-primary); }
        .sub-note { font-size: var(--text-xs); color: var(--color-text-muted); border-top: 1px solid var(--color-border); padding-top: 14px; line-height: 1.6; }
        @media (max-width: 820px) { .sub-checkout { grid-template-columns: 1fr; } .sub-summary { position: static; } }
    </style>
    @endpush

    <x-slot name="header">
        <h1>Assinar o Clube</h1>
        <p style="color: var(--color-text-muted); margin: 4px 0 0; font-size: var(--text-sm);">Plano {{ $plan->name }} · revise os dados e confirme.</p>
    </x-slot>

    <a href="{{ route('pages.show', 'clube') }}" style="color: var(--color-text-muted); font-size: var(--text-sm); display: inline-block; margin-bottom: 20px;">← Voltar aos planos</a>

    <form method="POST" action="{{ route('subscription.store', $plan->slug) }}">
        @csrf
        <div class="sub-checkout">
            {{-- Coluna: dados de entrega + pagamento --}}
            <div>
                <div class="sub-card" style="margin-bottom: 20px;">
                    <h2>Endereço de entrega</h2>

                    <div class="field">
                        <label for="name">Destinatário</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $address->name ?? auth()->user()->name) }}" required>
                        @error('name')<span class="err">{{ $message }}</span>@enderror
                    </div>

                    <div class="row-cep">
                        <div class="field">
                            <label for="zipcode">CEP</label>
                            <input type="text" id="zipcode" name="zipcode" value="{{ old('zipcode', $address->zipcode ?? '') }}" placeholder="00000-000" required>
                            @error('zipcode')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="street">Rua / logradouro</label>
                            <input type="text" id="street" name="street" value="{{ old('street', $address->street ?? '') }}" required>
                            @error('street')<span class="err">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="field">
                            <label for="number">Número</label>
                            <input type="text" id="number" name="number" value="{{ old('number', $address->number ?? '') }}" required>
                            @error('number')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="complement">Complemento</label>
                            <input type="text" id="complement" name="complement" value="{{ old('complement', $address->complement ?? '') }}" placeholder="Apto, bloco…">
                            @error('complement')<span class="err">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="field">
                        <label for="neighborhood">Bairro</label>
                        <input type="text" id="neighborhood" name="neighborhood" value="{{ old('neighborhood', $address->neighborhood ?? '') }}" required>
                        @error('neighborhood')<span class="err">{{ $message }}</span>@enderror
                    </div>

                    <div class="grid-2">
                        <div class="field">
                            <label for="city">Cidade</label>
                            <input type="text" id="city" name="city" value="{{ old('city', $address->city ?? '') }}" required>
                            @error('city')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="state">UF</label>
                            <input type="text" id="state" name="state" maxlength="2" value="{{ old('state', $address->state ?? '') }}" placeholder="SP" required>
                            @error('state')<span class="err">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                <div class="sub-card">
                    <h2>Forma de pagamento</h2>
                    <span class="pay-label">Como prefere pagar a recorrência?</span>
                    @php $pm = old('payment_method', 'pix'); @endphp
                    <div class="pay-opts">
                        <div class="pay-opt">
                            <input type="radio" id="pm-pix" name="payment_method" value="pix" {{ $pm === 'pix' ? 'checked' : '' }}>
                            <label for="pm-pix">PIX</label>
                        </div>
                        <div class="pay-opt">
                            <input type="radio" id="pm-cartao" name="payment_method" value="cartao" {{ $pm === 'cartao' ? 'checked' : '' }}>
                            <label for="pm-cartao">Cartão</label>
                        </div>
                        <div class="pay-opt">
                            <input type="radio" id="pm-boleto" name="payment_method" value="boleto" {{ $pm === 'boleto' ? 'checked' : '' }}>
                            <label for="pm-boleto">Boleto</label>
                        </div>
                    </div>
                    @error('payment_method')<span class="err">{{ $message }}</span>@enderror

                    <div class="field" style="margin-top: 18px;">
                        <label for="notes">Observações (opcional)</label>
                        <textarea id="notes" name="notes" rows="2" placeholder="Preferências de entrega, sabores…">{{ old('notes') }}</textarea>
                        @error('notes')<span class="err">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>

            {{-- Coluna: resumo do plano --}}
            <div class="sub-summary">
                <span class="eyebrow s-eyebrow">Seu plano</span>
                <p class="s-plan">{{ $plan->name }}</p>
                <p class="s-price">{{ $plan->priceDisplay() }}<small style="font-size: var(--text-base); color: var(--color-text-muted);">{{ $plan->intervalLabel() }}</small></p>
                <p class="s-cycle">{{ $plan->kicker }}</p>
                <ul>
                    @foreach ($plan->perks ?? [] as $perk)
                        <li>{{ $perk }}</li>
                    @endforeach
                </ul>
                <button type="submit" class="btn-aptk btn-aptk--block" style="margin-bottom: 16px;">Confirmar assinatura</button>
                <p class="sub-note">Sem fidelidade — pause, troque ou cancele quando quiser pela sua conta. A cobrança recorrente é combinada após a confirmação.</p>
            </div>
        </div>
    </form>
</x-app-layout>
