<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Dicas e Novidades — post do mini-blog (leva 06). */
class Post extends Model
{
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

    /** Publicados: ativos, com data no passado, mais recentes primeiro. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at');
    }
}
