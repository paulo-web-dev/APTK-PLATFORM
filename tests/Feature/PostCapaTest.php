<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Capa de post: a enviada no admin ou, sem ela, uma foto padrão fixa por
 * post — nunca o fundo listrado. Migration dá capa aos 3 posts de exemplo.
 */
class PostCapaTest extends TestCase
{
    use RefreshDatabase;

    protected function novoPost(array $over = []): Post
    {
        return Post::create($over + [
            'title' => 'Post', 'slug' => 'post-'.uniqid(), 'excerpt' => 'Resumo', 'body' => 'Texto',
            'published_at' => now()->subDay(), 'active' => true,
        ]);
    }

    public function test_post_sem_capa_usa_foto_padrao_fixa_por_post_em_todas_as_telas(): void
    {
        $p = $this->novoPost();
        $padrao = asset(Post::CAPAS_PADRAO[$p->id % count(Post::CAPAS_PADRAO)]);

        $this->assertSame($padrao, $p->capaUrl());
        $this->assertSame($padrao, Post::find($p->id)->capaUrl()); // não muda entre visitas

        foreach (['/', route('novidades.index'), route('novidades.show', $p->slug)] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('src="'.$padrao.'"', $html, $url);
            $this->assertStringNotContainsString('repeating-linear-gradient(45deg, var(--color-bg-card)', $html, $url);
        }
        foreach (Post::CAPAS_PADRAO as $foto) {
            $this->assertFileExists(public_path($foto));
        }
    }

    public function test_capa_enviada_vence_a_padrao(): void
    {
        $p = $this->novoPost(['cover_path' => 'posts/minha.jpg']);

        $this->assertSame(Storage::url('posts/minha.jpg'), $p->capaUrl());
        $this->get(route('novidades.show', $p->slug))->assertSee(Storage::url('posts/minha.jpg'));
    }

    public function test_migration_da_capa_aos_3_posts_de_exemplo_sem_mexer_em_capa_existente(): void
    {
        Storage::fake('public');
        $migration = require database_path('migrations/2026_10_25_110000_capas_dos_posts_exemplo.php');
        $negroni = $this->novoPost(['slug' => 'negroni-em-casa-3-detalhes']);
        $gelo = $this->novoPost(['slug' => 'gelo-cristal-por-que-importa']);
        $harmonizar = $this->novoPost(['slug' => 'como-harmonizar-drinks-e-comida', 'cover_path' => 'posts/da-gestao.jpg']);

        $migration->up();

        $this->assertSame('posts/capa-negroni-em-casa.jpg', $negroni->refresh()->cover_path);
        $this->assertSame('posts/capa-gelo-cristal.jpg', $gelo->refresh()->cover_path);
        $this->assertSame('posts/da-gestao.jpg', $harmonizar->refresh()->cover_path);
        Storage::disk('public')->assertExists(['posts/capa-negroni-em-casa.jpg', 'posts/capa-gelo-cristal.jpg']);
        Storage::disk('public')->assertMissing('posts/capa-harmonizar-drinks.jpg');
        $this->assertSame(
            file_get_contents(public_path('img/aptk/post-negroni-em-casa.jpg')),
            Storage::disk('public')->get('posts/capa-negroni-em-casa.jpg'),
        );

        $migration->down();
        $this->assertNull($negroni->refresh()->cover_path);
        $this->assertSame('posts/da-gestao.jpg', $harmonizar->refresh()->cover_path);
        Storage::disk('public')->assertMissing('posts/capa-negroni-em-casa.jpg');
    }

    public function test_admin_tem_upload_de_capa_com_previa_e_salva_no_disco_dos_posts(): void
    {
        Storage::fake('public');
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@aptk.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $p = $this->novoPost();

        $this->actingAs($admin)->get(route('admin.posts.edit', $p))->assertOk()
            ->assertSee('name="cover"', false)
            ->assertSee('id="coverPreview"', false)
            ->assertSee('src="'.$p->capaUrl().'"', false);

        $this->put(route('admin.posts.update', $p), [
            'title' => $p->title, 'excerpt' => $p->excerpt, 'body' => $p->body,
            'published_at' => now()->subDay()->format('Y-m-d\TH:i'), 'active' => 1,
            'cover' => UploadedFile::fake()->image('capa.jpg', 1600, 1000),
        ])->assertRedirect();

        $this->assertStringStartsWith('posts/', $p->refresh()->cover_path);
        Storage::disk('public')->assertExists($p->cover_path);
    }
}
