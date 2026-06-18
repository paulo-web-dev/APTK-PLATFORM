@extends('layouts.admin')

@php $editing = $plan->exists; @endphp

@section('admin_title', $editing ? 'Editar plano' : 'Novo plano')

@push('styles')
<style>
    .field { margin-bottom: 16px; }
    .field label { display:block; font-family:var(--font-mono); font-size:var(--text-xs); letter-spacing:0.06em; text-transform:uppercase; color:var(--color-text-muted); margin-bottom:7px; }
    .field .hint { font-size:var(--text-xs); color:var(--color-text-muted); margin-top:6px; }
    .check { display:flex; align-items:center; gap:8px; cursor:pointer; margin-bottom:10px; }
    .check input { accent-color: var(--color-primary); }
</style>
@endpush

@section('content')
    <a href="{{ route('admin.plans.index') }}" class="back-link" style="display:inline-block; margin-bottom:16px;">← Voltar aos planos</a>

    <div class="content-head"><h1>{{ $editing ? 'Editar plano' : 'Novo plano' }}</h1></div>

    @if ($errors->any())
        <div class="alert-ok" style="color:var(--color-danger); border-left-color:var(--color-danger);">Confira os campos: {{ $errors->first() }}</div>
    @endif

    <form action="{{ $editing ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" method="POST">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="order-grid">
            <div>
                <div class="panel">
                    <div class="panel-head">Plano</div>
                    <div class="panel-body">
                        <div class="field">
                            <label>Nome</label>
                            <input type="text" name="name" class="admin-input" style="width:100%;" value="{{ old('name', $plan->name) }}">
                            @if ($editing)<p class="hint">Slug atual: <code>{{ $plan->slug }}</code> (mantido ao salvar).</p>@endif
                        </div>
                        <div class="field">
                            <label>Chamada (kicker)</label>
                            <input type="text" name="kicker" class="admin-input" style="width:100%;" value="{{ old('kicker', $plan->kicker) }}" placeholder="Curadoria mensal">
                        </div>
                        <div class="field" style="margin-bottom:0;">
                            <label>Benefícios (um por linha)</label>
                            <textarea name="perks" rows="6" class="admin-input" style="width:100%;" placeholder="2 drinks prontos por mês&#10;Frete grátis&#10;Acesso antecipado">{{ old('perks', is_array($plan->perks) ? implode("\n", $plan->perks) : '') }}</textarea>
                            <p class="hint">Cada linha vira um item na lista do plano.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="panel">
                    <div class="panel-head">Preço &amp; ciclo</div>
                    <div class="panel-body">
                        <div class="field">
                            <label>Preço (R$, deixe vazio p/ "sob consulta")</label>
                            <input type="number" step="0.01" min="0" name="price" class="admin-input" style="width:100%;" value="{{ old('price', $plan->price) }}">
                        </div>
                        <div class="field">
                            <label>Rótulo sem preço (opcional)</label>
                            <input type="text" name="price_label" class="admin-input" style="width:100%;" value="{{ old('price_label', $plan->price_label) }}" placeholder="Sob consulta">
                            <p class="hint">Usado quando não há preço (ex.: plano Corporativo).</p>
                        </div>
                        <div class="field" style="margin-bottom:0;">
                            <label>Ciclo de cobrança</label>
                            <select name="interval" class="admin-select" style="width:100%;">
                                <option value="monthly" {{ old('interval', $plan->interval) === 'monthly' ? 'selected' : '' }}>Mensal</option>
                                <option value="quarterly" {{ old('interval', $plan->interval) === 'quarterly' ? 'selected' : '' }}>Trimestral</option>
                                <option value="yearly" {{ old('interval', $plan->interval) === 'yearly' ? 'selected' : '' }}>Anual</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">Exibição</div>
                    <div class="panel-body">
                        <div class="field">
                            <label>Ordem</label>
                            <input type="number" min="0" name="sort_order" class="admin-input" style="width:100%;" value="{{ old('sort_order', $plan->sort_order ?? 0) }}">
                        </div>
                        <label class="check">
                            <input type="checkbox" name="featured" value="1" {{ old('featured', $plan->featured) ? 'checked' : '' }}> Destaque ("Mais assinado")
                        </label>
                        <label class="check" style="margin-bottom:0;">
                            <input type="checkbox" name="active" value="1" {{ old('active', $plan->active ?? true) ? 'checked' : '' }}> Ativo (visível no Clube)
                        </label>
                    </div>
                </div>

                <button type="submit" class="admin-btn" style="width:100%; justify-content:center;">{{ $editing ? 'Salvar alterações' : 'Criar plano' }}</button>
            </div>
        </div>
    </form>
@endsection
