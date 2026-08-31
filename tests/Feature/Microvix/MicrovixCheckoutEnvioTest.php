<?php

namespace Tests\Feature\Microvix;

use App\Jobs\EnviarPedidoMicrovix;
use App\Models\MicrovixProduct;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Enfileiramento da reserva no placeFromCart (fluxo real do checkout) e a
 * corrida reserva × confirmação: Pix pago em segundos pode processar a
 * confirmação ANTES de a reserva rodar — o resultado tem que ser UM pedido
 * no ERP, confirmado.
 */
class MicrovixCheckoutEnvioTest extends TestCase
{
    use MontaPedidoParaEnvio;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuraMicrovixParaEnvio();
        config(['appmax.webhook_token' => 'tok-teste']);
    }

    /** Produto vendável + vínculo no espelho, carrinho montado. */
    protected function montaCarrinho(): Product
    {
        $product = Product::create([
            'name'        => 'Negroni Clássico',
            'slug'        => 'negroni-classico',
            'sku'         => 'NEG-750',
            'price'       => 189.90,
            'sizes'       => ['750 ml'],
            'size_prices' => ['750 ml' => 189.90],
            'stock_qty'   => 30,
            'active'      => true,
        ]);

        MicrovixProduct::create([
            'codigoproduto' => 900123,
            'product_id'    => $product->id,
            'size'          => '750 ml',
            'nome_produto'  => 'NEGRONI 750',
            'ativo'         => true,
        ]);

        app(CartService::class)->add($product->id, 2, '750 ml');

        return $product;
    }

    protected function cliente(): User
    {
        return User::create([
            'name'     => 'Cliente',
            'email'    => 'cliente@aptk.test',
            'password' => bcrypt('secret123'),
            'role'     => 'customer',
        ]);
    }

    protected function dadosCheckout(string $metodo = 'cartao'): array
    {
        return [
            'name'           => 'Cliente Teste',
            'phone'          => '11 99999-0000',
            'cpf'            => '111.444.777-35',
            'zipcode'        => '01310-100',
            'street'         => 'Avenida Paulista',
            'number'         => '1000',
            'neighborhood'   => 'Bela Vista',
            'city'           => 'São Paulo',
            'state'          => 'SP',
            'payment_method' => $metodo,
        ];
    }

    public function test_place_from_cart_enfileira_a_reserva_com_after_commit(): void
    {
        Queue::fake();
        $this->montaCarrinho();

        $order = app(OrderService::class)->placeFromCart($this->cliente(), $this->dadosCheckout());

        Queue::assertPushed(EnviarPedidoMicrovix::class, function ($job) use ($order) {
            return $job->orderId === $order->id
                && $job->acao === 'reservar'
                && $job->afterCommit === true       // cinto: sobrevive a transação externa
                && $job->queue === 'microvix';
        });
    }

    public function test_falha_no_envio_ao_erp_nao_derruba_o_fechamento_do_pedido(): void
    {
        // Fila sync: o job roda DENTRO do placeFromCart. ERP fora do ar não
        // pode impedir a venda — o try/catch do dispatch segura tudo.
        Http::fake([
            '*api/integracao*' => fn () => throw new ConnectionException('erp fora do ar'),
        ]);

        $product = $this->montaCarrinho();
        $order   = app(OrderService::class)->placeFromCart($this->cliente(), $this->dadosCheckout());

        // Venda intacta: pedido criado, estoque debitado, carrinho limpo.
        $this->assertSame('pending', $order->status);
        $this->assertSame(28, (int) $product->fresh()->stock_qty);
        $this->assertSame(0, app(CartService::class)->count());
        // E a integração parou em erro, visível pro painel, sem tocar a venda.
        $this->assertSame('erro', $order->fresh()->microvix_envio_estado);
    }

    public function test_confirmacao_processada_antes_da_reserva_resulta_em_um_pedido_soh_confirmado(): void
    {
        Queue::fake();
        $this->montaCarrinho();

        // 1) Pedido criado (reserva entra na fila) e pago quase junto
        //    (confirmação entra na fila via webhook).
        $order = app(OrderService::class)->placeFromCart($this->cliente(), $this->dadosCheckout());
        $order->update(['appmax_order_id' => 555]);

        $this->postJson('/webhooks/appmax/tok-teste', ['event' => 'order_paid', 'data' => ['id' => 555]])
            ->assertOk();

        $jobs = collect(Queue::pushedJobs()[EnviarPedidoMicrovix::class] ?? [])
            ->pluck('job')
            ->keyBy('acao');
        $this->assertNotNull($reserva = $jobs->get('reservar'));
        $this->assertNotNull($confirmacao = $jobs->get('confirmar'));

        Http::fake([
            '*api/integracao*' => Http::response($this->fixtureMicrovix('pedidos-vazio')),
            '*Importador.svc*' => Http::response($this->fixtureMicrovix('importa-sucesso')),
        ]);

        // 2) A CONFIRMAÇÃO roda primeiro: reserva nunca chegou ao ERP, então
        //    ela cadastra o fluxo completo já efetivado (somente_reserva=0).
        app()->call([$confirmacao, 'handle']);

        $corpos = $this->corposEnviados();
        $this->assertCount(5, $corpos);
        $this->assertStringContainsString('somente_reserva</linx1:Name><linx1:Value>0', $corpos[3]);
        $this->assertSame('confirmado', $order->fresh()->microvix_envio_estado);

        // 3) A RESERVA atrasada roda depois: não regride nem duplica — zero
        //    requisições novas. UM pedido no ERP, confirmado.
        app()->call([$reserva, 'handle']);

        $this->assertCount(5, $this->corposEnviados());
        $order->refresh();
        $this->assertSame('confirmado', $order->microvix_envio_estado);
        $this->assertFalse($order->microvix_reserved);
        $this->assertSame(1, (int) $order->microvix_envio_tentativas);
    }
}
