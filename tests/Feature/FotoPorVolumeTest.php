<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Foto por volume (leva 18): product_images.volume null = geral (garrafa
 * grande); "375 ml" = só da pequena. Volume escolhido sem foto própria cai
 * nas gerais — na página, nos cards, no carrinho e em meus pedidos.
 */
class FotoPorVolumeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    /** Produto 375 + 750 com fotos [path => volume]. */
    protected function produto(array $fotos, array $attrs = []): Product
    {
        $categoria = ProductCategory::firstOrCreate(['slug' => 'classicos'], ['name' => 'Clássicos', 'active' => true]);

        $p = Product::create($attrs + [
            'name'        => 'Cosmopolitan',
            'slug'        => 'cosmopolitan',
            'category_id' => $categoria->id,
            'sku'         => 'COS750',
            'price'       => 210,
            'sizes'       => ['375 ml', '750 ml'],
            'size_prices' => ['375 ml' => 110, '750 ml' => 210],
            'stock_qty'   => 30,
            'active'      => true,
        ]);

        $i = 0;
        foreach ($fotos as $path => $volume) {
            Storage::disk('public')->put($path, 'foto');
            ProductImage::create([
                'product_id' => $p->id, 'path' => $path, 'alt' => $p->name, 'volume' => $volume,
                'sort_order' => $i, 'is_primary' => $i === 0,
            ]);
            $i++;
        }

        return $p->load('images');
    }

    protected function cosmopolitan(): Product
    {
        return $this->produto([
            'products/foto-branco-cos750-frente.jpg' => null,
            'products/foto-branco-cos375-frente.jpg' => '375 ml',
            'products/foto-branco-cos375-verso.jpg'  => '375 ml',
        ]);
    }

    protected function admin(): User
    {
        return User::firstOrCreate(['email' => 'admin@aptk.test'], ['name' => 'Admin', 'password' => bcrypt('x'), 'role' => 'admin']);
    }

    /** Campos mínimos do salvar do admin. */
    protected function campos(Product $p, array $extra = []): array
    {
        return $extra + [
            'name' => $p->name, 'price' => 210, 'stock_qty' => 30, 'sizes' => '375 ml, 750 ml',
            'size_prices' => '110; 210', 'active' => '1',
        ];
    }

    // ---- regra -----------------------------------------------------------

    public function test_volume_com_foto_propria_mostra_a_dele_e_o_resto_cai_nas_gerais(): void
    {
        $p = $this->cosmopolitan();

        $this->assertSame(['products/foto-branco-cos375-frente.jpg', 'products/foto-branco-cos375-verso.jpg'],
            $p->fotosDoVolume('375 ml')->pluck('path')->all());
        $this->assertSame(['products/foto-branco-cos750-frente.jpg'], $p->fotosDoVolume('750 ml')->pluck('path')->all());
        $this->assertSame('products/foto-branco-cos750-frente.jpg', $p->fotoDoVolume('1 l')->path);
        $this->assertSame('products/foto-branco-cos750-frente.jpg', $p->fotoPadrao()->path);
        $this->assertSame(['375 ml'], $p->volumesComFotoPropria());
    }

    public function test_marcacao_nunca_deixa_produto_sem_foto_geral(): void
    {
        $p = $this->produto([]);

        $this->assertSame(['a' => null, 'b' => '375 ml'], $p->marcarVolumes(['a' => '750 ml', 'b' => '375 ml']));
        // Só foto da 375 de produto que vende 750 (Squadra Amêndoas): tudo geral.
        $this->assertSame(['a' => null, 'b' => null], $p->marcarVolumes(['a' => '375 ml', 'b' => '375 ml']));

        // Só vende 375 (Penicillin): 375 é o padrão, fica geral.
        $p->update(['sizes' => ['375 ml'], 'size_prices' => ['375 ml' => 110]]);
        $this->assertSame(['a' => null], $p->marcarVolumes(['a' => '375 ml']));
    }

    // ---- loja ------------------------------------------------------------

    public function test_pagina_abre_no_volume_padrao_e_leva_a_galeria_de_cada_volume(): void
    {
        $this->cosmopolitan();

        $html = $this->get('/produto/cosmopolitan')->assertOk()->getContent();

        preg_match('#<img[^>]*id="pdpMain"[^>]*>#', $html, $main);
        $this->assertStringContainsString('foto-branco-cos750-frente.jpg', $main[0]);
        $this->assertMatchesRegularExpression('#id="pdpThumbs"[^>]*hidden#', $html); // 750 tem uma foto só

        preg_match('#<script type="application/json" id="pdpFotos">(.*?)</script>#s', $html, $json);
        $fotos = json_decode($json[1], true);
        $this->assertSame(['375 ml', '750 ml'], array_keys($fotos));
        $this->assertCount(2, $fotos['375 ml']);
        $this->assertStringContainsString('cos375-frente', $fotos['375 ml'][0]['src']);
        $this->assertStringContainsString('cos750-frente', $fotos['750 ml'][0]['src']);
        $this->assertArrayHasKey('fundo', $fotos['375 ml'][0]);
    }

    public function test_volume_sem_foto_propria_usa_as_gerais_na_pagina(): void
    {
        $this->produto([
            'products/foto-branco-slo750-frente.jpg' => null,
            'products/foto-branco-slo750-verso.jpg'  => null,
        ], ['name' => "Sur L'Orange", 'slug' => 'sur-lorange']);

        $html = $this->get('/produto/sur-lorange')->assertOk()->getContent();

        preg_match('#id="pdpFotos">(.*?)</script>#s', $html, $json);
        $fotos = json_decode($json[1], true);
        $this->assertSame($fotos['750 ml'], $fotos['375 ml']);
        $this->assertCount(2, $fotos['375 ml']);
    }

    public function test_card_da_loja_usa_a_foto_do_volume_padrao(): void
    {
        // A 375 entrou primeiro na ordem, mas o card é do 750 (padrão).
        $this->produto([
            'products/foto-branco-cos375-frente.jpg' => '375 ml',
            'products/foto-branco-cos750-frente.jpg' => null,
        ]);

        $this->get('/loja')->assertOk()
            ->assertSee('foto-branco-cos750-frente.jpg', false)
            ->assertDontSee('foto-branco-cos375-frente.jpg', false);
    }

    public function test_carrinho_mostra_a_foto_do_volume_de_cada_linha(): void
    {
        $p = $this->cosmopolitan();

        $this->post(route('cart.add'), ['product_id' => $p->id, 'qty' => 1, 'size' => '375 ml']);
        $html = $this->get(route('cart.index'))->assertOk()->getContent();
        $this->assertStringContainsString('foto-branco-cos375-frente.jpg', $html);
        $this->assertStringNotContainsString('foto-branco-cos750-frente.jpg', $html);

        $this->post(route('cart.add'), ['product_id' => $p->id, 'qty' => 1, 'size' => '750 ml']);
        $this->get(route('cart.index'))
            ->assertSee('foto-branco-cos375-frente.jpg', false)
            ->assertSee('foto-branco-cos750-frente.jpg', false);
    }

    // ---- admin -----------------------------------------------------------

    public function test_admin_mostra_o_seletor_de_garrafa_com_os_volumes_do_produto(): void
    {
        $p = $this->cosmopolitan();
        $img = $p->images->firstWhere('volume', '375 ml');

        $this->actingAs($this->admin())->get(route('admin.products.edit', $p))->assertOk()
            ->assertSee('name="image_volume['.$img->id.']"', false)
            ->assertSee('Geral (grande)')
            ->assertSee('<option value="375 ml" selected>375 ml</option>', false)
            ->assertDontSee('<option value="750 ml"', false)
            ->assertSee('name="image_volume_nova"', false)
            ->assertSee('Fotos gerais aparecem para todos os volumes. Fotos da garrafa pequena aparecem só quando o cliente escolher 375 ml.');
    }

    public function test_admin_salva_a_marcacao_e_a_principal_fica_entre_as_gerais(): void
    {
        $p = $this->produto([
            'products/foto-branco-cos375-frente.jpg' => null,
            'products/foto-branco-cos750-frente.jpg' => null,
        ]);
        [$f375, $f750] = $p->images->all();

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $p), $this->campos($p, [
                'image_volume' => [$f375->id => '375 ml', $f750->id => ''],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('375 ml', $f375->fresh()->volume);
        $this->assertNull($f750->fresh()->volume);
        $this->assertTrue($f750->fresh()->is_primary);
        $this->assertFalse($f375->fresh()->is_primary);
        $this->assertSame(0, $f750->fresh()->sort_order);
    }

    public function test_admin_recusa_deixar_sem_foto_geral_e_volume_que_o_produto_nao_tem(): void
    {
        $p = $this->cosmopolitan();
        $ids = $p->images->pluck('id');

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $p), $this->campos($p, [
                'image_volume' => $ids->mapWithKeys(fn ($id) => [$id => '375 ml'])->all(),
            ]))
            ->assertSessionHasErrors(['image_volume' => 'Pelo menos uma foto precisa ser geral (garrafa grande).']);

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $p), $this->campos($p, [
                'image_volume' => [$ids[0] => '750 ml'],
            ]))
            ->assertSessionHasErrors('image_volume');

        $this->assertNull(ProductImage::find($ids[0])->volume);
    }

    public function test_upload_com_volume_nao_vira_principal_e_geral_vira(): void
    {
        $p = $this->cosmopolitan();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.products.update', $p), $this->campos($p, [
                'image'             => UploadedFile::fake()->image('pequena.jpg', 400, 600),
                'image_volume_nova' => '375 ml',
            ]))
            ->assertSessionHasNoErrors();

        $nova = ProductImage::latest('id')->first();
        $this->assertSame('375 ml', $nova->volume);
        $this->assertFalse($nova->is_primary);
        // Primeira das fotos da 375.
        $this->assertSame($nova->path, $p->fresh()->fotoDoVolume('375 ml')->path);
        $this->assertSame('products/foto-branco-cos750-frente.jpg', $p->fresh()->primaryImage->path);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $p), $this->campos($p, [
                'image' => UploadedFile::fake()->image('grande.jpg', 400, 600),
            ]))
            ->assertSessionHasNoErrors();

        $geral = ProductImage::latest('id')->first();
        $this->assertNull($geral->volume);
        $this->assertTrue($geral->is_primary);
    }

    // ---- comandos --------------------------------------------------------

    protected function manifesto(): string
    {
        $dir = storage_path('framework/testing/foto-volume');
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/manifesto.json", json_encode([
            ['sku' => 'COS375', 'produto' => 'Cosmopolitan', 'grupo' => 'cosmopolitan', 'tamanho' => '375ml',
                'frente' => '375ML/COS375_frente.jpg', 'verso' => '375ML/COS375_verso.jpg', 'tem_sku' => true, 'fundo' => 'branco'],
            ['sku' => 'COS750', 'produto' => 'Cosmopolitan', 'grupo' => 'cosmopolitan', 'tamanho' => '750ml',
                'frente' => '750ML/COS750_frente.jpg', 'verso' => null, 'tem_sku' => true, 'fundo' => 'branco'],
            ['sku' => 'LA375', 'produto' => 'Squadra Amêndoas', 'grupo' => 'squadra-amendoas', 'tamanho' => '375ml',
                'frente' => '375ML/LA375_frente.jpg', 'verso' => null, 'tem_sku' => true, 'fundo' => 'branco'],
        ]));

        return "{$dir}/manifesto.json";
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/testing/foto-volume'));

        parent::tearDown();
    }

    public function test_marcar_volume_dry_run_nao_grava_e_depois_marca_so_a_pequena(): void
    {
        $cos = $this->produto([
            'products/foto-branco-cos750-frente.jpg' => null,
            'products/foto-branco-cos375-frente.jpg' => null,
            'products/foto-branco-cos375-verso.jpg'  => null,
        ]);
        // Só foto da 375 num produto que vende 750: fica geral.
        $la = $this->produto(['products/foto-branco-la375-frente.jpg' => null],
            ['name' => 'Squadra Amêndoas', 'slug' => 'squadra-amendoas', 'sku' => 'LA750']);

        $this->artisan('produtos:marcar-volume', ['--dry-run' => true, '--manifesto' => $this->manifesto()])
            ->expectsOutputToContain('2 foto(s) ganham volume em 1 produto(s).')
            ->assertSuccessful();
        $this->assertSame(0, ProductImage::whereNotNull('volume')->count());

        $this->artisan('produtos:marcar-volume', ['--manifesto' => $this->manifesto()])->assertSuccessful();

        $this->assertSame(
            ['foto-branco-cos750-frente.jpg' => null, 'foto-branco-cos375-frente.jpg' => '375 ml', 'foto-branco-cos375-verso.jpg' => '375 ml'],
            $cos->images()->get()->mapWithKeys(fn ($i) => [basename($i->path) => $i->volume])->all(),
        );
        $this->assertNull($la->images()->first()->volume);
    }

    public function test_aplicar_fotos_grava_o_volume_e_poe_as_gerais_primeiro(): void
    {
        // Destino já existe: reaproveita (sem GD nem foto de origem).
        foreach (['cos750-frente', 'cos375-frente', 'cos375-verso'] as $nome) {
            Storage::disk('public')->put("products/foto-branco-{$nome}.jpg", 'foto');
        }
        $p = $this->produto([]);

        $this->artisan('produtos:aplicar-fotos', ['--manifesto' => $this->manifesto()])->assertSuccessful();

        $galeria = $p->images()->get();
        $this->assertSame(
            ['foto-branco-cos750-frente.jpg' => null, 'foto-branco-cos375-frente.jpg' => '375 ml', 'foto-branco-cos375-verso.jpg' => '375 ml'],
            $galeria->mapWithKeys(fn ($i) => [basename($i->path) => $i->volume])->all(),
        );
        $this->assertTrue($galeria->first()->is_primary);

        // Rodar de novo não muda nada.
        $this->artisan('produtos:aplicar-fotos', ['--manifesto' => $this->manifesto()])
            ->expectsOutputToContain('Nada a mudar.')
            ->assertSuccessful();
    }
}
