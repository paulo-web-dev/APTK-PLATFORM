<?php

namespace Tests\Feature\Microvix;

use App\Models\MicrovixProduct;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMicrovixProductsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Product $negroni;

    protected MicrovixProduct $mp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Admin',
            'email'    => 'admin@aptk.test',
            'password' => bcrypt('secret123'),
            'role'     => 'admin',
        ]);

        $this->negroni = Product::create([
            'name'  => 'Negroni Clássico',
            'slug'  => 'negroni-classico',
            'sku'   => 'NEG-750',
            'sizes' => ['375 ml', '750 ml'],
        ]);

        $this->mp = MicrovixProduct::create([
            'codigoproduto' => 900001,
            'nome_produto'  => 'APTK NEGRONI CLÁSSICO 375ML',
            'referencia'    => 'NEGRONI',
        ]);
    }

    public function test_acesso_restrito_a_admin(): void
    {
        $this->get('/admin/microvix/produtos')->assertRedirect('/login');

        $customer = User::create([
            'name'     => 'Cliente',
            'email'    => 'cliente@aptk.test',
            'password' => bcrypt('secret123'),
            'role'     => 'customer',
        ]);

        $this->actingAs($customer)->get('/admin/microvix/produtos')->assertForbidden();
    }

    public function test_index_destaca_quantos_faltam_sem_vinculo(): void
    {
        MicrovixProduct::create(['codigoproduto' => 900002, 'product_id' => $this->negroni->id, 'size' => '750 ml']);

        $this->actingAs($this->admin)
            ->get('/admin/microvix/produtos')
            ->assertOk()
            ->assertSee('Faltam 1 sem vínculo')
            ->assertSee('sugestão por referência');
    }

    public function test_filtro_sem_vinculo_lista_so_os_desvinculados(): void
    {
        MicrovixProduct::create([
            'codigoproduto' => 900002,
            'nome_produto'  => 'APTK NEGRONI CLÁSSICO 750ML',
            'product_id'    => $this->negroni->id,
            'size'          => '750 ml',
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/microvix/produtos?filtro=sem-vinculo')
            ->assertOk()
            ->assertSee('900001')
            ->assertDontSee('900002');
    }

    public function test_vincular_grava_produto_e_volume(): void
    {
        $this->actingAs($this->admin)
            ->patch("/admin/microvix/produtos/{$this->mp->id}/vinculo", [
                'vinculo' => "{$this->negroni->id}|375 ml",
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->mp->refresh();
        $this->assertSame($this->negroni->id, $this->mp->product_id);
        $this->assertSame('375 ml', $this->mp->size);
    }

    public function test_vinculo_com_volume_inexistente_e_rejeitado(): void
    {
        $this->actingAs($this->admin)
            ->patch("/admin/microvix/produtos/{$this->mp->id}/vinculo", [
                'vinculo' => "{$this->negroni->id}|1000 ml",
            ])
            ->assertSessionHasErrors('vinculo');

        $this->assertNull($this->mp->refresh()->product_id);
    }

    public function test_produto_e_volume_ja_ocupados_por_outro_codigo_sao_rejeitados(): void
    {
        MicrovixProduct::create([
            'codigoproduto' => 900002,
            'product_id'    => $this->negroni->id,
            'size'          => '375 ml',
        ]);

        $this->actingAs($this->admin)
            ->patch("/admin/microvix/produtos/{$this->mp->id}/vinculo", [
                'vinculo' => "{$this->negroni->id}|375 ml",
            ])
            ->assertSessionHasErrors('vinculo');

        $this->assertNull($this->mp->refresh()->product_id);
    }

    public function test_desvincular_limpa_produto_e_volume(): void
    {
        $this->mp->update(['product_id' => $this->negroni->id, 'size' => '375 ml']);

        $this->actingAs($this->admin)
            ->delete("/admin/microvix/produtos/{$this->mp->id}/vinculo")
            ->assertRedirect();

        $this->mp->refresh();
        $this->assertNull($this->mp->product_id);
        $this->assertNull($this->mp->size);
    }
}
