@extends('layouts.public')

@section('title', 'Dicas e Novidades · APTK Spirits')
@section('meta_description', 'Dicas, novidades, eventos e receitas para harmonizar com os seus drinks — o conteúdo para entusiastas da APTK Spirits.')

@push('styles')
<style>
  .nv-hero { border-bottom: 1px solid var(--color-border); }
  .nv-hero .container-aptk { text-align: center; max-width: 720px; padding-block: clamp(44px, 6vw, 64px); display: flex; flex-direction: column; align-items: center; }
  .nv-hero .script-line { font-family: var(--font-script); font-size: clamp(1.5rem, 3.5vw, 2.2rem); color: var(--color-primary); margin-bottom: 8px; }
  .nv-hero h1 { font-size: clamp(1.9rem, 4.5vw, 2.8rem); margin: 0 0 12px; }
  .nv-hero p { color: var(--color-text-muted); font-size: var(--text-lg); margin: 0; }

  .nv-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
  .nv-card { display: flex; flex-direction: column; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; text-decoration: none; transition: border-color .25s ease, transform .25s ease; }
  .nv-card:hover { border-color: var(--color-primary-muted); transform: translateY(-3px); }
  .nv-cover { position: relative; aspect-ratio: 16 / 10; background: repeating-linear-gradient(45deg, var(--color-bg-card), var(--color-bg-card) 12px, var(--color-bg) 12px, var(--color-bg) 24px); }
  .nv-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .nv-date { position: absolute; top: 12px; left: 12px; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-sm); text-align: center; padding: 6px 10px; line-height: 1.15; }
  .nv-date .d { font-family: var(--font-mono); font-size: var(--text-lg); color: var(--color-text); display: block; }
  .nv-date .m { font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.14em; text-transform: uppercase; color: var(--color-primary); }
  .nv-body { padding: 18px 20px 22px; display: flex; flex-direction: column; gap: 8px; }
  .nv-body h2 { font-family: var(--font-display); font-size: var(--text-xl); color: var(--color-text); margin: 0; }
  .nv-body p { color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.65; margin: 0; }
  @media (max-width: 980px) { .nv-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
  <section class="nv-hero">
    <div class="container-aptk">
      <p class="script-line">Dicas e Novidades</p>
      <h1>Conteúdo para entusiastas</h1>
      <p>Dicas, novidades, eventos e receitas para harmonizar com os seus drinks.</p>
    </div>
  </section>

  <section class="section">
    <div class="container-aptk">
      @if ($posts->isEmpty())
        <p style="text-align:center; color:var(--color-text-muted);">Nenhuma publicação por aqui ainda — em breve, as primeiras dicas da casa.</p>
      @else
        <div class="nv-grid">
          @foreach ($posts as $post)
            <a href="{{ route('novidades.show', $post->slug) }}" class="nv-card">
              <div class="nv-cover">
                @if ($post->cover_path)
                  <img src="{{ \Illuminate\Support\Facades\Storage::url($post->cover_path) }}" alt="{{ $post->title }}" loading="lazy">
                @endif
                <span class="nv-date">
                  <span class="d">{{ $post->published_at->format('d') }}</span>
                  <span class="m">{{ $post->published_at->translatedFormat('M') }}</span>
                </span>
              </div>
              <div class="nv-body">
                <h2>{{ $post->title }}</h2>
                @if ($post->excerpt)
                  <p>{{ $post->excerpt }}</p>
                @endif
              </div>
            </a>
          @endforeach
        </div>
        <div style="margin-top:26px;">{{ $posts->links() }}</div>
      @endif
    </div>
  </section>
@endsection
