@extends('layouts.public')

@section('title', $post->title . ' · Dicas e Novidades · APTK Spirits')
@section('meta_description', $post->excerpt ?? \Illuminate\Support\Str::limit(strip_tags($post->body), 150))

@push('styles')
<style>
  .pv-hero { border-bottom: 1px solid var(--color-border); }
  .pv-hero .container-aptk { max-width: 760px; padding-block: clamp(40px, 6vw, 60px); }
  .pv-hero .meta { font-family: var(--font-mono); font-size: var(--text-xs); letter-spacing: 0.12em; text-transform: uppercase; color: var(--color-primary); margin-bottom: 12px; display: block; }
  .pv-hero h1 { font-size: clamp(1.9rem, 4.5vw, 2.8rem); margin: 0; }
  .pv-cover { max-width: 920px; margin: clamp(28px, 4vw, 44px) auto 0; padding-inline: 20px; }
  .pv-cover img { width: 100%; border-radius: var(--radius-lg); border: 1px solid var(--color-border); display: block; }
  .pv-body { max-width: 720px; margin-inline: auto; padding: clamp(28px, 4vw, 44px) 20px clamp(48px, 6vw, 72px); }
  .pv-body p { color: var(--color-text-muted); font-size: var(--text-base); line-height: 1.9; margin: 0 0 20px; }
  .pv-related { border-top: 1px solid var(--color-border); }
  .pv-related h2 { font-family: var(--font-display); font-size: var(--text-2xl); text-align: center; margin: 0 0 24px; }
  .pv-rel-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; max-width: 920px; margin-inline: auto; }
  .pv-rel-grid a { display: block; padding: 18px 20px; border: 1px solid var(--color-border); border-radius: var(--radius-md); text-decoration: none; color: var(--color-text); font-family: var(--font-display); transition: border-color .2s ease; }
  .pv-rel-grid a:hover { border-color: var(--color-primary-muted); }
  .pv-rel-grid .rd { display: block; font-family: var(--font-mono); font-size: var(--text-xs); color: var(--color-primary); margin-bottom: 6px; }
  @media (max-width: 860px) { .pv-rel-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
  <section class="pv-hero">
    <div class="container-aptk">
      <a href="{{ route('novidades.index') }}" class="back-link" style="display:inline-block; margin-bottom:18px;">← Todas as novidades</a>
      <span class="meta">{{ $post->published_at->translatedFormat('d \d\e F \d\e Y') }}</span>
      <h1>{{ $post->title }}</h1>
    </div>
  </section>

  @if ($post->cover_path)
    <div class="pv-cover">
      <img src="{{ \Illuminate\Support\Facades\Storage::url($post->cover_path) }}" alt="{{ $post->title }}">
    </div>
  @endif

  <article class="pv-body">
    @foreach (preg_split('/\R{2,}/', trim($post->body)) as $paragraph)
      <p>{!! nl2br(e($paragraph)) !!}</p>
    @endforeach
  </article>

  @if ($related->isNotEmpty())
    <section class="section pv-related">
      <div class="container-aptk">
        <h2>Continue lendo</h2>
        <div class="pv-rel-grid">
          @foreach ($related as $rel)
            <a href="{{ route('novidades.show', $rel->slug) }}">
              <span class="rd">{{ $rel->published_at->format('d/m/Y') }}</span>
              {{ $rel->title }}
            </a>
          @endforeach
        </div>
      </div>
    </section>
  @endif
@endsection
