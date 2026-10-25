<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Dicas e Novidades — post do mini-blog (leva 06). */
class Post extends Model
{
    /**
     * Capa de quem não tem (cover_path vazio): foto de ambiente do
     * repertório, fixa por post (id), pra nunca mostrar o fundo listrado nem
     * mudar a cada visita. Nenhuma delas aparece nos cards da Home.
     */
    public const CAPAS_PADRAO = [
        'img/aptk/cat-pessoas.jpg',
        'img/aptk/about-loja-3.jpg',
        'img/aptk/cat-dry-martini.jpg',
        'img/aptk/loja-aptk.jpg',
    ];

    protected $fillable = [
        'title',
        'slug',
        'cover_path',
        'excerpt',
        'body',
        'published_at',
        'active',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'active'       => 'boolean',
    ];

    /** URL da capa: a enviada pelo admin ou a padrão do post. */
    public function capaUrl(): string
    {
        if ($this->cover_path) {
            return Storage::url($this->cover_path);
        }

        return asset(self::CAPAS_PADRAO[((int) $this->id) % count(self::CAPAS_PADRAO)]);
    }

    /** Publicados: ativos, com data no passado, mais recentes primeiro. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at');
    }
}
