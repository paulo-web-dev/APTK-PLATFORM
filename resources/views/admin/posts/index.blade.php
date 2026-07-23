@extends('layouts.admin')

@section('admin_title', 'Novidades')

@section('content')
    <div class="content-head" style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div>
            <h1>Dicas e Novidades</h1>
            <p>{{ $posts->total() }} {{ $posts->total() === 1 ? 'publicação' : 'publicações' }}</p>
        </div>
        <a href="{{ route('admin.posts.create') }}" class="admin-btn">+ Nova publicação</a>
    </div>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif

    <div class="data-card">
        <table class="data-table">
            <thead>
                <tr><th>Título</th><th>Publicado em</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($posts as $post)
                    <tr>
                        <td data-label="Título">
                            {{ $post->title }}
                            @if ($post->cover_path)
                                <span class="td-muted" style="font-size:var(--text-xs);"> · com capa</span>
                            @endif
                        </td>
                        <td class="td-muted" data-label="Publicado em">{{ $post->published_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td data-label="Status">
                            @if ($post->active && $post->published_at && $post->published_at->isPast())
                                <span class="status status--pago">No ar</span>
                            @elseif ($post->active)
                                <span class="status status--pendente">Agendada</span>
                            @else
                                <span class="status status--cancelado">Inativa</span>
                            @endif
                        </td>
                        <td data-label="" style="text-align:right; white-space:nowrap;">
                            <a href="{{ route('novidades.show', $post->slug) }}" class="admin-btn admin-btn--ghost" style="padding:6px 12px;" target="_blank" rel="noopener">Ver</a>
                            <a href="{{ route('admin.posts.edit', $post) }}" class="admin-btn admin-btn--ghost" style="padding:6px 12px;">Editar</a>
                            <form action="{{ route('admin.posts.destroy', $post) }}" method="POST" style="display:inline;" onsubmit="return confirm('Remover {{ addslashes($post->title) }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="admin-btn admin-btn--ghost" style="padding:6px 12px; color:var(--color-danger);">Excluir</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="td-muted" style="text-align:center; padding:28px;">Nenhuma publicação ainda — crie a primeira dica.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px;">{{ $posts->links() }}</div>
@endsection
