<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\FotoUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Foto do admin (produto e capa de post): aceita até 30 MB, guarda só a
 * cópia reduzida (2000 px, JPG 82, rotação do EXIF aplicada) e responde em
 * português. Form de produto: o aviso de foto só aparece sem foto no padrão.
 */
class FotoUploadAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    /** Arquivos temporários do teste (a foto grande tem 22 MB): apagados no tearDown. */
    protected array $temporarios = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@aptk.test', 'password' => bcrypt('x'), 'role' => 'admin']);
    }

    protected function tearDown(): void
    {
        array_map(fn ($f) => @unlink($f), $this->temporarios);

        parent::tearDown();
    }

    protected function temporario(string $ext): string
    {
        $base = tempnam(sys_get_temp_dir(), 'foto');
        array_push($this->temporarios, $base, $base.$ext);

        return $base.$ext;
    }

    /** JPG 6000x4500 de verdade, inchado com bytes depois do fim da imagem até ~22 MB. */
    protected function fotoGrande(): UploadedFile
    {
        $img = imagecreatetruecolor(6000, 4500);
        for ($x = 0; $x < 6000; $x += 50) {
            imagefilledrectangle($img, $x, 0, $x + 49, 4499, imagecolorallocate($img, $x % 256, 120, 255 - $x % 256));
        }
        $path = $this->temporario('.jpg');
        imagejpeg($img, $path, 95);
        imagedestroy($img);
        file_put_contents($path, random_bytes(22 * 1024 * 1024 - filesize($path)), FILE_APPEND);
        clearstatcache(true, $path);

        return new UploadedFile($path, 'IMG_0001.jpg', 'image/jpeg', null, true);
    }

    protected function produto(): Product
    {
        return Product::create(['name' => 'Penicillin', 'slug' => 'penicillin', 'sku' => 'PCL375', 'price' => 120, 'stock_qty' => 10, 'active' => false]);
    }

    public function test_foto_de_22_mb_entra_reduzida_pra_2000_px(): void
    {
        $p = $this->produto();
        $foto = $this->fotoGrande();
        $this->assertGreaterThan(20 * 1024 * 1024, $foto->getSize());

        $this->actingAs($this->admin)
            ->put("/admin/produtos/{$p->id}", ['name' => $p->name, 'price' => '120', 'stock_qty' => '10', 'image' => $foto])
            ->assertSessionHasNoErrors();

        $img = $p->images()->sole();
        $this->assertTrue($img->isUploadDoAdmin());
        $this->assertStringEndsWith('.jpg', $img->path);

        $gravada = Storage::disk('public')->path($img->path);
        [$w, $h, $tipo] = getimagesize($gravada);
        $this->assertSame([2000, 1500, IMAGETYPE_JPEG], [$w, $h, $tipo]);
        $this->assertLessThan(1024 * 1024, filesize($gravada)); // o original de 22 MB não fica
        $this->assertCount(1, Storage::disk('public')->allFiles('products'));
    }

    public function test_rotacao_do_exif_e_respeitada(): void
    {
        $p = $this->produto();
        $foto = new UploadedFile(base_path('tests/fixtures/foto-exif-orientacao-6.jpg'), 'deitada.jpg', 'image/jpeg', null, true);

        $this->actingAs($this->admin)
            ->put("/admin/produtos/{$p->id}", ['name' => $p->name, 'price' => '120', 'stock_qty' => '10', 'image' => $foto])
            ->assertSessionHasNoErrors();

        // Pixels 2400x1200 (vermelho à esquerda) com Orientation=6: em pé, vermelho em cima.
        $img = imagecreatefromjpeg(Storage::disk('public')->path($p->images()->sole()->path));
        $this->assertSame([1000, 2000], [imagesx($img), imagesy($img)]);
        $topo = imagecolorsforindex($img, imagecolorat($img, 500, 100));
        $base = imagecolorsforindex($img, imagecolorat($img, 500, 1900));
        $this->assertGreaterThan(180, $topo['red']);
        $this->assertGreaterThan(180, $base['blue']);
    }

    public function test_png_transparente_vira_jpg_com_fundo_branco_e_pequena_nao_aumenta(): void
    {
        $img = imagecreatetruecolor(400, 600);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        $path = $this->temporario('.png');
        imagepng($img, $path);

        $salvo = FotoUpload::salvar(new UploadedFile($path, 'garrafa.png', 'image/png', null, true), 'products');

        $jpg = imagecreatefromjpeg(Storage::disk('public')->path($salvo));
        $this->assertSame([400, 600], [imagesx($jpg), imagesy($jpg)]);
        $this->assertGreaterThan(245, imagecolorsforindex($jpg, imagecolorat($jpg, 10, 10))['red']);
    }

    public function test_acima_de_30_mb_e_recusada_em_portugues(): void
    {
        $p = $this->produto();

        $this->actingAs($this->admin)
            ->put("/admin/produtos/{$p->id}", ['name' => $p->name, 'price' => '120', 'stock_qty' => '10',
                'image' => UploadedFile::fake()->create('enorme.jpg', 31 * 1024, 'image/jpeg')])
            ->assertSessionHasErrors(['image' => 'A foto pode ter no máximo 30 MB.']);
    }

    public function test_heic_sem_suporte_no_servidor_pede_jpg(): void
    {
        if (FotoUpload::suportaHeic()) {
            $this->markTestSkipped('Servidor com Imagick HEIC: a foto é convertida.');
        }
        $p = $this->produto();

        $resp = $this->actingAs($this->admin)
            ->put("/admin/produtos/{$p->id}", ['name' => $p->name, 'price' => '120', 'stock_qty' => '10',
                'image' => UploadedFile::fake()->create('IMG_0002.heic', 2048, 'image/heic')]);

        $this->assertContains(
            'Foto em HEIC (formato do iPhone) não é aceita. Exporte como JPG e envie de novo.',
            session('errors')->get('image'),
        );
        $resp->assertRedirect();
        $this->assertSame(0, ProductImage::count());
    }

    public function test_capa_de_post_grande_entra_reduzida(): void
    {
        $post = Post::create(['title' => 'Post', 'slug' => 'post', 'body' => 'Texto', 'published_at' => now(), 'active' => true]);

        $this->actingAs($this->admin)->put(route('admin.posts.update', $post), [
            'title' => 'Post', 'body' => 'Texto', 'published_at' => now()->format('Y-m-d\TH:i'), 'active' => 1,
            'cover' => $this->fotoGrande(),
        ])->assertSessionHasNoErrors();

        $capa = $post->refresh()->cover_path;
        $this->assertMatchesRegularExpression('#^posts/[A-Za-z0-9]{40}\.jpg$#', $capa);
        $this->assertSame(2000, getimagesize(Storage::disk('public')->path($capa))[0]);
    }

    public function test_mensagens_de_validacao_em_portugues(): void
    {
        $this->assertSame('pt_BR', app()->getLocale());

        $this->actingAs($this->admin)->post(route('admin.posts.store'), [])
            ->assertSessionHasErrors([
                'title' => 'O campo título é obrigatório.',
                'body'  => 'O campo texto é obrigatório.',
            ]);

        $this->post(route('admin.posts.store'), ['title' => str_repeat('a', 300), 'body' => 'x', 'published_at' => 'ontem'])
            ->assertSessionHasErrors([
                'title'        => 'O campo título não pode passar de 255 caracteres.',
                'published_at' => 'O campo data de publicação precisa ser uma data válida.',
            ]);
    }

    public function test_aviso_de_foto_no_form_so_sem_foto_no_padrao(): void
    {
        $p = $this->produto();
        $p->update(['active' => true]);
        ProductImage::create(['product_id' => $p->id, 'path' => 'products/foto-branco-pcl375-frente.jpg', 'sort_order' => 0, 'is_primary' => true]);

        $this->actingAs($this->admin)->get("/admin/produtos/{$p->id}/editar")->assertOk()
            ->assertDontSee('Sem foto: envie')
            ->assertDontSee('A foto atual é antiga');

        // Só foto antiga do site: não é "sem foto", é foto fora do padrão.
        $p->images()->update(['path' => 'products/penicillin.jpg']);
        $this->get("/admin/produtos/{$p->id}/editar")
            ->assertDontSee('Sem foto: envie')
            ->assertSee('A foto atual é antiga');

        $p->images()->delete();
        $this->get("/admin/produtos/{$p->id}/editar")->assertSee('Sem foto: envie uma foto do produto antes de ativar.');
    }
}
