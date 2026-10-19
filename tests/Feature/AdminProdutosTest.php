<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin de produtos com o catálogo importado (leva 08): listagem, editar
 * sem perder nada, galeria que adiciona em vez de apagar, ativar só com
 * preço e frete nos pedidos.
 */
class AdminProdutosTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::create([
            'name'     => 'Admin',
            'email'    => 'admin@aptk.test',
            'password' => bcrypt('secret123'),
            'role'     => 'admin',
        ]);
    }

    /** Como o catalogo:importar-planilha deixa um produto. */
    protected function importado(array $attrs = []): Product
    {
        $categoria = ProductCategory::firstOrCreate(['slug' => 'classicos'], ['name' => 'Clássicos', 'active' => true]);

        $p = Product::create($attrs + [
            'name'        => 'Negroni Clássico',
            'slug'        => 'negroni-classico',
            'category_id' => $categoria->id,
            'sku'         => 'NC750',
            'price'       => 210.00,
            'sizes'       => ['100 ml', '375 ml', '750 ml'],
            'size_prices' => ['100 ml' => 80, '375 ml' => 97.44, '750 ml' => 210],
            'stock_qty'   => 30,
            'active'      => true,
        ]);

        foreach (['750ml-frente', '750ml-verso'] as $i => $nome) {
            $path = "products/{$p->slug}-{$nome}.jpg";
            Storage::disk('public')->put($path, 'pacote');
            ProductImage::create(['product_id' => $p->id, 'path' => $path, 'alt' => $p->name, 'sort_order' => $i, 'is_primary' => $i === 0]);
        }

        return $p;
    }

    /** Os campos do form de editar exatamente como a tela os mostra. */
    protected function camposDoForm(Product $p): array
    {
        $html = $this->actingAs($this->admin)->get("/admin/produtos/{$p->id}/editar")->assertOk()->getContent();

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xp = new DOMXPath($dom);
        $form = $xp->query('//form[@enctype="multipart/form-data"]')->item(0);
        $campos = [];

        foreach ($xp->query('.//input[@name]', $form) as $in) {
            $tipo = $in->getAttribute('type');
            if ($tipo === 'file' || ($tipo === 'checkbox' && ! $in->hasAttribute('checked'))) {
                continue;
            }
            $campos[$in->getAttribute('name')] = $in->getAttribute('value');
        }
        foreach ($xp->query('.//textarea[@name]', $form) as $ta) {
            $campos[$ta->getAttribute('name')] = $ta->textContent;
        }
        foreach ($xp->query('.//select[@name]', $form) as $sel) {
            $op = $xp->query('.//option[@selected]', $sel)->item(0);
            $campos[$sel->getAttribute('name')] = $op ? $op->getAttribute('value') : '';
        }

        return $campos;
    }

    // ---- listagem ------------------------------------------------------

    public function test_listagem_mostra_sku_menor_preco_categoria_placeholder_e_filtra(): void
    {
        $this->importado();
        $this->importado(['name' => 'Canarinha', 'slug' => 'canarinha', 'sku' => 'CNA375', 'sizes' => null, 'size_prices' => null, 'price' => 0, 'active' => false]);

        $this->actingAs($this->admin)->get('/admin/produtos')
            ->assertOk()
            ->assertSee('NC750')
            ->assertSee('R$ 80,00')       // menor preço, não o do 750
            ->assertSee('Clássicos')
            ->assertSee('sem preço')
            ->assertSee('Todos (2)')
            ->assertSee('Ativos (1)')
            ->assertSee('Inativos (1)');

        $this->actingAs($this->admin)->get('/admin/produtos?status=ativos')
            ->assertSee('Negroni Clássico')->assertDontSee('Canarinha');

        $this->actingAs($this->admin)->get('/admin/produtos?status=inativos')
            ->assertSee('Canarinha')->assertDontSee('NC750');
    }

    public function test_listagem_mostra_placeholder_para_produto_sem_foto(): void
    {
        $this->importado(['name' => 'Canarinha', 'slug' => 'canarinha', 'sku' => 'CNA375']);
        ProductImage::query()->delete();

        $this->actingAs($this->admin)->get('/admin/produtos')
            ->assertOk()
            ->assertSee('aria-label="Produto ainda sem foto"', false);
    }

    // ---- editar --------------------------------------------------------

    public function test_salvar_produto_importado_sem_mudar_nada_deixa_tudo_identico(): void
    {
        $p = $this->importado();
        $antes = $p->fresh()->getAttributes();
        $imagensAntes = ProductImage::orderBy('id')->get(['id', 'path', 'sort_order', 'is_primary'])->toArray();

        $campos = $this->camposDoForm($p);
        $this->assertSame('100 ml, 375 ml, 750 ml', $campos['sizes']);
        $this->assertSame('80,00; 97,44; 210,00', $campos['size_prices']);

        $this->actingAs($this->admin)
            ->put("/admin/produtos/{$p->id}", $campos)
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/produtos');

        $depois = $p->fresh()->getAttributes();
        unset($antes['updated_at'], $depois['updated_at']);
        $this->assertSame($antes, $depois);
        $this->assertSame($imagensAntes, ProductImage::orderBy('id')->get(['id', 'path', 'sort_order', 'is_primary'])->toArray());
        Storage::disk('public')->assertExists('products/negroni-classico-750ml-frente.jpg');
    }

    public function test_preco_por_volume_descasado_e_erro_e_nao_zera_os_precos(): void
    {
        $p = $this->importado();
        $campos = ['size_prices' => '80,00; 97,44'] + $this->camposDoForm($p);

        $this->actingAs($this->admin)
            ->put("/admin/produtos/{$p->id}", $campos)
            ->assertSessionHasErrors('size_prices');

        $this->assertEquals(['100 ml' => 80, '375 ml' => 97.44, '750 ml' => 210], $p->fresh()->size_prices);
    }

    public function test_editar_preco_por_volume_grava_o_novo(): void
    {
        $p = $this->importado();
        $campos = ['size_prices' => '85,00; 99,90; 215,00'] + $this->camposDoForm($p);

        $this->actingAs($this->admin)
            ->put("/admin/produtos/{$p->id}", $campos)
            ->assertSessionHasNoErrors();

        $this->assertEquals(['100 ml' => 85, '375 ml' => 99.90, '750 ml' => 215], $p->fresh()->size_prices);
    }

    // ---- ativar --------------------------------------------------------

    public function test_produto_sem_preco_nao_pode_ser_ativado(): void
    {
        $p = $this->importado(['sizes' => null, 'size_prices' => null, 'price' => 0, 'active' => false]);
        $campos = ['active' => '1'] + $this->camposDoForm($p);

        $this->actingAs($this->admin)
            ->put("/admin/produtos/{$p->id}", $campos)
            ->assertSessionHasErrors(['active' => 'Produto sem preço não pode ser ativado. Preencha o preço (ou os preços por volume) e salve de novo.']);

        $this->assertFalse($p->fresh()->active);
    }

    public function test_ativar_e_desativar_pelo_admin(): void
    {
        $p = $this->importado(['active' => false]);

        $this->actingAs($this->admin)
            ->put("/admin/produtos/{$p->id}", ['active' => '1'] + $this->camposDoForm($p))
            ->assertSessionHasNoErrors();
        $this->assertTrue($p->fresh()->active);

        $campos = $this->camposDoForm($p);
        unset($campos['active']);
        $this->actingAs($this->admin)->put("/admin/produtos/{$p->id}", $campos)->assertSessionHasNoErrors();
        $this->assertFalse($p->fresh()->active);
    }

    public function test_produto_sem_foto_no_padrao_nao_pode_ser_ativado(): void
    {
        $p = $this->importado(['active' => false]);
        $p->images()->delete();
        // Foto antiga do site não conta.
        ProductImage::create(['product_id' => $p->id, 'path' => 'products/negroni-classico.jpg', 'sort_order' => 0, 'is_primary' => true]);

        $this->actingAs($this->admin)
            ->put("/admin/produtos/{$p->id}", ['active' => '1'] + $this->camposDoForm($p))
            ->assertSessionHasErrors(['active' => 'Envie uma foto do produto antes de ativar.']);

        $this->assertFalse($p->fresh()->active);
    }

    public function test_foto_enviada_no_mesmo_salvar_libera_a_ativacao(): void
    {
        $p = $this->importado(['active' => false]);
        $p->images()->delete();

        $campos = ['active' => '1', 'image' => UploadedFile::fake()->image('nova.jpg', 800, 1200)] + $this->camposDoForm($p);
        $this->actingAs($this->admin)
            ->put("/admin/produtos/{$p->id}", $campos)
            ->assertSessionHasNoErrors();

        $p = $p->fresh('images');
        $this->assertTrue($p->active);
        $this->assertTrue($p->images->first()->isUploadDoAdmin());
        $this->assertTrue($p->podeAtivar());
    }

    public function test_criar_produto_ativo_sem_foto_e_recusado_e_com_foto_passa(): void
    {
        $campos = ['name' => 'Novo', 'price' => '150', 'stock_qty' => '10', 'active' => '1'];

        $this->actingAs($this->admin)
            ->post('/admin/produtos', $campos)
            ->assertSessionHasErrors(['active' => 'Envie uma foto do produto antes de ativar.']);
        $this->assertSame(0, Product::count());

        $this->actingAs($this->admin)
            ->post('/admin/produtos', $campos + ['image' => UploadedFile::fake()->image('nova.jpg', 800, 1200)])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Product::sole()->active);
    }

    public function test_criar_produto_ativo_sem_preco_e_recusado(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/produtos', ['name' => 'Novo', 'price' => '0', 'stock_qty' => '10', 'active' => '1'])
            ->assertSessionHasErrors('active');

        $this->assertSame(0, Product::count());
    }

    // ---- imagens -------------------------------------------------------

    public function test_upload_adiciona_como_principal_sem_apagar_o_pacote(): void
    {
        $p = $this->importado();
        $campos = $this->camposDoForm($p);
        $campos['image'] = UploadedFile::fake()->image('nova.jpg', 800, 1200);

        $this->actingAs($this->admin)
            ->put("/admin/produtos/{$p->id}", $campos)
            ->assertSessionHasNoErrors();

        $galeria = $p->fresh('images')->images;
        $this->assertCount(3, $galeria);
        $this->assertTrue($galeria[0]->isUploadDoAdmin());
        $this->assertSame([true, false, false], $galeria->pluck('is_primary')->all());
        $this->assertSame([0, 1, 2], $galeria->pluck('sort_order')->all());
        $this->assertSame('products/negroni-classico-750ml-frente.jpg', $galeria[1]->path);
        Storage::disk('public')->assertExists('products/negroni-classico-750ml-frente.jpg');
        Storage::disk('public')->assertExists($galeria[0]->path);
    }

    public function test_remover_imagem_apaga_a_linha_e_so_o_arquivo_do_upload(): void
    {
        $p = $this->importado();
        $campos = $this->camposDoForm($p);
        $campos['image'] = UploadedFile::fake()->image('nova.jpg');
        $this->actingAs($this->admin)->put("/admin/produtos/{$p->id}", $campos);

        [$upload, $frente] = $p->fresh('images')->images->all();

        // Upload do admin: some linha e arquivo; a frente do pacote vira principal.
        $this->actingAs($this->admin)
            ->delete("/admin/produtos/{$p->id}/imagens/{$upload->id}")
            ->assertRedirect("/admin/produtos/{$p->id}/editar");
        Storage::disk('public')->assertMissing($upload->path);
        $this->assertTrue($frente->fresh()->is_primary);
        $this->assertSame(0, $frente->fresh()->sort_order);

        // Foto do pacote: some a linha, o arquivo fica (não regera em produção).
        $this->actingAs($this->admin)->delete("/admin/produtos/{$p->id}/imagens/{$frente->id}");
        $this->assertNull(ProductImage::find($frente->id));
        Storage::disk('public')->assertExists($frente->path);
        $this->assertTrue($p->fresh('images')->images->first()->is_primary);
    }

    public function test_remover_imagem_de_outro_produto_da_404(): void
    {
        $a = $this->importado();
        $b = $this->importado(['name' => 'Fitzgerald', 'slug' => 'fitzgerald']);
        $imgDeB = $b->images()->first();

        $this->actingAs($this->admin)
            ->delete("/admin/produtos/{$a->id}/imagens/{$imgDeB->id}")
            ->assertNotFound();

        $this->assertNotNull($imgDeB->fresh());
    }

    // ---- pedidos -------------------------------------------------------

    public function test_pedido_no_admin_mostra_opcao_e_valor_do_frete(): void
    {
        $cliente = User::create(['name' => 'Cliente', 'email' => 'c@aptk.test', 'password' => bcrypt('x'), 'role' => 'customer']);
        $order = Order::create([
            'user_id' => $cliente->id, 'status' => 'pending', 'subtotal' => 210, 'shipping_cost' => 86.40,
            'total' => 296.40, 'payment_method' => 'pix', 'payment_status' => 'pending',
            'shipping_method' => 'sedex', 'shipping_days' => 5,
        ]);

        $this->actingAs($this->admin)->get("/admin/pedidos/{$order->id}")
            ->assertOk()
            ->assertSee('R$ 86,40')
            ->assertSee('SEDEX · até 5 dias úteis');

        $this->actingAs($this->admin)->get('/admin/pedidos')
            ->assertOk()
            ->assertSee('SEDEX')
            ->assertSee('R$ 86,40');
    }
}
