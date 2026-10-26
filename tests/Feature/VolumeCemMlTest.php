<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Leva 15: o 100 ml sai da venda. Migration (com down exato), importador
 * respeitando catalogo.volumes_desativados, carrinho/checkout com item de
 * 100 ml que ficou na sessão, admin e vitrine.
 */
class VolumeCemMlTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_27_100000_remover_volume_100ml.php';

    protected function migration()
    {
        return require database_path(self::MIGRATION);
    }

    protected function produto(string $slug, array $precos, array $over = []): Product
    {
        return Product::create($over + [
            'name' => ucfirst($slug), 'slug' => $slug, 'price' => end($precos),
            'sizes' => array_keys($precos), 'size_prices' => $precos, 'stock_qty' => 30, 'active' => true,
        ]);
    }

    protected function colunas(): array
    {
        return DB::table('products')->orderBy('id')->get(['id', 'sizes', 'size_prices', 'size_stock', 'price', 'active'])
            ->map(fn ($r) => (array) $r)->all();
    }

    /** RefreshDatabase já rodou o up() no banco vazio: desfaz pra rodar com dados. */
    protected function rodarUp(): void
    {
        $this->migration()->down();
        $this->migration()->up();
    }

    public function test_migration_tira_o_100_ml_e_o_down_restaura_exatamente(): void
    {
        $this->migration()->down();

        $negroni = $this->produto('negroni-classico', ['100 ml' => 80, '375 ml' => 165, '750 ml' => 210],
            ['size_stock' => ['100 ml' => 4, '375 ml' => 2, '750 ml' => 1]]);
        $penicillin = $this->produto('penicillin', ['100 ml' => 80, '375 ml' => 190]);
        $manhattan = $this->produto('manhattan', ['100 ml' => 80]);
        $semCem = $this->produto('fitzgerald', ['375 ml' => 165, '750 ml' => 210]);
        $teste = $this->produto('teste', ['100 ml' => 1]);
        $user = User::create(['name' => 'C', 'email' => 'c@aptk.test', 'password' => bcrypt('x'), 'role' => 'customer']);
        $pedido = Order::create(['user_id' => $user->id, 'status' => 'paid', 'total' => 80, 'subtotal' => 80]);
        $pedido->items()->create(['product_id' => $negroni->id, 'product_name' => 'Negroni', 'size' => '100 ml', 'qty' => 1, 'unit_price' => 80, 'total_price' => 80]);

        $antes = $this->colunas();
        $this->migration()->up();

        $negroni->refresh();
        $this->assertSame(['375 ml', '750 ml'], $negroni->sizes);
        $this->assertEquals(['375 ml' => 165, '750 ml' => 210], $negroni->size_prices);
        $this->assertSame(['375 ml' => 2, '750 ml' => 1], $negroni->size_stock);
        $this->assertSame('750 ml', $negroni->defaultSize());
        $this->assertTrue($negroni->active);

        $penicillin->refresh();
        $this->assertSame(['375 ml'], $penicillin->sizes);
        $this->assertSame('375 ml', $penicillin->defaultSize());
        $this->assertEquals(190, $penicillin->price);

        // Sem volume nenhum: inativo e sem preço (não volta a ativar vendendo sem volume).
        $manhattan->refresh();
        $this->assertNull($manhattan->sizes);
        $this->assertNull($manhattan->size_prices);
        $this->assertFalse($manhattan->active);
        $this->assertFalse($manhattan->podeAtivar());

        $this->assertSame(['375 ml', '750 ml'], $semCem->fresh()->sizes);
        $this->assertSame(['100 ml'], $teste->fresh()->sizes); // produto de teste de pagamento fica
        $this->assertSame('100 ml', $pedido->items()->first()->size); // pedido não muda

        $this->migration()->down();
        $this->assertSame($antes, $this->colunas());
        $this->assertFalse(Schema::hasTable('backup_volume_100ml'));

        $this->migration()->up(); // deixa o schema como o RefreshDatabase espera
    }

    public function test_importador_ignora_volume_desativado_e_volta_tirando_da_config(): void
    {
        $this->assertSame(['100 ml'], config('catalogo.volumes_desativados'));
        $csv = storage_path('framework/testing/planilha-100.csv');
        @mkdir(dirname($csv), 0777, true);
        file_put_contents($csv, "grupo,nome,marca,volume,sku,preco\n"
            ."negroni-classico,Negroni Clássico,aptk,100ml,NC100,80.00\n"
            ."negroni-classico,Negroni Clássico,aptk,375ml,NC375,165.00\n"
            ."manhattan,Manhattan,aptk,100ml,MHAC100,80.00\n");

        $this->artisan('catalogo:importar-planilha', ['--csv' => $csv])->assertSuccessful();

        $this->assertSame(['375 ml'], Product::where('slug', 'negroni-classico')->sole()->sizes);
        $this->assertNull(Product::where('slug', 'manhattan')->first()); // só tinha 100 ml: nem é criado

        config(['catalogo.volumes_desativados' => []]);
        $this->artisan('catalogo:importar-planilha', ['--csv' => $csv])->assertSuccessful();
        $this->assertSame(['100 ml', '375 ml'], Product::where('slug', 'negroni-classico')->sole()->sizes);
        @unlink($csv);
    }

    public function test_item_de_100_ml_na_sessao_fica_indisponivel_e_o_checkout_recusa(): void
    {
        Http::fake();
        $p = $this->produto('negroni-classico', ['100 ml' => 80, '375 ml' => 165]);
        $user = User::create(['name' => 'C', 'email' => 'c@aptk.test', 'password' => bcrypt('x'), 'role' => 'customer', 'maior_idade_em' => now()]);

        $this->actingAs($user)->post('/carrinho/adicionar', ['product_id' => $p->id, 'qty' => 1, 'size' => '100 ml']);
        $this->post('/carrinho/adicionar', ['product_id' => $p->id, 'qty' => 1, 'size' => '375 ml']);
        $this->rodarUp();

        $cart = app(CartService::class);
        $this->assertEquals(165, $cart->total()); // o 100 ml não soma (nem a preço do maior volume)

        $this->get('/carrinho')->assertOk()
            ->assertSee('O volume de 100 ml não está mais disponível.')
            ->assertSee('100 ml · indisponível');

        $this->get('/checkout')->assertRedirect('/carrinho');
        $this->assertStringContainsString('O volume de 100 ml não está mais disponível.', session('stock_error'));

        $this->post('/checkout', [
            'name' => 'Cliente', 'phone' => '11 99999-0000', 'cpf' => '111.444.777-35', 'zipcode' => '01310-100',
            'street' => 'Av. Paulista', 'number' => '1000', 'neighborhood' => 'Bela Vista', 'city' => 'São Paulo',
            'state' => 'SP', 'payment_method' => 'pix', 'shipping_method' => 'pac',
        ])->assertRedirect('/carrinho');
        $this->assertStringContainsString('O volume de 100 ml não está mais disponível.', session('stock_error'));
        $this->assertSame(0, Order::count());
        Http::assertNothingSent();
    }

    public function test_admin_nao_aceita_100_ml_novo_mas_o_produto_teste_salva(): void
    {
        Storage::fake('public');
        $admin = User::create(['name' => 'A', 'email' => 'a@aptk.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $p = $this->produto('negroni-classico', ['375 ml' => 165], ['active' => false]);
        $teste = $this->produto('teste', ['100 ml' => 1], ['active' => false]);

        $this->actingAs($admin)->put("/admin/produtos/{$p->id}", [
            'name' => $p->name, 'price' => '165', 'stock_qty' => '30', 'sizes' => '100 ml, 375 ml', 'size_prices' => '80, 165',
        ])->assertSessionHasErrors(['sizes' => 'O volume de 100 ml não está mais à venda e não pode ser adicionado.']);
        $this->assertSame(['375 ml'], $p->fresh()->sizes);

        $this->put("/admin/produtos/{$teste->id}", [
            'name' => 'Teste', 'price' => '1', 'stock_qty' => '30', 'sizes' => '100 ml', 'size_prices' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['100 ml'], $teste->fresh()->sizes);
    }

    public function test_loja_produto_e_home_sem_100_ml(): void
    {
        $p = $this->produto('negroni-classico', ['100 ml' => 80, '375 ml' => 165, '750 ml' => 210], ['featured' => true]);
        // A vitrine da Home só lista produto com foto.
        $p->images()->create(['path' => 'products/foto-branco-nc750-frente.jpg', 'is_primary' => true, 'sort_order' => 0]);
        $this->rodarUp();

        foreach (['/', route('catalog'), route('product', $p->slug)] as $url) {
            $this->get($url)->assertOk()->assertSee('Negroni-classico')->assertDontSee('100 ml')->assertDontSee('100ml');
        }
        $this->get(route('product', $p->slug))->assertSee('375 ml')->assertSee('750 ml');
    }
}
