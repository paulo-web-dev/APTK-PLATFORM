<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin → Novidades (leva 06): CRUD do mini-blog "Dicas e Novidades".
 * Capa em storage/app/public/posts (Storage::url para exibir).
 */
class PostController extends Controller
{
    public function index(): View
    {
        $posts = Post::orderByDesc('published_at')->orderByDesc('id')->paginate(20);

        return view('admin.posts.index', compact('posts'));
    }

    public function create(): View
    {
        return view('admin.posts.form', [
            'post' => new Post(['active' => true, 'published_at' => now()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        $data['slug']   = $this->uniqueSlug($data['title']);
        $data['active'] = $request->boolean('active');

        if ($request->hasFile('cover')) {
            $data['cover_path'] = $request->file('cover')->store('posts', 'public');
        }
        unset($data['cover']);

        Post::create($data);

        return redirect()->route('admin.posts.index')->with('status', 'Novidade publicada.');
    }

    public function edit(Post $post): View
    {
        return view('admin.posts.form', compact('post'));
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $data = $this->validateData($request);

        // Slug estável (links já compartilhados não quebram).
        $data['active'] = $request->boolean('active');

        if ($request->hasFile('cover')) {
            if ($post->cover_path) {
                Storage::disk('public')->delete($post->cover_path);
            }
            $data['cover_path'] = $request->file('cover')->store('posts', 'public');
        }
        unset($data['cover']);

        $post->update($data);

        return redirect()->route('admin.posts.index')->with('status', 'Novidade atualizada.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        if ($post->cover_path) {
            Storage::disk('public')->delete($post->cover_path);
        }

        $post->delete();

        return redirect()->route('admin.posts.index')->with('status', 'Novidade removida.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'excerpt'      => ['nullable', 'string', 'max:300'],
            'body'         => ['required', 'string'],
            'published_at' => ['required', 'date'],
            'cover'        => ['nullable', 'image', 'max:4096'],
            'active'       => ['nullable'],
        ]);
    }

    protected function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $n    = 2;

        while (Post::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
