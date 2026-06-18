@extends('layouts.admin')

@php $editing = $category->exists; @endphp

@section('admin_title', $editing ? 'Editar categoria' : 'Nova categoria')

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
    <a href="{{ route('admin.categories.index') }}" class="back-link" style="display:inline-block; margin-bottom:16px;">← Voltar às categorias</a>

    <div class="content-head"><h1>{{ $editing ? 'Editar categoria' : 'Nova categoria' }}</h1></div>

    @if ($errors->any())
        <div class="alert-ok" style="color:var(--color-danger); border-left-color:var(--color-danger);">Confira os campos: {{ $errors->first() }}</div>
    @endif

    <form action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" method="POST">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="order-grid">
            <div>
                <div class="panel">
                    <div class="panel-head">Dados</div>
                    <div class="panel-body">
                        <div class="field">
                            <label>Nome</label>
                            <input type="text" name="name" class="admin-input" style="width:100%;" value="{{ old('name', $category->name) }}">
                            @if ($editing)<p class="hint">Slug atual: <code>{{ $category->slug }}</code> (mantido ao salvar).</p>@endif
                        </div>
                        <div class="field" style="margin-bottom:0;">
                            <label>Descrição</label>
                            <textarea name="description" rows="4" class="admin-input" style="width:100%;">{{ old('description', $category->description) }}</textarea>
                            <p class="hint">Aparece na capa da seção, no catálogo.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="panel">
                    <div class="panel-head">Organização</div>
                    <div class="panel-body">
                        <div class="field">
                            <label>Ordem</label>
                            <input type="number" min="0" name="sort_order" class="admin-input" style="width:100%;" value="{{ old('sort_order', $category->sort_order ?? 0) }}">
                            <p class="hint">Menor número aparece primeiro.</p>
                        </div>
                        <label class="check">
                            <input type="checkbox" name="active" value="1" {{ old('active', $category->active ?? true) ? 'checked' : '' }}> Ativa (visível na loja)
                        </label>
                    </div>
                </div>

                <button type="submit" class="admin-btn" style="width:100%; justify-content:center;">{{ $editing ? 'Salvar alterações' : 'Criar categoria' }}</button>
            </div>
        </div>
    </form>
@endsection
