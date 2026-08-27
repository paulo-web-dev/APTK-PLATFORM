<?php

namespace Tests\Feature\Microvix;

use App\Models\MicrovixLog;
use App\Models\MicrovixProduct;
use App\Models\MicrovixReconcileLog;
use App\Models\MicrovixSyncState;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminMicrovixDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Admin',
            'email'    => 'admin@aptk.test',
            'password' => bcrypt('secret123'),
            'role'     => 'admin',
        ]);

        config([
            'microvix.environment' => 'aceitacao',
            'microvix.id_portal'   => '18443',
            'microvix.cnpj_emp'    => '36491550000106',
            'microvix.user'        => 'u',
            'microvix.password'    => 'p',
            'microvix.chave'       => 'c',
        ]);
    }

    protected function fixture(string $nome): string
    {
        return file_get_contents(base_path("tests/fixtures/microvix/{$nome}.xml"));
    }

    public function test_painel_mostra_fonte_sync_vinculos_e_logs(): void
    {
        MicrovixProduct::create(['codigoproduto' => 900001, 'nome_produto' => 'X']);
        MicrovixSyncState::create(['method' => 'B2CConsultaProdutos', 'last_timestamp' => 42, 'records_synced' => 7]);
        MicrovixLog::create(['direction' => 'out', 'method' => 'B2CConsultaProdutos', 'request' => 'r', 'success' => true, 'duration_ms' => 10]);

        $this->actingAs($this->admin)
            ->get('/admin/microvix')
            ->assertOk()
            ->assertSee('Fonte da verdade')
            ->assertSee('dry-run') // fonte=site (default) fica explícito no topo
            ->assertSee('B2CConsultaProdutos')
            ->assertSee('Sem vínculo');
    }

    public function test_filtro_de_log_por_erro(): void
    {
        MicrovixLog::create(['direction' => 'out', 'method' => 'MetodoOk', 'request' => 'r', 'success' => true, 'duration_ms' => 10]);
        MicrovixLog::create(['direction' => 'out', 'method' => 'MetodoErro', 'request' => 'r', 'success' => false, 'error' => 'boom', 'duration_ms' => 10]);

        $this->actingAs($this->admin)
            ->get('/admin/microvix?log=erro')
            ->assertOk()
            ->assertSee('MetodoErro')
            ->assertDontSee('MetodoOk');
    }

    public function test_botao_sync_roda_o_espelho(): void
    {
        Http::fake(['*' => Http::response($this->fixture('vazio'))]);

        $this->actingAs($this->admin)
            ->post('/admin/microvix/sync')
            ->assertRedirect()
            ->assertSessionHas('status');

        // Um estado por método do espelho, criado pelo sync disparado do painel.
        $this->assertSame(3, MicrovixSyncState::count());
    }

    public function test_botao_conciliar_com_fonte_site_e_dry_run(): void
    {
        config(['microvix.fonte_da_verdade' => 'site']);

        $p = Product::create(['name' => 'Negroni', 'slug' => 'negroni', 'sizes' => ['375 ml']]);
        MicrovixProduct::create(['codigoproduto' => 900001, 'product_id' => $p->id, 'size' => '375 ml', 'saldo' => 3, 'preco' => 99.90]);

        $this->actingAs($this->admin)
            ->post('/admin/microvix/conciliar')
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $msg) => str_contains($msg, 'nada gravado'));

        $this->assertNull($p->refresh()->size_stock);
        $this->assertSame(0, MicrovixReconcileLog::count());
    }

    public function test_botao_conciliar_com_fonte_erp_grava(): void
    {
        config(['microvix.fonte_da_verdade' => 'erp']);

        $p = Product::create(['name' => 'Negroni', 'slug' => 'negroni', 'sizes' => ['375 ml']]);
        MicrovixProduct::create(['codigoproduto' => 900001, 'product_id' => $p->id, 'size' => '375 ml', 'saldo' => 3, 'preco' => 99.90]);

        $this->actingAs($this->admin)
            ->post('/admin/microvix/conciliar')
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $msg) => str_contains($msg, 'gravada'));

        $this->assertSame(3, $p->refresh()->size_stock['375 ml']);
        $this->assertSame(2, MicrovixReconcileLog::count()); // saldo + preco
    }
}
