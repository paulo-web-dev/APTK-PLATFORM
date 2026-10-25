<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Capas dos 3 posts de exemplo (PostsExemploSeeder nunca preencheu
 * cover_path). Copia a foto de public/img/aptk/ (versionada) para
 * storage/app/public/posts/, onde o admin guarda capa de post: a gestão
 * troca depois pelo admin, que apaga a cópia ao substituir. Só mexe em post
 * que ainda está sem capa.
 */
return new class extends Migration
{
    /** slug => [foto em public/img/aptk, arquivo em posts/] */
    private const CAPAS = [
        'negroni-em-casa-3-detalhes'      => ['post-negroni-em-casa.jpg', 'posts/capa-negroni-em-casa.jpg'],
        'gelo-cristal-por-que-importa'    => ['post-gelo-cristal.jpg', 'posts/capa-gelo-cristal.jpg'],
        'como-harmonizar-drinks-e-comida' => ['post-harmonizar-drinks.jpg', 'posts/capa-harmonizar-drinks.jpg'],
    ];

    public function up(): void
    {
        foreach (self::CAPAS as $slug => [$foto, $destino]) {
            $post = DB::table('posts')->where('slug', $slug)->first(['id', 'cover_path']);
            if (! $post || $post->cover_path) {
                continue;
            }

            Storage::disk('public')->put($destino, file_get_contents(public_path('img/aptk/'.$foto)));
            DB::table('posts')->where('id', $post->id)->update(['cover_path' => $destino]);
        }
    }

    public function down(): void
    {
        foreach (self::CAPAS as $slug => [, $destino]) {
            // Só desfaz o que esta migration gravou (capa trocada no admin fica).
            $n = DB::table('posts')->where('slug', $slug)->where('cover_path', $destino)->update(['cover_path' => null]);
            if ($n) {
                Storage::disk('public')->delete($destino);
            }
        }
    }
};
