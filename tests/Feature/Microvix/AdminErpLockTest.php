<?php

namespace Tests\Feature\Microvix;

use App\Exceptions\OutOfStockException;
use App\Models\MicrovixProduct;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dados da Linx somente leitura, por VOLUME: com fonte_da_verdade=erp, preço
 * e estoque de volume vinculado vêm da Linx e não se editam no site (nem com
 * POST forjado); volume sem vínculo segue editável; com fonte "site" tudo é
 * editável. E na venda: volume da Linx sem saldo é indisponível, nunca
 * ilimitado; volume do site sem estoque é ilimitado ("sem controle").
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
            'name' => 'Admin', 'email' => 'admin@aptk.test', 'password' => bcrypt('secret123'), 'role' => 'admin',
        ]);

        $this->product = Product::create([
            'name'        => 'Boulevardier',
            'slug'        => 'boulevardier',
            'price'       => 210.00,
            'stock_qty'   => 29,
            'sizes'       => ['375 ml', '750 ml'],
            'size_prices' => ['375 ml' => 165.00, '750 ml' => 210.00],
            'size_stock'  => ['375 ml' => 4, '750 ml' => 7],
            'active'      => true,
        ]);
        // Ativo exige foto no padrão (Product::podeAtivar).
        ProductImage::create(['product_id' => $this->product->id, 'path' => 'products/foto-branco-bou750-frente.jpg', 'sort_order' => 0, 'is_primary' => true]);
    }

    /** 375 ml vinculado (código 184); 750 ml é do site. */
    protected function vincula375(?float $saldo = 4): void
    {
        MicrovixProduct::create([
            'codigoproduto' => 184, 'product_id' => $this->product->id, 'size' => '375 ml',
            'saldo' => $saldo, 'preco' => 165.00, 'synced_at' => now(),
        ]);
    }

    protected function salvar(array $payload)
    {
        return $this->actingAs($this->admin)->put("/admin/produtos/{$this->product->id}", $payload + [
            'name' => 'Boulevardier Renomeado', 'active' => '1',
        ]);
    }

    /* -----------------------------------------------------------------
     | Edição de produto
     ------------------------------------------------------------------ */

    public function test_volume_vinculado_com_erp_nao_muda_preco_nem_estoque_mesmo_com_post_forjado(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);
        $this->vincula375();

        // Form forjado: tenta trocar preço e estoque do 375 (da Linx) e o preço base.
        $this->salvar([
            'price'       => 999.99,
            'sizes'       => '750 ml',
            'size_prices' => '222,00',
            'size_stock'  => ['375 ml' => 999, '750 ml' => 12],
            'size_stock_form' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $p = $this->product->fresh();
        $this->assertSame('Boulevardier Renomeado', $p->name);                 // editorial passa
        $this->assertSame(['375 ml', '750 ml'], $p->sizes);                     // volume da Linx não some
        $this->assertEquals(165.00, $p->size_prices['375 ml']);                 // preço da Linx intocado
        $this->assertSame(4, (int) $p->size_stock['375 ml']);                   // estoque da Linx intocado
        $this->assertEquals(222.00, $p->size_prices['750 ml']);                 // volume do site editável
        $this->assertSame(12, (int) $p->size_stock['750 ml']);
        $this->assertEquals(210.00, (float) $p->price);                         // preço base travado
    }

    public function test_volume_da_linx_digitado_nos_volumes_do_site_e_recusado(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);
        $this->vincula375();

        $this->salvar(['sizes' => '375 ml, 750 ml', 'size_prices' => '1,00; 2,00'])
            ->assertSessionHasErrors(['sizes' => '375 ml vem da Linx: fica fora dos volumes do site.']);
        $this->assertEquals(165.00, $this->product->fresh()->size_prices['375 ml']);
    }

    public function test_volume_sem_vinculo_e_editavel_e_vazio_vira_sem_controle(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);
        $this->vincula375();

        $this->salvar([
            'sizes' => '750 ml', 'size_prices' => '199,90',
            'size_stock' => ['750 ml' => ''], 'size_stock_form' => '1',
        ])->assertSessionHasNoErrors();

        $p = $this->product->fresh();
        $this->assertEquals(199.90, $p->size_prices['750 ml']);
        $this->assertArrayNotHasKey('750 ml', $p->size_stock);
        $this->assertNull($p->stockForSize('750 ml')); // sem controle
    }

    public function test_com_fonte_site_tudo_e_editavel_mesmo_com_vinculo(): void
    {
        config(['microvix.fonte_da_verdade' => 'site']);
        $this->vincula375();

        $this->salvar([
            'price' => 230.00, 'sizes' => '375 ml, 750 ml', 'size_prices' => '170,00; 230,00',
            'size_stock' => ['375 ml' => 1, '750 ml' => 2], 'size_stock_form' => '1',
        ])->assertSessionHasNoErrors();

        $p = $this->product->fresh();
        $this->assertEquals(170.00, $p->size_prices['375 ml']);
        $this->assertSame(1, (int) $p->size_stock['375 ml']);
        $this->assertEquals(230.00, (float) $p->price);
    }

    public function test_form_mostra_volume_da_linx_so_leitura_com_codigo_e_sincronizacao(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);
        $this->vincula375();

        $this->actingAs($this->admin)->get("/admin/produtos/{$this->product->id}/editar")
            ->assertOk()
            ->assertSee('Volumes da Linx')
            ->assertSee('vem da Linx · código 184', false)
            ->assertSee('sincronizado em')
            ->assertSee('Volumes do site')
            ->assertSee('name="size_stock[750 ml]"', false)
            ->assertDontSee('name="size_stock[375 ml]"', false)
            ->assertDontSee('name="stock_qty"', false);
    }

    /* -----------------------------------------------------------------
     | Lista de produtos e /admin/estoque
     ------------------------------------------------------------------ */

    public function test_lista_de_produtos_mostra_estoque_do_size_stock_e_selo_linx(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);
        $this->vincula375();
        $this->product->update(['size_stock' => ['375 ml' => 0, '750 ml' => 0]]); // caso do Boulevardier (stock_qty 29)

        $this->actingAs($this->admin)->get('/admin/produtos')
            ->assertOk()
            ->assertSee('375: 0 · 750: 0')
            ->assertSee('>Linx</span>', false)
            ->assertDontSee('>29<', false);

        $this->product->update(['size_stock' => ['375 ml' => 3]]);
        $this->actingAs($this->admin)->get('/admin/produtos')->assertSee('375: 3 · 750: sem controle');
    }

    public function test_tela_de_estoque_edita_volume_do_site_e_recusa_volume_da_linx(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);
        $this->vincula375();

        $this->actingAs($this->admin)->get('/admin/estoque')
            ->assertOk()
            ->assertSee('Vem da Linx · código 184', false);

        $this->actingAs($this->admin)
            ->patch("/admin/estoque/{$this->product->id}", ['size' => '375 ml', 'size_stock' => 50])
            ->assertSessionHasErrors('size_stock');
        $this->assertSame(4, (int) $this->product->fresh()->size_stock['375 ml']);

        $this->actingAs($this->admin)
            ->patch("/admin/estoque/{$this->product->id}", ['size' => '750 ml', 'size_stock' => 15])
            ->assertSessionHasNoErrors();
        $this->assertSame(15, (int) $this->product->fresh()->size_stock['750 ml']);

        // Vazio = sem controle.
        $this->actingAs($this->admin)
            ->patch("/admin/estoque/{$this->product->id}", ['size' => '750 ml', 'size_stock' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($this->product->fresh()->stockForSize('750 ml'));
        $this->actingAs($this->admin)->get('/admin/estoque?filtro=sem_controle')->assertSee('Sem controle');
    }

    /* -----------------------------------------------------------------
     | Venda: volume da Linx sem saldo é indisponível; do site, ilimitado
     ------------------------------------------------------------------ */

    public function test_volume_da_linx_sem_saldo_fica_indisponivel_e_do_site_sem_estoque_e_ilimitado(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);
        $this->vincula375(saldo: null);                        // nunca veio saldo da Linx
        $this->product->update(['size_stock' => null]);        // nem conciliação

        $p = $this->product->fresh();
        $this->assertSame(0, $p->stockForSize('375 ml'));       // indisponível, não ilimitado
        $this->assertTrue($p->isSizeOutOfStock('375 ml'));
        $this->assertNull($p->stockForSize('750 ml'));          // site, sem controle: ilimitado
        $this->assertFalse($p->isSoldOut());

        // Com fonte "site" o mesmo volume volta ao comportamento de sempre.
        config(['microvix.fonte_da_verdade' => 'site']);
        $this->assertNull($this->product->fresh()->stockForSize('375 ml'));
    }

    public function test_checkout_revalida_com_lock_e_recusa_volume_da_linx_sem_saldo(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);
        $user = User::create(['name' => 'Cliente', 'email' => 'c@aptk.test', 'password' => bcrypt('secret123'), 'role' => 'customer']);
        $this->actingAs($user);

        // Item entra no carrinho enquanto o 375 ainda era do site...
        $this->product->update(['size_stock' => null]);
        app(CartService::class)->add($this->product->id, 1, '375 ml');
        // ...e é vinculado antes do fechamento, sem saldo da Linx.
        $this->vincula375(saldo: null);

        try {
            app(OrderService::class)->placeFromCart($user, [
                'name' => 'Cliente', 'phone' => '11 99999-0000', 'cpf' => '111.444.777-35', 'zipcode' => '01310-100',
                'street' => 'Av Paulista', 'number' => '1', 'neighborhood' => 'Bela Vista', 'city' => 'São Paulo',
                'state' => 'SP', 'payment_method' => 'pix', 'shipping_method' => 'sedex',
            ]);
            $this->fail('Volume da Linx sem saldo deveria recusar o fechamento.');
        } catch (OutOfStockException $e) {
            $this->assertStringContainsString('esgot', mb_strtolower($e->getMessage()));
        }

        // Volume do site sem estoque (sem controle) fecha normalmente.
        app(CartService::class)->clear();
        app(CartService::class)->add($this->product->id, 3, '750 ml');
        $order = app(OrderService::class)->placeFromCart($user, [
            'name' => 'Cliente', 'phone' => '11 99999-0000', 'cpf' => '111.444.777-35', 'zipcode' => '01310-100',
            'street' => 'Av Paulista', 'number' => '1', 'neighborhood' => 'Bela Vista', 'city' => 'São Paulo',
            'state' => 'SP', 'payment_method' => 'pix', 'shipping_method' => 'sedex',
        ]);
        $this->assertSame(3, (int) $order->items()->sum('qty'));
    }

    /* -----------------------------------------------------------------
     | microvix:sincronizar
     ------------------------------------------------------------------ */

    public function test_sincronizar_nao_concilia_quando_o_sync_falha(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);
        $this->vincula375(saldo: 50);
        $antes = $this->product->fresh()->size_stock;

        // Sync falha (credenciais vazias → microvix:sync devolve FAILURE).
        $this->mock(\App\Services\Microvix\MicrovixReconciler::class, fn ($m) => $m->shouldNotReceive('executar')->shouldNotReceive('plano'));
        config(['microvix.chave' => null, 'microvix.id_portal' => null]);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('erro', 500)]);

        $this->artisan('microvix:sincronizar')->assertFailed();

        $this->assertSame($antes, $this->product->fresh()->size_stock);
        $ultima = \App\Console\Commands\Microvix\MicrovixSincronizar::ultima();
        $this->assertFalse($ultima['ok']);
        $this->assertStringContainsString('conciliação não rodou', $ultima['mensagem']);
    }

    public function test_sincronizar_concilia_quando_o_sync_da_certo_e_painel_mostra(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp', 'microvix.sync_agendado' => true]);
        $this->vincula375(saldo: 50);

        // Sync ok: os três métodos respondem cabeçalho sem registros ("nada mudou").
        config([
            'microvix.environment' => 'aceitacao', 'microvix.id_portal' => '18443', 'microvix.cnpj_emp' => '36491550000106',
            'microvix.user' => 'u', 'microvix.password' => 'p', 'microvix.chave' => 'k',
        ]);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(file_get_contents(base_path('tests/Fixtures/microvix/vazio.xml')))]);

        $this->artisan('microvix:sincronizar')->assertSuccessful();

        $this->assertSame(50, (int) $this->product->fresh()->size_stock['375 ml']);
        $this->assertTrue(\App\Console\Commands\Microvix\MicrovixSincronizar::ultima()['ok']);

        $this->actingAs($this->admin)->get('/admin/microvix')
            ->assertOk()
            ->assertSee('Sincronização Linx → site', false)
            ->assertSee('deu certo')
            ->assertSee('(a cada 15 min)');
    }

    public function test_agendamento_so_com_a_chave_ligada(): void
    {
        $evento = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'microvix:sincronizar'));

        $this->assertNotNull($evento);
        $this->assertSame('*/15 * * * *', $evento->expression);
        $this->assertTrue($evento->withoutOverlapping);
        $this->assertSame(30, $evento->expiresAt);
        $this->assertTrue($evento->runInBackground);

        config(['microvix.sync_agendado' => false]);
        $this->assertFalse($evento->filtersPass(app()));
        config(['microvix.sync_agendado' => true]);
        $this->assertTrue($evento->filtersPass(app()));
    }
}
