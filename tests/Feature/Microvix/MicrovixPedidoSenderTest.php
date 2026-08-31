<?php

namespace Tests\Feature\Microvix;

use App\Exceptions\MicrovixNegocioException;
use App\Models\MicrovixLog;
use App\Models\MicrovixProduct;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Microvix\MicrovixClient;
use App\Services\Microvix\MicrovixPedidoSender;
use App\Services\Microvix\MicrovixRelogio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Fase 2 — envio de pedido ao ERP, tudo contra fixtures (nenhum envio
 * real; o formato da resposta do Importador é PALPITE, ver fixtures).
 */
class MicrovixPedidoSenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'microvix.environment'       => 'aceitacao',
            'microvix.id_portal'         => '18443',
            'microvix.cnpj_emp'          => '36491550000106',
            'microvix.user'              => 'usuario_teste',
            'microvix.password'          => 'senha-secreta',
            'microvix.chave'             => 'chave-secreta-uuid',
            'microvix.empresa'           => '4',
            'microvix.cod_vendedor'      => '15',
            'microvix.id_deposito'       => '1',
            'microvix.id_tabela_preco'   => '1',
            'microvix.id_status_inicial' => '3',
            'microvix.id_tipo_encomenda' => '99',
            'microvix.planos_pagamento'  => ['cartao' => 279, 'pix' => null, 'boleto' => null],
            // Testes de envio ligam a trava explicitamente; o default do
            // config segue false e há teste cobrindo o desligado.
            'microvix.envia_pedido'      => true,
        ]);
    }

    /* ----------------------------------------------------------------- */

    protected function fixture(string $nome): string
    {
        return file_get_contents(base_path("tests/Fixtures/microvix/{$nome}.xml"));
    }

    protected function fixturePedidoExiste(Order $order): string
    {
        return str_replace(
            ['{ID_PEDIDO}', '{COD_CLIENTE}'],
            [(string) $order->id, (string) $order->user_id],
            $this->fixture('pedidos-existe'),
        );
    }

    protected function pedido(string $metodo = 'cartao', bool $vincula = true): Order
    {
        $user = User::create([
            'name'     => 'Cliente ERP',
            'email'    => uniqid().'@aptk.test',
            'password' => bcrypt('secret123'),
            'role'     => 'customer',
        ]);

        $product = Product::firstOrCreate(['slug' => 'negroni-classico'], [
            'name'      => 'Negroni Clássico',
            'sku'       => 'NEG-750',
            'price'     => 189.90,
            'sizes'     => ['750 ml'],
            'stock_qty' => 30,
            'active'    => true,
        ]);

        if ($vincula) {
            MicrovixProduct::firstOrCreate(['codigoproduto' => 900123], [
                'product_id'   => $product->id,
                'size'         => '750 ml',
                'nome_produto' => 'NEGRONI 750',
                'ativo'        => true,
            ]);
        }

        $order = Order::create([
            'user_id'          => $user->id,
            'status'           => 'pending',
            'subtotal'         => 379.80,
            'discount'         => 0,
            'shipping_cost'    => 10.00,
            'total'            => 389.80,
            'payment_method'   => $metodo,
            'payment_status'   => 'pending',
            'ship_name'        => 'Cliente ERP',
            'ship_cpf'         => '111.444.777-35',
            'ship_phone'       => '11 99999-0000',
            'ship_zipcode'     => '01310-100',
            'ship_street'      => 'Avenida Paulista',
            'ship_number'      => '1000',
            'ship_complement'  => 'ap 12',
            'ship_neighborhood' => 'Bela Vista',
            'ship_city'        => 'São Paulo',
            'ship_state'       => 'SP',
        ]);

        $order->items()->create([
            'product_id'   => $product->id,
            'product_name' => 'Negroni Clássico',
            'product_sku'  => 'NEG-750',
            'size'         => '750 ml',
            'qty'          => 2,
            'unit_price'   => 189.90,
            'total_price'  => 379.80,
        ]);

        return $order->fresh(['items', 'user']);
    }

    protected function sender(): MicrovixPedidoSender
    {
        return app(MicrovixPedidoSender::class);
    }

    /** Corpos das requisições gravadas pelo Http::fake, na ordem. */
    protected function corpos(): array
    {
        $corpos = [];
        Http::recorded(function ($request) use (&$corpos) {
            $corpos[] = $request->body();

            return true;
        });

        return $corpos;
    }

    protected function param(string $nome, string $valor): string
    {
        return "<linx1:Name>{$nome}</linx1:Name><linx1:Value>{$valor}</linx1:Value>";
    }

    /* -----------------------------------------------------------------
     | Sequência do fluxograma (5.1)
     ------------------------------------------------------------------ */

    public function test_reserva_executa_a_sequencia_completa_na_ordem_da_doc(): void
    {
        Http::fake([
            '*api/integracao*'  => Http::response($this->fixture('pedidos-vazio')),
            '*Importador.svc*'  => Http::response($this->fixture('importa-sucesso')),
        ]);

        $order  = $this->pedido();
        $estado = $this->sender()->reservar($order);

        $corpos = $this->corpos();
        $this->assertCount(5, $corpos);
        $this->assertStringContainsString('B2CConsultaPedidos', $corpos[0]);
        $this->assertStringContainsString('B2CCadastraClientes', $corpos[1]);
        $this->assertStringContainsString('B2CCadastraClientesEnderecosEntrega', $corpos[2]);
        $this->assertStringContainsString('B2CCadastraPedido</linx2:Comando>', $corpos[3]);
        $this->assertStringContainsString('B2CCadastraPedidoItens', $corpos[4]);

        // Cliente: idempotência por CPF + endereço básico (obs. 1 e 3 da doc)
        $this->assertStringContainsString($this->param('atualizar_por_cpf', '1'), $corpos[1]);
        $this->assertStringContainsString($this->param('doc_cliente', '11144477735'), $corpos[1]);
        $this->assertStringContainsString($this->param('cidade_cliente', 'São Paulo'), $corpos[1]);

        // Endereço de entrega: chave global = orders.id, origem na descrição
        $this->assertStringContainsString($this->param('id_endereco_entrega', (string) $order->id), $corpos[2]);
        $this->assertStringContainsString($this->param('descricao', "Pedido #{$order->id}"), $corpos[2]);
        $this->assertStringContainsString($this->param('principal', '1'), $corpos[2]);

        // Pedido: reserva, plano do mapa, parâmetros de faturamento do config
        $this->assertStringContainsString($this->param('id_pedido', (string) $order->id), $corpos[3]);
        $this->assertStringContainsString($this->param('somente_reserva', '1'), $corpos[3]);
        $this->assertStringContainsString($this->param('plano_pagamento', '279'), $corpos[3]);
        $this->assertStringContainsString($this->param('tipo_frete', '99'), $corpos[3]);
        $this->assertStringContainsString($this->param('id_status', '3'), $corpos[3]);
        $this->assertStringContainsString($this->param('valor_frete', '10.00'), $corpos[3]);
        $this->assertStringContainsString($this->param('empresa', '4'), $corpos[3]);

        // Itens: codigoproduto do vínculo
        $this->assertStringContainsString($this->param('codigoproduto', '900123'), $corpos[4]);
        $this->assertStringContainsString($this->param('quantidade', '2'), $corpos[4]);
        $this->assertStringContainsString($this->param('vl_unitario', '189.90'), $corpos[4]);

        $this->assertSame('reservado', $estado);
        $order->refresh();
        $this->assertSame('reservado', $order->microvix_envio_estado);
        $this->assertTrue($order->microvix_reserved);
        $this->assertSame($order->id, (int) $order->microvix_pedido_id);
        $this->assertNull($order->microvix_envio_erro);
    }

    public function test_pedido_ja_integrado_nao_reenvia_soh_espelha_o_estado(): void
    {
        $order = $this->pedido();

        Http::fake([
            '*api/integracao*' => Http::response($this->fixturePedidoExiste($order)),
            '*Importador.svc*' => Http::response($this->fixture('importa-sucesso')),
        ]);

        $estado = $this->sender()->reservar($order);

        // Só a consulta saiu — nenhum método de entrada foi chamado.
        $this->assertCount(1, $this->corpos());
        $this->assertSame('reservado', $estado);
        $order->refresh();
        $this->assertSame('reservado', $order->microvix_envio_estado);
        $this->assertSame(3, (int) $order->microvix_status);
    }

    /* -----------------------------------------------------------------
     | Trava de config
     ------------------------------------------------------------------ */

    public function test_trava_desligada_monta_o_xml_loga_e_nao_chama_o_webservice(): void
    {
        config(['microvix.envia_pedido' => false]);
        Http::fake();

        $order  = $this->pedido();
        $estado = $this->sender()->reservar($order);

        Http::assertNothingSent();
        $this->assertSame('pendente', $estado);

        $logs = MicrovixLog::where('order_id', $order->id)->where('simulated', true)->get();
        $this->assertCount(5, $logs); // consulta + 4 métodos de entrada

        $cadastro = $logs->firstWhere('method', 'B2CCadastraPedido');
        $this->assertNotNull($cadastro);
        // O XML que TERIA sido enviado está inteiro no log...
        $this->assertStringContainsString('somente_reserva', $cadastro->request);
        // ...com credenciais mascaradas, como todo log do client.
        $this->assertStringNotContainsString('chave-secreta-uuid', $cadastro->request);
        $this->assertStringNotContainsString('senha-secreta', $cadastro->request);

        // Estado local intocado: nada foi enviado de verdade.
        $order->refresh();
        $this->assertNull($order->microvix_envio_estado);
        $this->assertFalse($order->microvix_reserved);
    }

    /* -----------------------------------------------------------------
     | Pré-requisitos (erro de negócio: claro e sem chamada)
     ------------------------------------------------------------------ */

    public function test_plano_de_pagamento_nulo_falha_com_mensagem_clara_sem_enviar(): void
    {
        Http::fake();
        $order = $this->pedido(metodo: 'pix');

        try {
            $this->sender()->reservar($order);
            $this->fail('Deveria ter lançado MicrovixNegocioException.');
        } catch (MicrovixNegocioException $e) {
            $this->assertStringContainsString("'pix'", $e->getMessage());
            $this->assertStringContainsString('plano', mb_strtolower($e->getMessage()));
        }

        Http::assertNothingSent();
    }

    public function test_tabela_de_preco_vazia_falha_com_mensagem_clara(): void
    {
        config(['microvix.id_tabela_preco' => '']);
        Http::fake();

        $this->expectException(MicrovixNegocioException::class);
        $this->expectExceptionMessage('MICROVIX_ID_TABELA_PRECO');

        $this->sender()->reservar($this->pedido());
    }

    public function test_item_sem_vinculo_no_espelho_falha_com_mensagem_clara(): void
    {
        Http::fake();
        $order = $this->pedido(vincula: false);

        $this->expectException(MicrovixNegocioException::class);
        $this->expectExceptionMessage('sem vínculo');

        $this->sender()->reservar($order);
    }

    /* -----------------------------------------------------------------
     | Resposta do Importador (formato NÃO confirmado — parse pessimista)
     ------------------------------------------------------------------ */

    public function test_recusa_do_erp_vira_erro_com_a_descricao_e_nao_toca_o_pedido_local(): void
    {
        Http::fake([
            '*api/integracao*' => Http::response($this->fixture('pedidos-vazio')),
            '*Importador.svc*' => Http::response($this->fixture('importa-falha')),
        ]);

        $order = $this->pedido();

        try {
            $this->sender()->reservar($order);
            $this->fail('Deveria ter lançado MicrovixNegocioException.');
        } catch (MicrovixNegocioException $e) {
            $this->assertStringContainsString('Plano de pagamento informado nao existe', $e->getMessage());
        }

        // Falha no ERP não mexe em NADA do pedido do cliente.
        $order->refresh();
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertNull($order->microvix_envio_estado);
        $this->assertFalse($order->microvix_reserved);
    }

    public function test_resposta_em_formato_desconhecido_nunca_vira_sucesso(): void
    {
        Http::fake([
            '*api/integracao*' => Http::response($this->fixture('pedidos-vazio')),
            '*Importador.svc*' => Http::response($this->fixture('importa-estranho')),
        ]);

        $order = $this->pedido();

        try {
            $this->sender()->reservar($order);
            $this->fail('Deveria ter lançado MicrovixNegocioException.');
        } catch (MicrovixNegocioException $e) {
            $this->assertStringContainsString('formato não reconhecido', $e->getMessage());
        }

        $order->refresh();
        $this->assertNull($order->microvix_envio_estado);
    }

    /* -----------------------------------------------------------------
     | Confirmação e cancelamento
     ------------------------------------------------------------------ */

    public function test_confirmar_reserva_existente_reenvia_soh_o_pedido_com_somente_reserva_zero(): void
    {
        $order = $this->pedido();

        Http::fake([
            '*api/integracao*' => Http::response($this->fixturePedidoExiste($order)),
            '*Importador.svc*' => Http::response($this->fixture('importa-sucesso')),
        ]);

        $estado = $this->sender()->confirmar($order);

        $corpos = $this->corpos();
        $this->assertCount(2, $corpos); // consulta + B2CCadastraPedido, nada de cliente/endereço/itens
        $this->assertStringContainsString('B2CCadastraPedido</linx2:Comando>', $corpos[1]);
        $this->assertStringContainsString($this->param('somente_reserva', '0'), $corpos[1]);

        $this->assertSame('confirmado', $estado);
        $order->refresh();
        $this->assertSame('confirmado', $order->microvix_envio_estado);
        $this->assertFalse($order->microvix_reserved);
    }

    public function test_confirmar_sem_reserva_no_erp_cadastra_o_fluxo_completo_ja_efetivado(): void
    {
        Http::fake([
            '*api/integracao*' => Http::response($this->fixture('pedidos-vazio')),
            '*Importador.svc*' => Http::response($this->fixture('importa-sucesso')),
        ]);

        $order  = $this->pedido();
        $estado = $this->sender()->confirmar($order);

        $corpos = $this->corpos();
        $this->assertCount(5, $corpos);
        $this->assertStringContainsString($this->param('somente_reserva', '0'), $corpos[3]);
        $this->assertSame('confirmado', $estado);
    }

    public function test_cancelar_pedido_integrado_envia_cancela_com_ativo_zero(): void
    {
        $order = $this->pedido();

        Http::fake([
            '*api/integracao*' => Http::response($this->fixturePedidoExiste($order)),
            '*Importador.svc*' => Http::response($this->fixture('importa-sucesso')),
        ]);

        $estado = $this->sender()->cancelar($order);

        $corpos = $this->corpos();
        $this->assertCount(2, $corpos);
        $this->assertStringContainsString('B2CCancelaPedido', $corpos[1]);
        $this->assertStringContainsString($this->param('ativo', '0'), $corpos[1]);

        $this->assertSame('cancelado', $estado);
        $order->refresh();
        $this->assertSame('cancelado', $order->microvix_envio_estado);
        $this->assertFalse($order->microvix_reserved);
    }

    public function test_cancelar_sem_pedido_no_erp_nao_chama_entrada_e_marca_local(): void
    {
        Http::fake([
            '*api/integracao*' => Http::response($this->fixture('pedidos-vazio')),
            '*Importador.svc*' => Http::response($this->fixture('importa-sucesso')),
        ]);

        $order  = $this->pedido();
        $estado = $this->sender()->cancelar($order);

        $this->assertCount(1, $this->corpos()); // só a consulta
        $this->assertSame('cancelado', $estado);
    }

    /* -----------------------------------------------------------------
     | Serialização (obs. 5 de B2CCadastraPedido)
     ------------------------------------------------------------------ */

    public function test_dois_pedidos_nunca_usam_o_mesmo_segundo_no_mesmo_metodo(): void
    {
        Http::fake([
            '*api/integracao*' => Http::response($this->fixture('pedidos-vazio')),
            '*Importador.svc*' => Http::response($this->fixture('importa-sucesso')),
        ]);

        $relogio = new RelogioVirtual;
        $sender  = new SenderComRegistroDeSegundos(app(MicrovixClient::class), $relogio);

        $sender->reservar($this->pedido());
        $sender->reservar($this->pedido());

        foreach (['B2CCadastraClientes', 'B2CCadastraClientesEnderecosEntrega', 'B2CCadastraPedido', 'B2CCadastraPedidoItens'] as $metodo) {
            $segundos = $sender->segundos[$metodo];
            $this->assertCount(2, $segundos);
            $this->assertNotSame($segundos[0], $segundos[1], "{$metodo} disparou duas vezes no mesmo segundo.");
        }

        // Dentro de UM pedido, métodos DIFERENTES podem dividir o segundo —
        // a doc só proíbe repetição do MESMO método (obs. 5).
        $primeiro = array_map(fn ($m) => $sender->segundos[$m][0], ['B2CCadastraClientes', 'B2CCadastraPedido']);
        $this->assertSame($primeiro[0], $primeiro[1]);
    }
}

/** Relógio de segundo virtual: nada dorme de verdade nos testes. */
class RelogioVirtual extends MicrovixRelogio
{
    public int $segundo = 1000;

    public function agora(): int
    {
        return $this->segundo;
    }

    public function esperaProximoSegundo(): void
    {
        $this->segundo++;
    }
}

/** Expõe o segundo usado por cada envio pra afirmar a serialização. */
class SenderComRegistroDeSegundos extends MicrovixPedidoSender
{
    public array $segundos = [];

    protected function respeitaJanelaDoMetodo(string $metodo): int
    {
        return $this->segundos[$metodo][] = parent::respeitaJanelaDoMetodo($metodo);
    }
}
