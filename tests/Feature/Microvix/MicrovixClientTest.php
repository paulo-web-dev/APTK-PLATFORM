<?php

namespace Tests\Feature\Microvix;

use App\Exceptions\MicrovixException;
use App\Models\MicrovixLog;
use App\Services\Microvix\MicrovixClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MicrovixClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'microvix.environment' => 'aceitacao',
            'microvix.id_portal'   => '18443',
            'microvix.cnpj_emp'    => '36491550000106',
            'microvix.user'        => 'usuario_teste',
            'microvix.password'    => 'senha-secreta',
            'microvix.chave'       => 'chave-secreta-uuid',
        ]);
    }

    protected function fixture(string $nome): string
    {
        return file_get_contents(base_path("tests/fixtures/microvix/{$nome}.xml"));
    }

    protected function client(): MicrovixClient
    {
        return app(MicrovixClient::class);
    }

    public function test_envelope_de_consulta_segue_a_secao_8_da_doc(): void
    {
        Http::fake(['*' => Http::response($this->fixture('empresas'))]);

        $this->client()->consulta('B2CConsultaEmpresas', ['timestamp' => 0]);

        Http::assertSent(function (Request $request) {
            $body = $request->body();

            return $request->url() === 'https://webapi-aceitacao.microvix.com.br/api/integracao'
                && str_contains($body, '<Authentication user="usuario_teste" password="senha-secreta"/>')
                && str_contains($body, '<ResponseFormat>xml</ResponseFormat>')
                && str_contains($body, '<IdPortal>18443</IdPortal>')
                && str_contains($body, '<Name>B2CConsultaEmpresas</Name>')
                && str_contains($body, '<Parameter id="chave">chave-secreta-uuid</Parameter>')
                && str_contains($body, '<Parameter id="cnpjEmp">36491550000106</Parameter>')
                && str_contains($body, '<Parameter id="timestamp">0</Parameter>');
        });
    }

    public function test_consulta_zipa_cabecalho_c_com_valores_de_cada_r_por_indice(): void
    {
        Http::fake(['*' => Http::response($this->fixture('empresas'))]);

        $rows = $this->client()->consulta('B2CConsultaEmpresas', ['timestamp' => 0]);

        $this->assertCount(2, $rows);
        $this->assertSame('4', $rows[0]['empresa']);
        $this->assertSame('04 - APTK SPIRITS MATRIZ', $rows[0]['nome_emp']);
        $this->assertSame('185318320', $rows[1]['timestamp']);
        // Célula <D/> vazia vira string vazia, na coluna certa.
        $this->assertSame('', $rows[1]['end_unidade']);
        // Timestamp fica string: BIGINT do SQL Server não pode virar int.
        $this->assertSame('185318313', $rows[0]['timestamp']);
    }

    public function test_bom_utf8_no_inicio_da_resposta_e_removido_antes_do_parse(): void
    {
        // A resposta real do WS vem com BOM (conferido em microvix_logs id=7).
        Http::fake(['*' => Http::response("\xEF\xBB\xBF".$this->fixture('empresas'))]);

        $rows = $this->client()->consulta('B2CConsultaEmpresas', ['timestamp' => 0]);

        $this->assertCount(2, $rows);
    }

    public function test_resposta_com_cabecalho_e_sem_registros_retorna_lista_vazia_sem_erro(): void
    {
        Http::fake(['*' => Http::response($this->fixture('vazio'))]);

        $rows = $this->client()->consulta('B2CConsultaProdutos', ['timestamp' => 999]);

        $this->assertSame([], $rows);
    }

    public function test_resposta_sem_cabecalho_retorna_null_para_distinguir_de_nada_mudou(): void
    {
        // Caso real de Empresas/ProdutosTabelas sem cadastro no portal:
        // ResponseSuccess True, mas ResponseData sem <C>. Não é erro, e não
        // pode se confundir com [] ("nada mudou desde o timestamp").
        Http::fake(['*' => Http::response($this->fixture('sem-cadastro'))]);

        $rows = $this->client()->consulta('B2CConsultaEmpresas', ['timestamp' => 0]);

        $this->assertNull($rows);
    }

    public function test_registro_com_contagem_diferente_do_cabecalho_lanca_excecao(): void
    {
        // 4 colunas no <C>, 3 valores no segundo <R>: casamento posicional
        // errado corromperia o dado em silêncio — tem que explodir.
        $xml = '<?xml version="1.0" encoding="utf-8" standalone="yes"?>'
            .'<Microvix><ResponseData>'
            .'<C><D>id_status</D><D>descricao_status</D><D>timestamp</D><D>portal</D></C>'
            .'<R><D>1</D><D>EM ABERTO</D><D>1950211</D><D>18443</D></R>'
            .'<R><D>2</D><D>ENCERRADO</D><D>1950212</D></R>'
            .'</ResponseData>'
            .'<ResponseResult><ResponseSuccess>True</ResponseSuccess></ResponseResult></Microvix>';

        Http::fake(['*' => Http::response($xml)]);

        try {
            $this->client()->consulta('B2CConsultaStatus', ['timestamp' => 0]);
            $this->fail('Deveria ter lançado MicrovixException.');
        } catch (MicrovixException $e) {
            $this->assertStringContainsString('B2CConsultaStatus', $e->getMessage());
            $this->assertStringContainsString('posição 2', $e->getMessage());
            $this->assertStringContainsString('3 valores', $e->getMessage());
            $this->assertStringContainsString('4 colunas', $e->getMessage());
        }
    }

    public function test_response_success_diferente_de_true_lanca_excecao_com_o_response_result(): void
    {
        $xml = '<?xml version="1.0" encoding="utf-8" standalone="yes"?>'
            .'<Microvix><ResponseData/>'
            .'<ResponseResult><ResponseSuccess>False</ResponseSuccess>'
            .'<ResponseError>Chave invalida para o portal informado</ResponseError>'
            .'</ResponseResult></Microvix>';

        Http::fake(['*' => Http::response($xml)]);

        try {
            $this->client()->consulta('B2CConsultaEmpresas', ['timestamp' => 0]);
            $this->fail('Deveria ter lançado MicrovixException.');
        } catch (MicrovixException $e) {
            // O ResponseResult inteiro vai na mensagem — pode carregar
            // detalhe de erro que ainda não conhecemos.
            $this->assertStringContainsString('B2CConsultaEmpresas', $e->getMessage());
            $this->assertStringContainsString('Chave invalida para o portal informado', $e->getMessage());
        }
    }

    public function test_credenciais_sao_mascaradas_no_log(): void
    {
        Http::fake(['*' => Http::response($this->fixture('empresas'))]);

        $this->client()->consulta('B2CConsultaEmpresas', ['timestamp' => 0]);

        $log = MicrovixLog::firstOrFail();

        $this->assertStringContainsString('password="***"', $log->request);
        $this->assertStringContainsString('<Parameter id="chave">***</Parameter>', $log->request);
        $this->assertStringNotContainsString('senha-secreta', $log->request);
        $this->assertStringNotContainsString('chave-secreta-uuid', $log->request);
        // O restante do envelope continua íntegro para depuração.
        $this->assertStringContainsString('<Name>B2CConsultaEmpresas</Name>', $log->request);
        $this->assertStringContainsString('<Parameter id="cnpjEmp">36491550000106</Parameter>', $log->request);
        $this->assertTrue($log->success);
    }

    public function test_envelope_soap_de_importacao_segue_a_secao_8_da_doc(): void
    {
        Http::fake(['*' => Http::response('<Envelope><Body><ImportarResponse/></Body></Envelope>')]);

        $this->client()->importa('B2CCadastraClientes', [
            ['cod_cliente' => 1, 'doc_cliente' => '11122233344', 'ativo' => 1, 'receber_email' => 1],
            ['cod_cliente' => 2, 'doc_cliente' => '55566677788', 'ativo' => 1, 'receber_email' => 0],
        ]);

        Http::assertSent(function (Request $request) {
            $body = $request->body();

            return $request->url() === 'https://webapi-aceitacao.microvix.com.br/Importador.svc'
                && $request->header('SOAPAction') === ['http://tempuri.org/IImportador/Importar']
                && str_contains($body, 'xmlns:linx="http://schemas.datacontract.org/2004/07/Linx.Microvix.WebApi.Importacao.Requests"')
                && str_contains($body, 'xmlns:linx1="http://schemas.datacontract.org/2004/07/Linx.Microvix.WebApi.Business.Api"')
                && str_contains($body, 'xmlns:linx2="http://schemas.datacontract.org/2004/07/Linx.Microvix.WebApi.Importacao"')
                && str_contains($body, '<linx2:Comando>B2CCadastraClientes</linx2:Comando>')
                && substr_count($body, '<linx:Registros><linx:Colunas>') === 2
                && str_contains($body, '<linx1:Name>IdPortal</linx1:Name>')
                && str_contains($body, '<linx2:Pass>senha-secreta</linx2:Pass>');
        });

        // E no log, as credenciais do SOAP também saem mascaradas.
        $log = MicrovixLog::firstOrFail();
        $this->assertStringContainsString('<linx2:Pass>***</linx2:Pass>', $log->request);
        $this->assertStringNotContainsString('chave-secreta-uuid', $log->request);
        $this->assertStringContainsString('doc_cliente', $log->request);
    }

    public function test_consulta_retenta_ate_duas_vezes_em_falha_de_rede(): void
    {
        $tentativas = 0;

        Http::fake(function () use (&$tentativas) {
            $tentativas++;
            if ($tentativas < 3) {
                throw new ConnectionException('timeout simulado');
            }

            return Http::response($this->fixture('empresas'));
        });

        $rows = $this->client()->consulta('B2CConsultaEmpresas', ['timestamp' => 0]);

        $this->assertSame(3, $tentativas);
        $this->assertCount(2, $rows);
    }

    public function test_importa_nunca_retenta_nem_em_falha_de_rede(): void
    {
        // Doc 6.2, obs. 5 de B2CCadastraPedido: repetição pode reservar
        // estoque duas vezes. Tem que ser exatamente UMA tentativa.
        $tentativas = 0;

        Http::fake(function () use (&$tentativas) {
            $tentativas++;
            throw new ConnectionException('timeout simulado');
        });

        try {
            $this->client()->importa('B2CCadastraClientes', [['cod_cliente' => 1]]);
            $this->fail('Deveria ter lançado MicrovixException.');
        } catch (MicrovixException) {
            // esperado
        }

        $this->assertSame(1, $tentativas);
    }

    public function test_erro_http_vira_excecao_e_nao_e_retentado(): void
    {
        $tentativas = 0;

        Http::fake(function () use (&$tentativas) {
            $tentativas++;

            return Http::response('erro interno', 500);
        });

        try {
            $this->client()->consulta('B2CConsultaEmpresas', ['timestamp' => 0]);
            $this->fail('Deveria ter lançado MicrovixException.');
        } catch (MicrovixException $e) {
            $this->assertSame(500, $e->httpStatus);
        }

        // Erro de negócio/HTTP não é falha de rede: uma tentativa só.
        $this->assertSame(1, $tentativas);
        $this->assertFalse(MicrovixLog::firstOrFail()->success);
    }
}
