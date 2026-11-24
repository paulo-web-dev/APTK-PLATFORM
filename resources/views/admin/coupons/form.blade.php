@extends('layouts.admin')

@php $editing = $coupon->exists; @endphp

@section('admin_title', $editing ? 'Editar cupom' : 'Novo cupom')

@push('styles')
<style>
    .field { margin-bottom: 16px; }
    .field label { display:block; font-family:var(--font-mono); font-size:var(--text-xs); letter-spacing:0.06em; text-transform:uppercase; color:var(--color-text-muted); margin-bottom:7px; }
    .field .hint { font-size:var(--text-xs); color:var(--color-text-muted); margin-top:6px; }
    .check { display:flex; align-items:center; gap:8px; cursor:pointer; margin-bottom:12px; }
    .check input { accent-color: var(--color-primary); }
    .field-row { display:grid; grid-template-columns: 1fr 1fr; gap: 12px; }
</style>
@endpush

@section('content')
    <a href="{{ $editing ? route('admin.coupons.show', $coupon) : route('admin.coupons.index') }}" class="back-link" style="display:inline-block; margin-bottom:16px;">← Voltar</a>

    <div class="content-head"><h1>{{ $editing ? 'Editar cupom '.$coupon->code : 'Novo cupom' }}</h1></div>

    @if ($errors->any())
        <div class="alert-ok" style="color:var(--color-danger); border-left-color:var(--color-danger);">Confira os campos: {{ $errors->first() }}</div>
    @endif

    <form action="{{ $editing ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" method="POST">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="order-grid">
            <div>
                <div class="panel">
                    <div class="panel-head">Cupom</div>
                    <div class="panel-body">
                        <div class="field">
                            <label for="code">Código</label>
                            <input type="text" id="code" name="code" class="admin-input" style="width:100%; text-transform:uppercase;" value="{{ old('code', $coupon->code) }}" placeholder="MITRE15" maxlength="50" required>
                            <p class="hint">Letras, números, hífen e sublinhado, sem espaço. Salvo em maiúsculas; o cliente pode digitar como quiser. Link que aplica sozinho: <code>{{ url('/') }}?cupom={{ $coupon->code ?: 'CODIGO' }}</code></p>
                        </div>
                        <div class="field">
                            <label for="name">Descrição interna (opcional)</label>
                            <input type="text" id="name" name="name" class="admin-input" style="width:100%;" value="{{ old('name', $coupon->name) }}" placeholder="Evento Mitre - mailing nov/2026" maxlength="120">
                        </div>
                        <div class="field-row">
                            <div class="field">
                                <label for="type">Tipo de desconto</label>
                                <select id="type" name="type" class="admin-select" style="width:100%;">
                                    <option value="percent" {{ old('type', $coupon->type) === 'percent' ? 'selected' : '' }}>Percentual (%)</option>
                                    <option value="fixed" {{ old('type', $coupon->type) === 'fixed' ? 'selected' : '' }}>Valor fixo (R$)</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="value">Valor</label>
                                <input type="number" id="value" step="0.01" min="0" name="value" class="admin-input" style="width:100%;" value="{{ old('value', $coupon->value) }}" required>
                            </div>
                        </div>
                        <p class="hint" style="margin:-8px 0 0;">Sempre sobre os produtos (garrafa personalizada inclusa), nunca sobre o frete. Percentual: 15 = 15%.</p>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">Validade (horário de Brasília)</div>
                    <div class="panel-body">
                        <div class="field-row">
                            <div class="field">
                                <label for="starts_at">Válido de</label>
                                <input type="text" id="starts_at" name="starts_at" class="admin-input js-data-hora" style="width:100%;" value="{{ old('starts_at', \App\Models\Coupon::paraCampo($coupon->starts_at)) }}" placeholder="dd/mm/aaaa hh:mm" inputmode="numeric" maxlength="16">
                            </div>
                            <div class="field">
                                <label for="expires_at">Válido até</label>
                                <input type="text" id="expires_at" name="expires_at" class="admin-input js-data-hora" style="width:100%;" value="{{ old('expires_at', \App\Models\Coupon::paraCampo($coupon->expires_at)) }}" placeholder="dd/mm/aaaa hh:mm" inputmode="numeric" maxlength="16">
                            </div>
                        </div>
                        <p class="hint" style="margin:-8px 0 0;">Em branco = sem limite. Ex.: 31/12/2026 23:59.</p>
                    </div>
                </div>
            </div>

            <div>
                <div class="panel">
                    <div class="panel-head">Regras</div>
                    <div class="panel-body">
                        <label class="check">
                            <input type="checkbox" name="active" value="1" {{ old('active', $coupon->active ?? true) ? 'checked' : '' }}> Ativo
                        </label>
                        <label class="check">
                            <input type="checkbox" name="first_purchase_only" value="1" {{ old('first_purchase_only', $coupon->first_purchase_only) ? 'checked' : '' }}> Só primeira compra
                        </label>
                        <p class="hint" style="margin:-6px 0 14px;">Recusa quem já tem pedido pago com o mesmo CPF ou e-mail.</p>
                        <div class="field">
                            <label for="max_per_customer">Usos por cliente</label>
                            <input type="number" id="max_per_customer" min="1" name="max_per_customer" class="admin-input" style="width:100%;" value="{{ old('max_per_customer', $coupon->max_per_customer) }}" placeholder="ilimitado">
                            <p class="hint">Mesmo CPF ou mesmo e-mail. Padrão 1.</p>
                        </div>
                        <div class="field">
                            <label for="max_uses">Limite total de usos (opcional)</label>
                            <input type="number" id="max_uses" min="1" name="max_uses" class="admin-input" style="width:100%;" value="{{ old('max_uses', $coupon->max_uses) }}" placeholder="ilimitado">
                            <p class="hint">Conta todo pedido com o cupom que não foi cancelado.</p>
                        </div>
                        <div class="field" style="margin-bottom:0;">
                            <label for="min_order">Pedido mínimo em produtos (R$, opcional)</label>
                            <input type="number" id="min_order" step="0.01" min="0" name="min_order" class="admin-input" style="width:100%;" value="{{ old('min_order', $coupon->min_order) }}">
                        </div>
                    </div>
                </div>

                <button type="submit" class="admin-btn" style="width:100%; justify-content:center;">{{ $editing ? 'Salvar alterações' : 'Criar cupom' }}</button>
            </div>
        </div>
    </form>

    <script>
      // Máscara dd/mm/aaaa hh:mm (o servidor valida o formato).
      document.querySelectorAll('.js-data-hora').forEach(function (el) {
        el.addEventListener('input', function () {
          var d = el.value.replace(/\D/g, '').slice(0, 12), out = '';
          for (var i = 0; i < d.length; i++) {
            if (i === 2 || i === 4) out += '/';
            if (i === 8) out += ' ';
            if (i === 10) out += ':';
            out += d[i];
          }
          el.value = out;
        });
      });
    </script>
@endsection
