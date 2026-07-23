<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dicas e Novidades (leva 06) — mini-blog nativo.
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('cover_path')->nullable();      // storage/app/public/posts
            $table->string('excerpt', 300)->nullable();    // resumo do card
            $table->longText('body');                      // texto do post
            $table->timestamp('published_at')->nullable(); // data exibida no card
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
