<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Reconciliação de Pix/boleto pendentes com a Appmax (independe do webhook):
 * aprova/cancela só com resposta conclusiva; Pix só cancela se a Appmax
 * disser "não pago" depois da expiração; boleto nunca cancela por aqui e,
 * passada a janela de 10 dias, vira "pagamento a conferir".
 */
class ReconciliacaoPagamentoTest extends TestCase
{
    use RefreshDatabase;

    /** status da API por appmax_order_id; 'erro' = 503 em todas as tentativas */
    protected array $statusNaApi = [];

    protected int $consultas = 0;

    protected ?User $cliente = null;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->travelTo(Carbon::parse('2026-10-18 15:00:00', 'UTC')); // 12:00 em Brasília
        config([
            'appmax.client_id'           => 'cid',
            'appmax.client_secret'       => 'csecret',
            'appmax.api_url'             => 'https://api.appmax.com.br',
            'appmax.auth_url'            => 'https://auth.appmax.com.br/oauth2/token',
            'appmax.http_retry_sleep_ms' => 0,
        ]);

        Http::fake(function (Request $req) {
            if (str_starts_with((string) parse_url($req->url(), PHP_URL_HOST), 'auth.')) {
                return Http::response(['access_token' => 'bearer-x', 'expires_in' => 3600]);
            }
            $this->consultas++;
            $id = (int) basename((string) parse_url($req->url(), PHP_URL_PATH));
            $status = $this->statusNaApi[$id] ?? 'pendente';

            return $status === 'erro'
                ? Http::response(['message' => 'Service Unavailable'], 503)
                : Http::response(['data' => ['order' => ['id' => $id, 'status' => $status, 'total_paid' => 3100]]]);
        });

        Product::create([
            'name' => 'Negroni', 'slug' => 'negroni', 'sku' => 'N750', 'price' => 210,
            'sizes' => ['750 ml'], 'size_prices' => ['750 ml' => 210], 'size_stock' => ['750 ml' => 10],
            'stock_qty' => 30, 'active' => true,
        ]);
    }

    /** Pedido Pix/boleto já enviado à Appmax, criado há $minutos. */
    protected function pedido(string $metodo, int $appmaxId, int $minutos = 5, ?string $expiraBr = null): Order
    {
        $this->cliente ??= User::create(['name' => 'C', 'email' => 'c@aptk.test', 'password' => bcrypt('x12345678'), 'role' => 'customer']);
        app(CartService::class)->add(Product::sole()->id, 1, '750 ml');

        $order = app(OrderService::class)->placeFromCart($this->cliente, [
            'name' => 'Cliente', 'phone' => '11999990000', 'cpf' => '191.000.000-00', 'zipcode' => '01310-100',
            'street' => 'Av. Paulista', 'number' => '1000', 'neighborhood' => 'Bela Vista', 'city' => 'São Paulo',
            'state' => 'SP', 'payment_method' => $metodo, 'shipping_method' => 'pac',
        ]);
        $order->update([
            'appmax_order_id' => $appmaxId,
            'payment_details' => ['type' => $metodo] + ($expiraBr ? ['expiration' => $expiraBr] : []),
        ]);
        DB::table('orders')->where('id', $order->id)->update(['created_at' => now()->subMinutes($minutos)]);

        return $order->refresh();
    }

    protected function estoque(): int
    {
        return Product::sole()->size_stock['750 ml'];
    }

    // ---- Pix -------------------------------------------------------------

    public function test_pix_pago_na_appmax_e_aprovado(): void
    {
        $order = $this->pedido('pix', 101, 10, '2026-10-18 12:50:00');
        $this->statusNaApi[101] = 'aprovado';

        $this->artisan('pagamentos:reconciliar')
            ->expectsOutputToContain('1 aprovado(s)')
            ->assertSuccessful();

        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertSame('processing', $order->status);
        $this->assertStringContainsString('Aprovado pela reconciliação com a Appmax em 18/10/2026 12:00', $order->notes);
        $this->assertSame(9, $this->estoque()); // venda: estoque segue baixado
    }

    public function test_pix_que_a_appmax_diz_nao_pago_depois_da_expiracao_e_cancelado(): void
    {
        // Expirou 11:30 (Brasília); agora são 12:00 > 11:30 + 10 min de folga.
        $order = $this->pedido('pix', 102, 90, '2026-10-18 11:30:00');

        $this->artisan('pagamentos:reconciliar')->assertSuccessful();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('expired', $order->payment_status);
        $this->assertSame(10, $this->estoque()); // devolvido
    }

    public function test_pix_pendente_dentro_do_prazo_ou_da_folga_fica_intocado(): void
    {
        $antes = $this->pedido('pix', 103, 10, '2026-10-18 12:50:00'); // ainda vale
        $folga = $this->pedido('pix', 104, 70, '2026-10-18 11:55:00'); // expirou há 5 min < folga

        $this->artisan('pagamentos:reconciliar')->assertSuccessful();

        $this->assertSame('pending', $antes->refresh()->status);
        $this->assertSame('pending', $folga->refresh()->status);
    }

    public function test_pix_sem_expiracao_gravada_nunca_cancela_pelo_relogio(): void
    {
        $order = $this->pedido('pix', 105, 100);

        $this->artisan('pagamentos:reconciliar')->assertSuccessful();

        $this->assertSame('pending', $order->refresh()->status);
    }

    public function test_resposta_inconclusiva_nao_mexe(): void
    {
        $exp = '2026-10-18 10:00:00'; // já expirado: só a resposta decide
        $analise = $this->pedido('pix', 106, 100, $exp);
        $erro = $this->pedido('pix', 107, 100, $exp);
        $estornado = $this->pedido('pix', 108, 100, $exp);
        $estranho = $this->pedido('pix', 109, 100, $exp);
        $this->statusNaApi += [106 => 'autorizado', 107 => 'erro', 108 => 'estornado', 109 => 'status_novo_qualquer'];

        $this->artisan('pagamentos:reconciliar')
            ->expectsOutputToContain('0 aprovado(s), 0 cancelado(s), 4 mantido(s)')
            ->assertSuccessful();

        foreach ([$analise, $erro, $estornado, $estranho] as $o) {
            $this->assertSame('pending', $o->refresh()->status);
            $this->assertSame('pending', $o->payment_status);
        }
        $this->assertSame(6, $this->estoque()); // nada devolvido
    }

    public function test_pedido_cancelado_na_appmax_e_cancelado_aqui(): void
    {
        $order = $this->pedido('pix', 110, 10, '2026-10-18 12:50:00');
        $this->statusNaApi[110] = 'cancelado';

        $this->artisan('pagamentos:reconciliar')->assertSuccessful();

        $this->assertSame('cancelled', $order->refresh()->status);
    }

    public function test_dry_run_consulta_mas_nao_grava(): void
    {
        $order = $this->pedido('pix', 111, 10, '2026-10-18 12:50:00');
        $this->statusNaApi[111] = 'aprovado';

        $this->artisan('pagamentos:reconciliar --dry-run')
            ->expectsOutputToContain('Dry-run: 1 aprovado(s)')
            ->assertSuccessful();

        $this->assertSame('pending', $order->refresh()->payment_status);
    }

    // ---- boleto e janela -------------------------------------------------

    public function test_boleto_pendente_nunca_cancela_e_pago_no_fim_da_janela_aprova(): void
    {
        $pendente = $this->pedido('boleto', 201, 60 * 24 * 7);
        $pagoTarde = $this->pedido('boleto', 202, 60 * 24 * 9);
        $this->statusNaApi[202] = 'aprovado';

        $this->artisan('pagamentos:reconciliar')->assertSuccessful();

        $this->assertSame('pending', $pendente->refresh()->status);
        $this->assertTrue($pagoTarde->refresh()->isPaid());
    }

    public function test_fora_da_janela_nao_consulta_nem_cancela_e_vira_a_conferir(): void
    {
        $boleto = $this->pedido('boleto', 203, 60 * 24 * 11);
        $pix = $this->pedido('pix', 112, 60 * 3, '2026-10-18 10:00:00');
        $this->statusNaApi += [203 => 'cancelado', 112 => 'cancelado'];

        $this->artisan('pagamentos:reconciliar')
            ->expectsOutputToContain('2 a conferir no admin')
            ->assertSuccessful();

        $this->assertSame(0, $this->consultas);
        $this->assertSame('pending', $boleto->refresh()->status);
        $this->assertSame('pending', $pix->refresh()->status);

        $admin = User::create(['name' => 'A', 'email' => 'a@aptk.test', 'password' => bcrypt('x12345678'), 'role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('Pagamento a conferir (2)');
        $this->actingAs($admin)->get(route('admin.orders.index', ['status' => 'a_conferir']))
            ->assertOk()
            ->assertSee('APTK-'.str_pad((string) $boleto->id, 5, '0', STR_PAD_LEFT));
        $this->actingAs($admin)->get(route('admin.orders.show', $boleto))
            ->assertSee('Pagamento a conferir');
    }

    // ---- polling e diagnóstico -------------------------------------------

    public function test_polling_da_tela_de_sucesso_reconcilia_com_limite_de_frequencia(): void
    {
        $order = $this->pedido('pix', 113, 2, '2026-10-18 12:50:00');
        $url = route('checkout.payment-status', $order);

        $this->actingAs($this->cliente)->getJson($url)->assertJson(['paid' => false]);
        $this->assertSame(1, $this->consultas);

        // Pagou; a próxima consulta dentro de 30s não vai à Appmax.
        $this->statusNaApi[113] = 'aprovado';
        $this->actingAs($this->cliente)->getJson($url)->assertJson(['paid' => false]);
        $this->assertSame(1, $this->consultas);

        $this->travel(31)->seconds();
        $this->actingAs($this->cliente)->getJson($url)->assertJson(['paid' => true, 'payment_status' => 'paid']);
        $this->assertSame(2, $this->consultas);
    }

    public function test_diagnostico_mostra_local_e_appmax_e_reconcilia_so_com_a_opcao(): void
    {
        $order = $this->pedido('pix', 130172222, 60 * 24, '2026-10-17 13:00:00'); // fora da janela
        $this->statusNaApi[130172222] = 'aprovado';

        $this->artisan('appmax:diagnostico', ['--pedido' => $order->id])
            ->expectsOutputToContain('fora — a conferir')
            ->expectsOutputToContain('aprovado')
            ->expectsOutputToContain('R$ 31,00')
            ->expectsOutputToContain('Rode com --reconciliar')
            ->assertSuccessful();
        $this->assertSame('pending', $order->refresh()->payment_status);

        $this->artisan('appmax:diagnostico', ['--pedido' => $order->id, '--reconciliar' => true])
            ->expectsOutputToContain('Agora: processing / paid')
            ->assertSuccessful();
        $this->assertTrue($order->refresh()->isPaid());
    }

    public function test_reconciliacao_esta_no_scheduler_a_cada_5_minutos(): void
    {
        $evento = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'pagamentos:reconciliar'));

        $this->assertNotNull($evento);
        $this->assertSame('*/5 * * * *', $evento->expression);
    }
}
