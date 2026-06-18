@extends('layouts.admin')

@section('admin_title', 'Configurações')

@push('styles')
<style>
    .set-row { display:grid; grid-template-columns: 240px 1fr auto auto; gap:12px; align-items:center; padding:12px 0; border-bottom:1px solid var(--color-border); }
    .set-row:last-child { border-bottom:none; }
    .set-key { font-family:var(--font-mono); font-size:var(--text-sm); color:var(--color-text); word-break:break-all; }
    .set-row form { display:flex; gap:8px; margin:0; align-items:center; }
    .set-row .val-form { flex:1; }
    .set-row .val-form input { flex:1; }
    .add-grid { display:grid; grid-template-columns: 1fr 1.4fr 140px auto; gap:12px; align-items:end; }
    .add-grid .field { margin:0; }
    .field label { display:block; font-family:var(--font-mono); font-size:var(--text-xs); letter-spacing:0.06em; text-transform:uppercase; color:var(--color-text-muted); margin-bottom:7px; }
    @media (max-width:860px) { .set-row, .add-grid { grid-template-columns:1fr; align-items:stretch; } }
</style>
@endpush

@section('content')
    <div class="content-head">
        <h1>Configurações</h1>
        <p>Pares de chave/valor da loja. Use <code>Setting::get('chave')</code> para ler no código.</p>
    </div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert-ok" style="color:var(--color-danger); border-left-color:var(--color-danger);">{{ $errors->first() }}</div>
    @endif

    @forelse ($settings as $group => $items)
        <div class="panel">
            <div class="panel-head" style="text-transform:capitalize;">{{ $group }}</div>
            <div class="panel-body" style="padding-top:6px; padding-bottom:6px;">
                @foreach ($items as $setting)
                    <div class="set-row">
                        <span class="set-key">{{ $setting->key }}</span>
                        <form method="POST" action="{{ route('admin.settings.update', $setting) }}" class="val-form">
                            @csrf @method('PUT')
                            <input type="text" name="value" class="admin-input" value="{{ $setting->value }}" placeholder="(vazio)">
                            <button type="submit" class="admin-btn admin-btn--ghost">Salvar</button>
                        </form>
                        <span></span>
                        <form method="POST" action="{{ route('admin.settings.destroy', $setting) }}" onsubmit="return confirm('Remover a chave {{ $setting->key }}?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="admin-btn admin-btn--ghost" style="color:var(--color-danger);">Excluir</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="data-card" style="padding:40px; text-align:center;">
            <p class="td-muted" style="margin:0;">Nenhuma configuração ainda. Adicione a primeira abaixo.</p>
        </div>
    @endforelse

    <div class="panel" style="margin-top:24px;">
        <div class="panel-head">Adicionar configuração</div>
        <div class="panel-body">
            <form method="POST" action="{{ route('admin.settings.store') }}">
                @csrf
                <div class="add-grid">
                    <div class="field">
                        <label>Chave</label>
                        <input type="text" name="key" class="admin-input" value="{{ old('key') }}" placeholder="store_name">
                    </div>
                    <div class="field">
                        <label>Valor</label>
                        <input type="text" name="value" class="admin-input" value="{{ old('value') }}" placeholder="APTK Spirits">
                    </div>
                    <div class="field">
                        <label>Grupo</label>
                        <input type="text" name="group" class="admin-input" value="{{ old('group', 'general') }}" placeholder="general">
                    </div>
                    <button type="submit" class="admin-btn">+ Adicionar</button>
                </div>
                <p class="td-muted" style="font-size:var(--text-xs); margin:12px 0 0;">A chave aceita apenas letras, números, ponto e underscore (ex.: <code>loja.whatsapp</code>).</p>
            </form>
        </div>
    </div>
@endsection
