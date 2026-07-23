@extends('layouts.admin')

@php $editing = $post->exists; @endphp

@section('admin_title', $editing ? 'Editar publicação' : 'Nova publicação')

@push('styles')
<style>
    .field { margin-bottom: 16px; }
    .field label { display:block; font-family:var(--font-mono); font-size:var(--text-xs); letter-spacing:0.06em; text-transform:uppercase; color:var(--color-text-muted); margin-bottom:7px; }
    .field .hint { font-size:var(--text-xs); color:var(--color-text-muted); margin-top:6px; }
    .check { display:flex; align-items:center; gap:8px; cursor:pointer; }
    .check input { accent-color: var(--color-primary); }
    .cover-preview { max-width:280px; border-radius:var(--radius-md); border:1px solid var(--color-border); display:block; margin-bottom:10px; }
</style>
@endpush

@section('content')
    <a href="{{ route('admin.posts.index') }}" class="back-link" style="display:inline-block; margin-bottom:16px;">← Voltar às novidades</a>

    <div class="content-head"><h1>{{ $editing ? 'Editar publicação' : 'Nova publicação' }}</h1></div>

    @if ($errors->any())
        <div class="alert-ok" style="color:var(--color-danger); border-left-color:var(--color-danger);">Confira os campos: {{ $errors->first() }}</div>
    @endif

    <form action="{{ $editing ? route('admin.posts.update', $post) : route('admin.posts.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="data-card" style="padding:24px; max-width:820px;">
            <div class="field">
                <label>Título</label>
                <input type="text" name="title" class="admin-input" style="width:100%;" value="{{ old('title', $post->title) }}" required maxlength="255">
            </div>

            <div class="field">
                <label>Resumo (aparece no card — opcional)</label>
                <input type="text" name="excerpt" class="admin-input" style="width:100%;" value="{{ old('excerpt', $post->excerpt) }}" maxlength="300" placeholder="Uma frase que convida à leitura.">
            </div>

            <div class="field">
                <label>Texto</label>
                <textarea name="body" class="admin-input" style="width:100%; min-height:280px; resize:vertical;" required>{{ old('body', $post->body) }}</textarea>
                <p class="hint">Parágrafos separados por linha em branco. O texto é publicado como está.</p>
            </div>

            <div class="field">
                <label>Capa (opcional, até 4 MB)</label>
                @if ($editing && $post->cover_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($post->cover_path) }}" alt="" class="cover-preview">
                @endif
                <input type="file" name="cover" accept="image/*">
                @if ($editing && $post->cover_path)
                    <p class="hint">Enviar uma nova capa substitui a atual.</p>
                @endif
            </div>

            <div class="field">
                <label>Publicado em</label>
                <input type="datetime-local" name="published_at" class="admin-input" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}" required>
                <p class="hint">Data futura = agendada (só aparece no site quando chegar a hora).</p>
            </div>

            <div class="field">
                <label class="check">
                    <input type="checkbox" name="active" value="1" {{ old('active', $post->active) ? 'checked' : '' }}>
                    Ativa (visível no site)
                </label>
            </div>

            <button type="submit" class="admin-btn">{{ $editing ? 'Salvar alterações' : 'Publicar' }}</button>
        </div>
    </form>
@endsection
