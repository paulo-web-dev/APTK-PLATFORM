@extends('layouts.admin')

@php
    $editing = $product->exists;
@endphp

@section('admin_title', $editing ? 'Editar produto' : 'Novo produto')

@push('styles')
<style>
    .field { margin-bottom: 16px; }
    .field label { display:block; font-family:var(--font-mono); font-size:var(--text-xs); letter-spacing:0.06em; text-transform:uppercase; color:var(--color-text-muted); margin-bottom:7px; }
    .check { display:flex; align-items:center; gap:8px; cursor:pointer; margin-bottom:10px; }
    .check input { accent-color: var(--color-primary); }
</style>
@endpush

@section('content')
    <a href="{{ route('admin.products.index') }}" class="back-link" style="display:inline-block; margin-bottom:16px;">← Voltar aos produtos</a>

    <div class="content-head"><h1>{{ $editing ? 'Editar produto' : 'Novo produto' }}</h1></div>

    @if ($errors->any())
        <div class="alert-ok" style="color:var(--color-danger); border-left-color:var(--color-danger);">Confira os campos: {{ $errors->first() }}</div>
    @endif

    <form action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="order-grid">
            <div>
                <div class="panel">
                    <div class="panel-head">Dados</div>
                    <div class="panel-body">
                        <div class="field">
                            <label>Nome</label>
                            <input type="text" name="name" class="admin-input" style="width:100%;" value="{{ old('name', $product->name) }}">
                        </div>
                        <div class="field">
                            <label>Descrição curta</label>
                            <input type="text" name="short_description" class="admin-input" style="width:100%;" value="{{ old('short_description', $product->short_description) }}">
                        </div>
                        <div class="field" style="margin-bottom:0;">
                            <label>Descrição</label>
                            <textarea name="description" rows="5" class="admin-input" style="width:100%;">{{ old('description', $product->description) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">Ficha técnica</div>
                    <div class="panel-body">
                        <div class="field">
                            <label>Base</label>
                            <input type="text" name="base" class="admin-input" style="width:100%;" value="{{ old('base', $product->base) }}" placeholder="APTK Gin">
                        </div>
                        <div class="field">
                            <label>Teor alcoólico (% vol.)</label>
                            <input type="number" min="0" max="100" name="abv" class="admin-input" style="width:100%;" value="{{ old('abv', $product->abv) }}">
                        </div>
                        <div class="field" style="margin-bottom:0;">
                            <label>Volumes</label>
                            <input type="text" name="sizes" class="admin-input" style="width:100%;" value="{{ old('sizes', is_array($product->sizes) ? implode(', ', $product->sizes) : '') }}" placeholder="100 ml, 375 ml, 750 ml">
                            <p style="font-size:var(--text-xs); color:var(--color-text-muted); margin-top:6px;">Separe por vírgula. Aparecem como chips na página do produto.</p>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">Imagem</div>
                    <div class="panel-body">
                        @if ($product->primaryImage)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($product->primaryImage->path) }}" alt="" style="width:120px; height:120px; object-fit:cover; border-radius:var(--radius-md); border:1px solid var(--color-border); margin-bottom:12px; display:block;">
                            <p class="td-muted" style="font-size:var(--text-xs); margin:0 0 10px;">Enviar uma nova imagem substitui a atual.</p>
                        @endif
                        <input type="file" name="image" accept="image/*" class="admin-input" style="width:100%;">
                    </div>
                </div>
            </div>

            <div>
                <div class="panel">
                    <div class="panel-head">Organização</div>
                    <div class="panel-body">
                        <div class="field">
                            <label>Categoria</label>
                            <select name="category_id" class="admin-select" style="width:100%;">
                                <option value="">— sem categoria —</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ (int) old('category_id', $product->category_id) === $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="check">
                            <input type="checkbox" name="active" value="1" {{ old('active', $product->active ?? true) ? 'checked' : '' }}> Ativo (visível na loja)
                        </label>
                        <label class="check" style="margin-bottom:0;">
                            <input type="checkbox" name="featured" value="1" {{ old('featured', $product->featured) ? 'checked' : '' }}> Destaque
                        </label>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">Preço &amp; estoque</div>
                    <div class="panel-body">
                        <div class="field">
                            <label>Preço (R$)</label>
                            <input type="number" step="0.01" min="0" name="price" class="admin-input" style="width:100%;" value="{{ old('price', $product->price) }}">
                        </div>
                        <div class="field">
                            <label>Preço "de" (opcional)</label>
                            <input type="number" step="0.01" min="0" name="compare_price" class="admin-input" style="width:100%;" value="{{ old('compare_price', $product->compare_price) }}">
                        </div>
                        <div class="field">
                            <label>SKU</label>
                            <input type="text" name="sku" class="admin-input" style="width:100%;" value="{{ old('sku', $product->sku) }}">
                        </div>
                        <div class="field">
                            <label>Estoque</label>
                            <input type="number" min="0" name="stock_qty" class="admin-input" style="width:100%;" value="{{ old('stock_qty', $product->stock_qty ?? 0) }}">
                        </div>
                        <div class="field" style="margin-bottom:0;">
                            <label>Peso (kg, opcional)</label>
                            <input type="number" step="0.001" min="0" name="weight" class="admin-input" style="width:100%;" value="{{ old('weight', $product->weight) }}">
                        </div>
                    </div>
                </div>

                <button type="submit" class="admin-btn" style="width:100%; justify-content:center;">{{ $editing ? 'Salvar alterações' : 'Criar produto' }}</button>
            </div>
        </div>
    </form>
@endsection
