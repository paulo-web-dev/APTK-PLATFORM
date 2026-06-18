@extends('layouts.admin')

@php $editing = $coupon->exists; @endphp

@section('admin_title', $editing ? 'Editar cupom' : 'Novo cupom')

@push('styles')
<style>
    .field { margin-bottom: 16px; }
    .field label { display:block; font-family:var(--font-mono); font-size:var(--text-xs); letter-spacing:0.06em; text-transform:uppercase; color:var(--color-text-muted); margin-bottom:7px; }
    .field .hint { font-size:var(--text-xs); color:var(--color-text-muted); margin-top:6px; }
    .check { display:flex; align-items:center; gap:8px; cursor:pointer; }
    .check input { accent-color: var(--color-primary); }
</style>
@endpush

@section('content')
    <a href="{{ route('admin.coupons.index') }}" class="back-link" style="display:inline-block; margin-bottom:16px;">← Voltar aos cupons</a>

    <div class="content-head"><h1>{{ $editing ? 'Editar cupom' : 'Novo cupom' }}</h1></div>

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
                            <label>Código</label>
                            <input type="text" name="code" class="admin-input" style="width:100%; text-transform:uppercase;" value="{{ old('code', $coupon->code) }}" placeholder="BEMVINDO10">
                            <p class="hint">Sem espaços. Será salvo em maiúsculas.</p>
                        </div>
                        <div class="field">
                            <label>Tipo de desconto</label>
                            <select name="type" class="admin-select" style="width:100%;">
                                <option value="percent" {{ old('type', $coupon->type) === 'percent' ? 'selected' : '' }}>Percentual (%)</option>
                                <option value="fixed" {{ old('type', $coupon->type) === 'fixed' ? 'selected' : '' }}>Valor fixo (R$)</option>
                            </select>
                        </div>
                        <div class="field" style="margin-bottom:0;">
                            <label>Valor</label>
                            <input type="number" step="0.01" min="0" name="value" class="admin-input" style="width:100%;" value="{{ old('value', $coupon->value) }}">
                            <p class="hint">Para percentual, use o número (ex.: 10 = 10%).</p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="panel">
                    <div class="panel-head">Regras</div>
                    <div class="panel-body">
                        <div class="field">
                            <label>Pedido mínimo (R$, opcional)</label>
                            <input type="number" step="0.01" min="0" name="min_order" class="admin-input" style="width:100%;" value="{{ old('min_order', $coupon->min_order) }}">
                        </div>
                        <div class="field">
                            <label>Limite de usos (opcional)</label>
                            <input type="number" min="1" name="max_uses" class="admin-input" style="width:100%;" value="{{ old('max_uses', $coupon->max_uses) }}" placeholder="ilimitado">
                            @if ($editing)<p class="hint">Já utilizado {{ $coupon->used_count }}× até agora.</p>@endif
                        </div>
                        <div class="field">
                            <label>Validade (opcional)</label>
                            <input type="date" name="expires_at" class="admin-input" style="width:100%;" value="{{ old('expires_at', $coupon->expires_at?->format('Y-m-d')) }}">
                        </div>
                        <label class="check">
                            <input type="checkbox" name="active" value="1" {{ old('active', $coupon->active ?? true) ? 'checked' : '' }}> Ativo
                        </label>
                    </div>
                </div>

                <button type="submit" class="admin-btn" style="width:100%; justify-content:center;">{{ $editing ? 'Salvar alterações' : 'Criar cupom' }}</button>
            </div>
        </div>
    </form>
@endsection
