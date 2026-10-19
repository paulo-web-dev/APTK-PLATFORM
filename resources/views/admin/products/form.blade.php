@extends('layouts.admin')

@php
    $editing = $product->exists;
    // Preço/estoque travados quando o ERP manda (fonte=erp + vínculo).
    $erpLock = $editing && $product->managedByErp();

    // Preços por volume na ORDEM dos volumes (é assim que o save casa um com
    // o outro); mapa incompleto cai na ordem gravada, como antes.
    $precosPorVolume = '';
    if (is_array($product->size_prices)) {
        $ordem = is_array($product->sizes) && array_diff($product->sizes, array_keys($product->size_prices)) === []
            ? array_map(fn ($s) => $product->size_prices[$s], $product->sizes)
            : array_values($product->size_prices);
        $precosPorVolume = implode('; ', array_map(fn ($v) => number_format((float) $v, 2, ',', '.'), $ordem));
    }
@endphp

@section('admin_title', $editing ? 'Editar produto' : 'Novo produto')

@push('styles')
<style>
    .field { margin-bottom: 16px; }
    .field label { display:block; font-family:var(--font-mono); font-size:var(--text-xs); letter-spacing:0.06em; text-transform:uppercase; color:var(--color-text-muted); margin-bottom:7px; }
    .check { display:flex; align-items:center; gap:8px; cursor:pointer; margin-bottom:10px; }
    .check input { accent-color: var(--color-primary); }
    .field .err { display:block; color:var(--color-danger); font-size:var(--text-xs); margin-top:6px; }
    .gal { display:grid; grid-template-columns:repeat(auto-fill, minmax(96px, 1fr)); gap:12px; margin-bottom:14px; }
    .gal-item { display:flex; flex-direction:column; gap:6px; }
    .gal-item .prod-thumb { width:100%; aspect-ratio:2 / 3; }
    .gal-item.is-primary .prod-thumb { border-color:var(--color-primary); }
    .gal-tag { font-family:var(--font-mono); font-size:10px; letter-spacing:.06em; text-transform:uppercase; color:var(--color-primary); }
    .gal-rm { background:none; border:none; padding:0; color:var(--color-danger); font-size:var(--text-xs); cursor:pointer; text-align:left; }
    .gal-rm:hover { text-decoration:underline; }
</style>
@endpush

@section('content')
    <a href="{{ route('admin.products.index') }}" class="back-link" style="display:inline-block; margin-bottom:16px;">← Voltar aos produtos</a>

    <div class="content-head"><h1>{{ $editing ? 'Editar produto' : 'Novo produto' }}</h1></div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif

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
                        <div class="field">
                            <label>Volumes</label>
                            <input type="text" name="sizes" class="admin-input" style="width:100%;" value="{{ old('sizes', is_array($product->sizes) ? implode(', ', $product->sizes) : '') }}" placeholder="375 ml, 750 ml">
                            <p style="font-size:var(--text-xs); color:var(--color-text-muted); margin-top:6px;">Separe por vírgula. Viram o seletor de volume na loja.</p>
                        </div>
                        <div class="field" style="margin-bottom:0;">
                            <label>Preços por volume (R$)</label>
                            <input type="text" name="size_prices" class="admin-input" style="width:100%;" @disabled($erpLock)
                                   value="{{ old('size_prices', $precosPorVolume) }}"
                                   placeholder="109,90; 189,90">
                            @error('size_prices') <span class="err">{{ $message }}</span> @enderror
                            <p style="font-size:var(--text-xs); color:var(--color-text-muted); margin-top:6px;">Na mesma ordem dos volumes, separados por ponto-e-vírgula. Vazio = todos os volumes usam o preço base.</p>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">Imagens</div>
                    <div class="panel-body">
                        @if ($editing && $product->images->isNotEmpty())
                            <div class="gal">
                                @foreach ($product->images as $img)
                                    <div class="gal-item {{ $img->is_primary ? 'is-primary' : '' }}">
                                        <x-product.image class="prod-thumb" :image="$img" alt="" />
                                        @if ($img->is_primary)<span class="gal-tag">Principal</span>@endif
                                        {{-- Form de remoção fica FORA deste form (form não aninha). --}}
                                        <button type="submit" class="gal-rm" form="rm-img-{{ $img->id }}">Remover</button>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="gal">
                                <div class="gal-item"><x-product.image class="prod-thumb" alt="" /></div>
                            </div>
                            <p class="td-muted" style="font-size:var(--text-xs); margin:0 0 10px;">Sem foto: a loja mostra o placeholder.</p>
                        @endif
                        <input type="file" name="image" accept="image/*" class="admin-input" style="width:100%;">
                        <p class="td-muted" style="font-size:var(--text-xs); margin:8px 0 0;">A imagem enviada entra como principal; as outras continuam na galeria.</p>
                        @error('image') <span class="err" style="color:var(--color-danger); font-size:var(--text-xs);">{{ $message }}</span> @enderror
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
                        @error('active')
                            <p style="color:var(--color-danger); font-size:var(--text-xs); margin:-4px 0 10px;">{{ $message }}</p>
                        @else
                            @if ($editing && ! $product->temPreco())
                                <p class="td-muted" style="font-size:var(--text-xs); margin:-4px 0 10px;">Sem preço: só pode ser ativado depois de ter preço.</p>
                            @elseif ($editing && ! $product->temFotoNoPadrao())
                                <p class="td-muted" style="font-size:var(--text-xs); margin:-4px 0 10px;">Sem foto: envie uma foto do produto antes de ativar.</p>
                            @endif
                        @enderror
                        <label class="check" style="margin-bottom:0;">
                            <input type="checkbox" name="featured" value="1" {{ old('featured', $product->featured) ? 'checked' : '' }}> Destaque
                        </label>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">Preço &amp; estoque</div>
                    <div class="panel-body">
                        @if ($erpLock)
                            <div class="field" style="font-size:.85em; color:var(--color-text-muted); border:1px solid var(--color-border); border-left:3px solid var(--color-primary); border-radius:var(--radius-sm); padding:10px 12px;">
                                Preço e estoque deste produto são <strong>gerenciados pelo ERP</strong> (Microvix)
                                e sobrescritos a cada conciliação — edite lá.
                                <a href="{{ route('admin.microvix.products.index') }}">Ver vínculos</a>
                            </div>
                        @endif
                        <div class="field">
                            <label>Preço (R$)</label>
                            <input type="number" step="0.01" min="0" name="price" class="admin-input" style="width:100%;" value="{{ old('price', $product->price) }}" @disabled($erpLock)>
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
                            <input type="number" min="0" name="stock_qty" class="admin-input" style="width:100%;" value="{{ old('stock_qty', $product->stock_qty ?? 0) }}" @disabled($erpLock)>
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

    @if ($editing)
        @foreach ($product->images as $img)
            <form id="rm-img-{{ $img->id }}" action="{{ route('admin.products.images.destroy', [$product, $img]) }}" method="POST"
                  onsubmit="return confirm('Remover esta imagem da galeria?');" hidden>
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    @endif
@endsection
