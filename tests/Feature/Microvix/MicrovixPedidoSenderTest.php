<?php

namespace Tests\Feature\Microvix;

use App\Exceptions\MicrovixException;
use App\Exceptions\MicrovixNegocioException;
use App\Models\MicrovixLog;
use App\Models\Order;
use App\Services\Microvix\MicrovixClient;
use App\Services\Microvix\MicrovixEnvio;
use App\Services\Microvix\MicrovixPedidoSender;
use App\Services\Microvix\MicrovixRelogio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Fase 2 — envio de pedido PAGO à Linx (somente_reserva=0), tudo contra
 * fixtures (o formato da resposta do Importador é PALPITE, ver fixtures).
 */
class MicrovixPedidoSenderTest extends TestCase
{
    use MontaPedidoParaEnvio;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuraMicrovixParaEnvio();
    }

    protected function sender(): MicrovixPedidoSender
    {
        return app(MicrovixPedidoSender::class);
    }

    protected function param(string $nome, string $valor): string
    {
        return "<linx1:Name>{$nome}</linx1:Name><linx1:Value>{$valor}</linx1:Value>";
    }

    /** Valor de um parâmetro em um corpo SOAP (primeira ocorrência). */
    protected function valor(string $corpo, string $nome): ?string
    {
        return preg_match("#<linx1:Name>{$nome}</linx1:Name><linx1:Value>([^<]*)</linx1:Value>#", $corpo, $m) ? $m[1] : null;
    }

    protected function fakeFeliz(): void
    {
        Http::fake([
            '*api/integracao*' => Http::response($this->fixtureMicrovix('pedidos-vazio')),
            '*importador.svc*' => Http::response($this->fixtureMicrovix('importa-sucesso')),
            '*Importador.svc*' => Http::response($this->fixtureMicrovix('importa-sucesso')),
        ]);
    }

    /** Resposta do Importador com Sucesso=false e a descrição dada. */
    protected function importaFalha(string $descricao): string
    {
        return str_replace('Plano de pagamento informado nao existe no portal', $descricao, $this->fixtureMicrovix('importa-falha'));
    }

    /* -----------------------------------------------------------------
     | Envio feliz
     ------------------------------------------------------------------ */

    public function test_pix_pago_executa_a_sequencia_da_doc_ja_efetivado_com_plano_286(): void
    {
        $this->fakeFeliz();
        $order = $this->pedidoParaEnvio('pix');

        $this->assertSame('enviado', $this->sender()->enviar($order));

        $corpos = $this->corposEnviados();
        $this->assertCount(5, $corpos);
        $this->assertStringContainsString('B2CConsultaPedidos', $corpos[0]);
        $this->assertStringContainsString('B2CCadastraClientes', $corpos[1]);
        $this->assertStringContainsString('B2CCadastraClientesEnderecosEntrega', $corpos[2]);
        $this->assertStringContainsString('B2CCadastraPedido</linx2:Comando>', $corpos[3]);
        $this->assertStringContainsString('B2CCadastraPedidoItens', $corpos[4]);

        // Cliente: CPF do snapshot, idempotência por CPF, endereço básico
        $this->assertStringContainsString($this->param('doc_cliente', '11144477735'), $corpos[1]);
        $this->assertStringContainsString($this->param('atualizar_por_cpf', '1'), $corpos[1]);
        $this->assertStringContainsString($this->param('cidade_cliente', 'São Paulo'), $corpos[1]);
        // Endereço de entrega: snapshot ship_*, chave = orders.id
        $this->assertStringContainsString($this->param('id_endereco_entrega', (string) $order->id), $corpos[2]);
        $this->assertStringContainsString($this->param('endereco_cliente', 'Avenida Paulista'), $corpos[2]);
        $this->assertStringContainsString($this->param('cep_cliente', '01310-100'), $corpos[2]);

        // Pedido: sem reserva, plano do Pix, status inicial e faturamento do config
        $this->assertStringContainsString($this->param('somente_reserva', '0'), $corpos[3]);
        $this->assertStringContainsString($this->param('plano_pagamento', '286'), $corpos[3]);
        $this->assertStringContainsString($this->param('id_status', '5'), $corpos[3]);
        $this->assertStringContainsString($this->param('tipo_frete', '99'), $corpos[3]);
        $this->assertStringContainsString($this->param('empresa', '4'), $corpos[3]);
        $this->assertStringContainsString($this->param('cod_vendedor', '15'), $corpos[3]);
        $this->assertStringContainsString($this->param('id_deposito_faturamento', '1'), $corpos[3]);

        // Itens: codigoproduto do vínculo, preço pago pelo cliente
        $this->assertStringContainsString($this->param('codigoproduto', '900123'), $corpos[4]);
        $this->assertStringContainsString($this->param('vl_unitario', '189.90'), $corpos[4]);

        $order->refresh();
        $this->assertSame('enviado', $order->microvix_envio_estado);
        $this->assertSame($order->id, (int) $order->microvix_pedido_id);
        $this->assertSame(5, (int) $order->microvix_status);
        $this->assertFalse($order->microvix_reserved);
        $this->assertNull($order->microvix_envio_erro);
    }

    public function test_cartao_2x_usa_o_plano_280(): void
    {
        $this->fakeFeliz();
        $order = $this->pedidoParaEnvio('cartao', ['payment_details' => ['type' => 'cartao', 'installments' => 2]]);

        $this->assertSame('enviado', $this->sender()->enviar($order));
        $this->assertSame('280', $this->valor($this->corposEnviados()[3], 'plano_pagamento'));
    }

    public function test_total_na_linx_bate_com_o_total_cobrado_pela_appmax(): void
    {
        $this->fakeFeliz();
        // 2 × 189,90 + 1 × 211,00 (preço pago) − 15,00 + 37,40 = 613,20
        $order = $this->pedidoParaEnvio('pix', [
            'subtotal' => 590.80, 'discount' => 15.00, 'shipping_cost' => 37.40, 'total' => 613.20,
        ]);
        $outro = \App\Models\Product::create(['name' => 'Dry Martini', 'slug' => 'dry-martini', 'price' => 165, 'sizes' => ['375 ml']]);
        \App\Models\MicrovixProduct::create(['codigoproduto' => 900456, 'product_id' => $outro->id, 'size' => '375 ml']);
        $order->items()->create([
            'product_id' => $outro->id, 'product_name' => 'Dry Martini', 'size' => '375 ml',
            'qty' => 1, 'unit_price' => 211.00, 'total_price' => 211.00,
        ]);

        $this->sender()->enviar($order->fresh(['items', 'user']));

        $corpos = $this->corposEnviados();
        preg_match_all('#<linx1:Name>quantidade</linx1:Name><linx1:Value>(\d+)</linx1:Value>.*?<linx1:Name>vl_unitario</linx1:Name><linx1:Value>([\d.]+)</linx1:Value>#s', $corpos[4], $m, PREG_SET_ORDER);
        $this->assertCount(2, $m);
        $itens = array_sum(array_map(fn ($r) => (int) $r[1] * (float) $r[2], $m));

        $totalLinx = round($itens - (float) $this->valor($corpos[3], 'desconto') + (float) $this->valor($corpos[3], 'valor_frete'), 2);
        $this->assertSame(613.20, $totalLinx);
        $this->assertSame((float) $order->total, $totalLinx);
    }

    public function test_cupom_vai_no_cabecalho_com_o_desconto_e_os_itens_com_preco_cheio(): void
    {
        $this->fakeFeliz();
        $order = $this->pedidoParaEnvio('pix', [
            'subtotal' => 379.80, 'discount' => 56.97, 'coupon_code' => 'MITRE15',
            'shipping_cost' => 37.40, 'total' => 360.23,
        ]);

        $this->sender()->enviar($order->fresh(['items', 'user']));

        $corpos = $this->corposEnviados();
        $this->assertSame('56.97', $this->valor($corpos[3], 'desconto'));
        $this->assertSame('MITRE15', $this->valor($corpos[3], 'descricao_cupom_desconto'));
        $this->assertNull($this->valor($corpos[4], 'descricao_cupom_desconto'));
        $this->assertStringContainsString($this->param('vl_unitario', '189.90'), $corpos[4]);
    }

    public function test_sem_cupom_nao_manda_descricao_cupom_desconto(): void
    {
        $this->fakeFeliz();
        $this->sender()->enviar($this->pedidoParaEnvio('pix')->fresh(['items', 'user']));

        $this->assertNull($this->valor($this->corposEnviados()[3], 'descricao_cupom_desconto'));
    }

    /* -----------------------------------------------------------------
     | Manual: nada vai para a Linx
     ------------------------------------------------------------------ */

    public function test_item_sem_vinculo_fica_manual_e_nada_e_enviado(): void
    {
        Http::fake();
        $order = $this->pedidoParaEnvio('pix');
        \App\Models\MicrovixProduct::query()->update(['product_id' => null]);

        $this->assertSame('manual', $this->sender()->enviar($order));

        Http::assertNothingSent();
        $order->refresh();
        $this->assertSame('manual', $order->microvix_envio_estado);
        $this->assertStringContainsString('Lançar manualmente na Linx', $order->microvix_envio_erro);
        $this->assertStringContainsString('item sem vínculo com a Linx (Negroni Clássico 750 ml)', $order->microvix_envio_erro);
        $this->assertNull($order->microvix_pedido_id);
    }

    public function test_garrafa_personalizada_fica_manual_mesmo_com_o_volume_vinculado(): void
    {
        Http::fake();
        $order = $this->pedidoParaEnvio('pix');
        $order->items()->first()->update(['personalizacao' => ['bebida' => 'negroni', 'texto' => 'Ana']]);

        $this->assertSame('manual', $this->sender()->enviar($order->fresh(['items', 'user'])));
        Http::assertNothingSent();
        $this->assertStringContainsString('garrafa personalizada', $order->fresh()->microvix_envio_erro);
    }

    public function test_cartao_6x_sem_plano_no_mapa_fica_manual(): void
    {
        Http::fake();
        $order = $this->pedidoParaEnvio('cartao', ['payment_details' => ['type' => 'cartao', 'installments' => 6]]);

        $this->assertSame('manual', $this->sender()->enviar($order));
        Http::assertNothingSent();
        $this->assertStringContainsString('sem plano de pagamento na Linx para cartão 6x', $order->fresh()->microvix_envio_erro);
    }

    public function test_sem_cpf_no_snapshot_fica_manual_mesmo_com_cpf_no_cadastro(): void
    {
        Http::fake();
        $order = $this->pedidoParaEnvio('pix', ['ship_cpf' => null]);
        $order->user->forceFill(['cpf' => '11144477735'])->save();

        $this->assertSame('manual', $this->sender()->enviar($order->fresh(['items', 'user'])));
        $this->assertStringContainsString('pedido sem CPF', $order->fresh()->microvix_envio_erro);
    }

    /* -----------------------------------------------------------------
     | Fora do escopo: não toca em nada
     ------------------------------------------------------------------ */

    public function test_pedido_anterior_a_data_de_corte_nunca_vai(): void
    {
        Http::fake();
        $order = $this->pedidoParaEnvio('pix', ['created_at' => now()->subDays(3)]);

        $this->assertSame('nao_aplicavel', $this->sender()->enviar($order));
        Http::assertNothingSent();
        $this->assertNull($order->fresh()->microvix_envio_estado);
    }

    public function test_sem_data_de_corte_nada_e_enviado(): void
    {
        Http::fake();
        config(['microvix.envio_a_partir_de' => null]);

        $this->assertSame('nao_aplicavel', $this->sender()->enviar($this->pedidoParaEnvio('pix')));
        Http::assertNothingSent();
    }

    public function test_pedido_pendente_de_pagamento_nunca_vai(): void
    {
        Http::fake();
        $order = $this->pedidoParaEnvio('pix', ['payment_status' => 'pending', 'status' => 'pending', 'paid_at' => null]);

        $this->assertSame('nao_aplicavel', $this->sender()->enviar($order));
        Http::assertNothingSent();
    }

    public function test_pedido_ja_enviado_nunca_e_reenviado(): void
    {
        Http::fake();
        $order = $this->pedidoParaEnvio('pix', ['microvix_pedido_id' => 4242, 'microvix_envio_estado' => 'enviado']);

        $this->assertSame('enviado', $this->sender()->enviar($order));
        Http::assertNothingSent();
    }

    /* -----------------------------------------------------------------
     | Idempotência: pedido já na Linx sem marca local
     ------------------------------------------------------------------ */

    public function test_pedido_ja_na_linx_reenvia_pedido_e_itens_e_marca_enviado(): void
    {
        $order = $this->pedidoParaEnvio('pix');
        Http::fake([
            '*api/integracao*' => Http::response($this->fixturePedidoExisteNoErp($order)),
            '*Importador.svc*' => Http::response($this->fixtureMicrovix('importa-sucesso')),
        ]);

        $this->assertSame('enviado', $this->sender()->enviar($order));

        $corpos = $this->corposEnviados();
        $this->assertCount(3, $corpos); // consulta + pedido + itens (sem cliente/endereço)
        $this->assertStringContainsString('B2CCadastraPedido</linx2:Comando>', $corpos[1]);
        $this->assertStringContainsString('B2CCadastraPedidoItens', $corpos[2]);
        $this->assertSame('enviado', $order->fresh()->microvix_envio_estado);
    }

    /* -----------------------------------------------------------------
     | Erros
     ------------------------------------------------------------------ */

    public function test_recusa_de_negocio_lanca_negocio_e_nao_toca_o_pedido(): void
    {
        Http::fake([
            '*api/integracao*' => Http::response($this->fixtureMicrovix('pedidos-vazio')),
            '*Importador.svc*' => Http::response($this->fixtureMicrovix('importa-falha')),
        ]);
        $order = $this->pedidoParaEnvio('pix');

        try {
            $this->sender()->enviar($order);
            $this->fail('Deveria ter lançado MicrovixNegocioException.');
        } catch (MicrovixNegocioException $e) {
            $this->assertStringContainsString('Plano de pagamento informado nao existe', $e->getMessage());
        }

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertNull($order->microvix_pedido_id);
        // Resposta crua fica na trilha.
        $this->assertTrue(MicrovixLog::where('order_id', $order->id)->where('response', 'like', '%nao existe no portal%')->exists());
    }

    public function test_erro_de_pool_da_linx_e_temporario(): void
    {
        Http::fake([
            '*api/integracao*' => Http::response($this->fixtureMicrovix('pedidos-vazio')),
            '*Importador.svc*' => Http::response($this->importaFalha('Pool de conexoes esgotado, tente novamente')),
        ]);

        try {
            $this->sender()->enviar($this->pedidoParaEnvio('pix'));
            $this->fail('Deveria ter lançado MicrovixException.');
        } catch (MicrovixNegocioException $e) {
            $this->fail('Pool é temporário, não erro de negócio.');
        } catch (MicrovixException $e) {
            $this->assertStringContainsString('erro temporário da Linx', $e->getMessage());
        }
    }

    public function test_resposta_em_formato_desconhecido_nunca_vira_sucesso(): void
    {
        Http::fake([
            '*api/integracao*' => Http::response($this->fixtureMicrovix('pedidos-vazio')),
            '*Importador.svc*' => Http::response($this->fixtureMicrovix('importa-estranho')),
        ]);
        $order = $this->pedidoParaEnvio('pix');

        $this->expectException(MicrovixNegocioException::class);
        $this->expectExceptionMessage('formato não reconhecido');
        try {
            $this->sender()->enviar($order);
        } finally {
            $this->assertNull($order->fresh()->microvix_pedido_id);
        }
    }

    public function test_configuracao_vazia_e_erro_nao_manual(): void
    {
        Http::fake();
        config(['microvix.id_status_inicial' => null]);

        $this->expectException(MicrovixNegocioException::class);
        $this->expectExceptionMessage('MICROVIX_ID_STATUS_INICIAL');
        $this->sender()->enviar($this->pedidoParaEnvio('pix'));
    }

    /* -----------------------------------------------------------------
     | Trava e serialização
     ------------------------------------------------------------------ */

    public function test_trava_desligada_monta_o_xml_loga_e_nao_chama_o_webservice(): void
    {
        config(['microvix.envia_pedido' => false]);
        Http::fake();
        $order = $this->pedidoParaEnvio('pix');

        $this->assertSame('pendente', $this->sender()->enviar($order));

        Http::assertNothingSent();
        $logs = MicrovixLog::where('order_id', $order->id)->where('simulated', true)->get();
        $this->assertCount(5, $logs);
        $cadastro = $logs->firstWhere('method', 'B2CCadastraPedido');
        $this->assertStringContainsString($this->param('somente_reserva', '0'), $cadastro->request);
        $this->assertStringNotContainsString('chave-secreta-uuid', $cadastro->request);
        $this->assertStringNotContainsString('senha-secreta', $cadastro->request);
        $this->assertNull($order->fresh()->microvix_pedido_id);
    }

    public function test_dois_pedidos_nunca_usam_o_mesmo_segundo_no_mesmo_metodo(): void
    {
        $this->fakeFeliz();

        $sender = new SenderComRegistroDeSegundos(app(MicrovixClient::class), new RelogioVirtual, app(MicrovixEnvio::class));
        $sender->enviar($this->pedidoParaEnvio('pix'));
        $sender->enviar($this->pedidoParaEnvio('pix'));

        foreach (['B2CCadastraClientes', 'B2CCadastraClientesEnderecosEntrega', 'B2CCadastraPedido', 'B2CCadastraPedidoItens'] as $metodo) {
            $segundos = $sender->segundos[$metodo];
            $this->assertCount(2, $segundos);
            $this->assertNotSame($segundos[0], $segundos[1], "{$metodo} disparou duas vezes no mesmo segundo.");
        }

        // Métodos DIFERENTES podem dividir o segundo (a doc só proíbe o MESMO).
        $this->assertSame($sender->segundos['B2CCadastraClientes'][0], $sender->segundos['B2CCadastraPedido'][0]);
    }

    /* -----------------------------------------------------------------
     | Formato real do Importador (confirmado no pedido #58)
     ------------------------------------------------------------------ */

    /** Importador respondendo um XML montado a partir da fixture real. */
    protected function fakeImportador(string $resposta): void
    {
        Http::fake([
            '*api/integracao*' => Http::response($this->fixtureMicrovix('pedidos-vazio')),
            '*Importador.svc*' => Http::response($resposta),
        ]);
    }

    protected function sucessoReal(): string
    {
        return $this->fixtureMicrovix('importa-sucesso');
    }

    public function test_resposta_real_de_sucesso_e_reconhecida(): void
    {
        $this->fakeImportador($this->sucessoReal());

        $this->assertSame('enviado', $this->sender()->enviar($this->pedidoParaEnvio('pix')));
    }

    public function test_succeeded_false_sem_mensagem_e_erro_de_negocio(): void
    {
        $this->fakeImportador(str_replace('<a:Succeeded>true</a:Succeeded>', '<a:Succeeded>false</a:Succeeded>', $this->sucessoReal()));

        $this->expectException(MicrovixNegocioException::class);
        $this->expectExceptionMessage('Succeeded=false');
        $this->sender()->enviar($this->pedidoParaEnvio('pix'));
    }

    public function test_resultado_chamada_false_e_erro_de_negocio(): void
    {
        $this->fakeImportador(str_replace('<b:ResultadoChamada>true</b:ResultadoChamada>', '<b:ResultadoChamada>false</b:ResultadoChamada>', $this->sucessoReal()));

        $this->expectException(MicrovixNegocioException::class);
        $this->sender()->enviar($this->pedidoParaEnvio('pix'));
    }

    public function test_mensagem_com_itens_mesmo_com_succeeded_true_e_erro_e_junta_os_textos(): void
    {
        $this->fakeImportador(str_replace(
            '<a:Mensagem xmlns:b="http://schemas.microsoft.com/2003/10/Serialization/Arrays"/>',
            '<a:Mensagem xmlns:b="http://schemas.microsoft.com/2003/10/Serialization/Arrays"><b:string>Cliente sem cidade</b:string><b:string>CEP invalido</b:string></a:Mensagem>',
            $this->sucessoReal(),
        ));

        try {
            $this->sender()->enviar($this->pedidoParaEnvio('pix'));
            $this->fail('Deveria ter lançado MicrovixNegocioException.');
        } catch (MicrovixNegocioException $e) {
            $this->assertStringContainsString('Cliente sem cidade; CEP invalido', $e->getMessage());
        }
    }

    public function test_mensagem_erro_preenchida_junta_com_mensagem(): void
    {
        $this->fakeImportador($this->fixtureMicrovix('importa-falha'));

        try {
            $this->sender()->enviar($this->pedidoParaEnvio('pix'));
            $this->fail('Deveria ter lançado MicrovixNegocioException.');
        } catch (MicrovixNegocioException $e) {
            $this->assertStringContainsString('Plano de pagamento informado nao existe no portal; Registro 1 nao importado', $e->getMessage());
        }
    }

    public function test_succeeded_true_sem_importacao_log_nao_presume_sucesso(): void
    {
        $this->fakeImportador(preg_replace('#<a:importacaoLOG.*</a:importacaoLOG>#s', '', $this->sucessoReal()));

        $this->expectException(MicrovixNegocioException::class);
        $this->expectExceptionMessage('formato não reconhecido');
        $this->sender()->enviar($this->pedidoParaEnvio('pix'));
    }

    public function test_cidade_em_sigla_fica_manual(): void
    {
        Http::fake();
        $order = $this->pedidoParaEnvio('pix', ['ship_city' => 'VP']);

        $this->assertSame('manual', $this->sender()->enviar($order));
        Http::assertNothingSent();
        $this->assertStringContainsString('cidade inválida ("VP")', $order->fresh()->microvix_envio_erro);
    }

    /* -----------------------------------------------------------------
     | Reenvio do pedido em erro (caso do #58)
     ------------------------------------------------------------------ */

    public function test_reenvio_de_pedido_em_erro_recomeca_pela_consulta_e_cadastra_cliente_por_cpf(): void
    {
        $this->fakeFeliz();
        // Estado do #58: cliente já foi cadastrado na Linx, o parse antigo marcou erro.
        $order = $this->pedidoParaEnvio('pix', ['microvix_envio_estado' => 'erro', 'microvix_envio_erro' => 'formato não reconhecido']);

        $this->assertSame('enviado', $this->sender()->enviar($order));

        $corpos = $this->corposEnviados();
        $this->assertStringContainsString('B2CConsultaPedidos', $corpos[0]);
        $this->assertStringContainsString('B2CCadastraClientes', $corpos[1]);
        // Repetir o cadastro do cliente é seguro: atualiza pelo CPF, não duplica.
        $this->assertStringContainsString($this->param('atualizar_por_cpf', '1'), $corpos[1]);
        $this->assertStringContainsString($this->param('cod_cliente', (string) $order->user_id), $corpos[1]);
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
