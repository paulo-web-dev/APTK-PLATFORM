<?php

namespace Tests\Feature\Microvix;

use App\Models\MicrovixProduct;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trava de preço/estoque no admin para produto gerenciado pelo ERP
 * (fonte_da_verdade=erp + vínculo confirmado). Sem os DOIS juntos,
 * tudo continua editável como sempre.
 */
class AdminErpLockTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Admin',
            'email'    => 'admin@aptk.test',
            'password' => bcrypt('secret123'),
            'role'     => 'admin',
        ]);

        $this->product = Product::create([
            'name'        => 'Negroni Clássico',
            'slug'        => 'negroni-classico',
            'price'       => 149.90,
            'stock_qty'   => 30,
            'sizes'       => ['375 ml'],
            'size_prices' => ['375 ml' => 123.44],
            'active'      => true,
        ]);
        // Ativo exige foto no padrão (Product::podeAtivar).
        ProductImage::create(['product_id' => $this->product->id, 'path' => 'products/negroni-classico-375ml-frente.jpg', 'sort_order' => 0, 'is_primary' => true]);
    }

    protected function vincula(): void
    {
        MicrovixProduct::create([
            'codigoproduto' => 900001,
            'product_id'    => $this->product->id,
            'size'          => '375 ml',
        ]);
    }

    /** Payload de edição tentando mexer em tudo, inclusive preço/estoque. */
    protected function payload(): array
    {
        return [
            'name'        => 'Negroni Renomeado',
            'price'       => 999.99,
            'stock_qty'   => 999,
            'sizes'       => '375 ml',
            'size_prices' => '888,88',
            'active'      => '1',
        ];
    }

    public function test_com_fonte_erp_e_vinculo_preco_e_estoque_nao_mudam_mas_editorial_sim(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);
        $this->vincula();

        $this->actingAs($this->admin)
            ->put("/admin/produtos/{$this->product->id}", $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $p = $this->product->refresh();
        $this->assertSame('Negroni Renomeado', $p->name);          // editorial passa
        $this->assertSame(149.90, (float) $p->price);              // ERP manda
        $this->assertSame(30, $p->stock_qty);
        $this->assertSame(123.44, (float) $p->size_prices['375 ml']);
    }

    public function test_com_fonte_site_tudo_segue_editavel_mesmo_com_vinculo(): void
    {
        config(['microvix.fonte_da_verdade' => 'site']);
        $this->vincula();

        $this->actingAs($this->admin)
            ->put("/admin/produtos/{$this->product->id}", $this->payload())
            ->assertSessionHasNoErrors();

        $p = $this->product->refresh();
        $this->assertSame(999.99, (float) $p->price);
        $this->assertSame(999, $p->stock_qty);
    }

    public function test_com_fonte_erp_mas_sem_vinculo_segue_editavel(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);

        $this->actingAs($this->admin)
            ->put("/admin/produtos/{$this->product->id}", $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(999.99, (float) $this->product->refresh()->price);
    }

    public function test_form_mostra_aviso_gerenciado_pelo_erp(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);
        $this->vincula();

        $this->actingAs($this->admin)
            ->get("/admin/produtos/{$this->product->id}/editar")
            ->assertOk()
            ->assertSee('gerenciados pelo ERP')
            ->assertSee('Ver vínculos');
    }

    public function test_tela_de_estoque_recusa_produto_gerenciado(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);
        $this->vincula();

        $this->actingAs($this->admin)
            ->patch("/admin/estoque/{$this->product->id}", ['stock_qty' => 999])
            ->assertSessionHasErrors('stock_qty');

        $this->assertSame(30, $this->product->refresh()->stock_qty);

        // Com fonte=site o mesmo produto volta a ser editável.
        config(['microvix.fonte_da_verdade' => 'site']);

        $this->actingAs($this->admin)
            ->patch("/admin/estoque/{$this->product->id}", ['stock_qty' => 25])
            ->assertSessionHasNoErrors();

        $this->assertSame(25, $this->product->refresh()->stock_qty);
    }
}
