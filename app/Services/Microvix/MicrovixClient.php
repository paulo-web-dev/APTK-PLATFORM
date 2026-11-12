<?php

namespace App\Services\Microvix;

use App\Exceptions\MicrovixException;
use App\Models\MicrovixLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Transporte puro do WS B2C do Microvix — zero regra de negócio.
 *
 * Dois protocolos (doc docs/linx-microvix-ws-b2c.md, seções 3 e 8; em caso de
 * divergência de nomes de campo, vale o PDF original):
 *   consulta() — WS de Saída: POST de XML puro no envelope <LinxMicrovix>
 *   importa()  — WS de Entrada: SOAP no Importador.svc
 */
class MicrovixClient
{
    /** Espera (s) antes de cada retry de consulta em falha temporária. */
    public const ESPERAS_CONSULTA = [5, 15, 30];

    /** Pedido dono da chamada corrente (trilha por pedido em microvix_logs). */
    protected ?int $orderId = null;

    /**
     * Consulta (WS de Saída). Injeta chave e cnpjEmp automaticamente;
     * o chamador passa o resto (timestamp, filtros).
     *
     * @param  bool  $incluiCnpj  B2CConsultaCNPJsChave não recebe cnpjEmp
     * @return array<int, array<string, string>>|null registros retornados;
     *         [] = cabeçalho presente sem registros ("nada mudou", doc 4.1);
     *         null = resposta sem cabeçalho <C> (cadastro inexistente no portal)
     */
    public function consulta(string $method, array $params = [], bool $incluiCnpj = true, ?int $orderId = null): ?array
    {
        $this->orderId = $orderId;
        $envelope = $this->envelopeConsulta($method, $params, $incluiCnpj);

        // Retry SÓ em consulta e SÓ em falha temporária da Linx: rede, 5xx e
        // HTTP 400 "Timeout expired"/"max pool size" (Linx de produção
        // sobrecarregada). 1 chamada + até 3 retries, esperando 5, 15 e 30 s.
        // Erro de negócio (200 com ResponseSuccess false, 400 de outro tipo)
        // nunca é retentado. Cada tentativa que falha fica em microvix_logs.
        $tentativa = 0;

        while (true) {
            $started = microtime(true);
            $espera = self::ESPERAS_CONSULTA[$tentativa] ?? null;
            $tentativa++;

            try {
                $response = Http::withHeaders(['Content-Type' => 'text/xml; charset=utf-8'])
                    ->timeout((int) config('microvix.timeout', 60))
                    ->withBody($envelope, 'text/xml')
                    ->post($this->urlSaida());
            } catch (ConnectionException $e) {
                $this->log('out', $method, $envelope, null, null, false, "Tentativa {$tentativa}: {$e->getMessage()}", $started);
                if ($espera === null) {
                    throw new MicrovixException("Falha de rede ao consultar {$method}: {$e->getMessage()}");
                }
                Sleep::for($espera)->seconds();

                continue;
            }

            if ($espera !== null && self::falhaTemporaria($response)) {
                $this->log('out', $method, $envelope, $response->body(), $response->status(), false, "Tentativa {$tentativa}: HTTP {$response->status()} temporário", $started);
                Sleep::for($espera)->seconds();

                continue;
            }

            return $this->finalizaConsulta($method, $envelope, $response, $started);
        }
    }

    /** 5xx, ou 400 de timeout/pool esgotado do lado da Linx: vale retentar. */
    public static function falhaTemporaria(Response $response): bool
    {
        return $response->serverError()
            || ($response->status() === 400 && preg_match('/timeout expired|max pool size/i', $response->body()) === 1);
    }

    /**
     * Importação (WS de Entrada, SOAP).
     *
     * NUNCA faz retry — nem em falha de rede. A doc (6.2, obs. 5 de
     * B2CCadastraPedido) proíbe múltiplas requisições de cadastro no mesmo
     * método, portal e segundo: um timeout em que a requisição CHEGOU,
     * seguido de retry, reservaria o estoque duas vezes. Se falhar, o erro
     * sobe e a decisão de repetir é de quem chama (serializada, fase 2).
     * NÃO "consertar" isso adicionando retry.
     *
     * @param  array<int, array<string, mixed>>  $registros  um item por <linx:Registros>
     */
    public function importa(string $comando, array $registros, ?int $orderId = null): array
    {
        $this->orderId = $orderId;
        $envelope = $this->envelopeImportacao($comando, $registros);
        $started  = microtime(true);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml;charset=UTF-8',
                'SOAPAction'   => 'http://tempuri.org/IImportador/Importar',
            ])
                ->timeout((int) config('microvix.timeout', 60))
                ->withBody($envelope, 'text/xml')
                ->post($this->urlEntrada());
        } catch (ConnectionException $e) {
            $this->log('in', $comando, $envelope, null, null, false, $e->getMessage(), $started);
            throw new MicrovixException("Falha de rede ao importar {$comando}: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            $this->log('in', $comando, $envelope, $response->body(), $response->status(), false, "HTTP {$response->status()}", $started);
            throw new MicrovixException(
                "Erro HTTP {$response->status()} ao importar {$comando}.",
                $response->body(),
                $response->status(),
            );
        }

        $this->log('in', $comando, $envelope, $response->body(), $response->status(), true, null, $started);

        return $this->xmlParaArray($this->removePrefixosNamespace($response->body()), $comando);
    }

    /**
     * Simulação (trava MICROVIX_ENVIA_PEDIDO desligada): monta o envelope
     * que SERIA enviado e grava em microvix_logs com simulated=true, sem
     * nenhuma chamada HTTP. Devolve o envelope (mascarado no log, íntegro
     * no retorno).
     */
    public function simulaConsulta(string $method, array $params = [], ?int $orderId = null): string
    {
        $envelope = $this->envelopeConsulta($method, $params, true);
        $this->logSimulado('out', $method, $envelope, $orderId);

        return $envelope;
    }

    /** @see simulaConsulta() */
    public function simulaImporta(string $comando, array $registros, ?int $orderId = null): string
    {
        $envelope = $this->envelopeImportacao($comando, $registros);
        $this->logSimulado('in', $comando, $envelope, $orderId);

        return $envelope;
    }

    protected function logSimulado(string $direction, string $method, string $envelope, ?int $orderId): void
    {
        MicrovixLog::create([
            'order_id'    => $orderId,
            'direction'   => $direction,
            'method'      => $method,
            'request'     => self::limparSegredos($envelope),
            'response'    => null,
            'http_status' => null,
            'success'     => true,
            'simulated'   => true,
            'error'       => null,
            'duration_ms' => 0,
        ]);
    }

    public function urlSaida(): string
    {
        return config('microvix.urls.saida.'.config('microvix.environment'));
    }

    public function urlEntrada(): string
    {
        return config('microvix.urls.entrada.'.config('microvix.environment'));
    }

    /* -----------------------------------------------------------------
     | Envelopes
     ------------------------------------------------------------------ */

    /** Envelope <LinxMicrovix> (doc, seção 8 — "Consulta nos métodos de Saída"). */
    protected function envelopeConsulta(string $method, array $params, bool $incluiCnpj): string
    {
        $todos = ['chave' => config('microvix.chave')];
        if ($incluiCnpj && ! array_key_exists('cnpjEmp', $params)) {
            $todos['cnpjEmp'] = config('microvix.cnpj_emp');
        }
        $todos += $params;

        $linhas = '';
        foreach ($todos as $id => $value) {
            if ($value === null) {
                continue;
            }
            $linhas .= sprintf(
                "      <Parameter id=\"%s\">%s</Parameter>\n",
                htmlspecialchars((string) $id, ENT_XML1),
                htmlspecialchars((string) $value, ENT_XML1),
            );
        }

        return sprintf(
            <<<'XML'
<?xml version="1.0" encoding="utf-8" ?>
<LinxMicrovix>
  <Authentication user="%s" password="%s"/>
  <ResponseFormat>xml</ResponseFormat>
  <IdPortal>%s</IdPortal>
  <Command>
    <Name>%s</Name>
    <Parameters>
%s    </Parameters>
  </Command>
</LinxMicrovix>
XML,
            htmlspecialchars((string) config('microvix.user'), ENT_XML1),
            htmlspecialchars((string) config('microvix.password'), ENT_XML1),
            htmlspecialchars((string) config('microvix.id_portal'), ENT_XML1),
            htmlspecialchars($method, ENT_XML1),
            $linhas,
        );
    }

    /**
     * Envelope SOAP do Importador (doc, seção 8 — "Inclusão nos métodos de
     * Entrada"). Estrutura e namespaces copiados literalmente do exemplo.
     */
    protected function envelopeImportacao(string $comando, array $registros): string
    {
        $param = fn (string $name, string $value): string => sprintf(
            "<linx1:CommandParameter><linx1:Name>%s</linx1:Name><linx1:Value>%s</linx1:Value></linx1:CommandParameter>",
            htmlspecialchars($name, ENT_XML1),
            htmlspecialchars($value, ENT_XML1),
        );

        $blocos = '';
        foreach ($registros as $registro) {
            $colunas = '';
            foreach ($registro as $name => $value) {
                $colunas .= $param((string) $name, $value === null ? 'NULL' : (string) $value);
            }
            $blocos .= "<linx:Registros><linx:Colunas>{$colunas}</linx:Colunas></linx:Registros>";
        }

        $seletor = $param('chave', (string) config('microvix.chave'))
            .$param('cnpjEmp', (string) config('microvix.cnpj_emp'))
            .$param('IdPortal', (string) config('microvix.id_portal'));

        return sprintf(
            '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"'
            .' xmlns:tem="http://tempuri.org/"'
            .' xmlns:linx="http://schemas.datacontract.org/2004/07/Linx.Microvix.WebApi.Importacao.Requests"'
            .' xmlns:linx1="http://schemas.datacontract.org/2004/07/Linx.Microvix.WebApi.Business.Api"'
            .' xmlns:linx2="http://schemas.datacontract.org/2004/07/Linx.Microvix.WebApi.Importacao">'
            .'<soapenv:Header/><soapenv:Body><tem:Importar><tem:request>'
            .'<linx:ParamsSeletorDestino>%s</linx:ParamsSeletorDestino>'
            .'<linx:Tabela><linx2:Comando>%s</linx2:Comando><linx2:Registros>%s</linx2:Registros></linx:Tabela>'
            .'<linx:UserAuth><linx2:Pass>%s</linx2:Pass><linx2:User>%s</linx2:User></linx:UserAuth>'
            .'</tem:request></tem:Importar></soapenv:Body></soapenv:Envelope>',
            $seletor,
            htmlspecialchars($comando, ENT_XML1),
            $blocos,
            htmlspecialchars((string) config('microvix.password'), ENT_XML1),
            htmlspecialchars((string) config('microvix.user'), ENT_XML1),
        );
    }

    /* -----------------------------------------------------------------
     | Resposta da consulta
     ------------------------------------------------------------------ */

    protected function finalizaConsulta(string $method, string $envelope, Response $response, float $started): ?array
    {
        if (! $response->successful()) {
            $this->log('out', $method, $envelope, $response->body(), $response->status(), false, "HTTP {$response->status()}", $started);
            throw new MicrovixException(
                "Erro HTTP {$response->status()} ao consultar {$method}.",
                $response->body(),
                $response->status(),
            );
        }

        $rows = $this->extraiRegistros($response->body(), $method);

        $this->log('out', $method, $envelope, $response->body(), $response->status(), true, null, $started);

        return $rows;
    }

    /**
     * Formato REAL da resposta de consulta (conferido em microvix_logs; a doc
     * não o especifica): tabela posicional —
     *
     *   <Microvix>
     *     <ResponseData>
     *       <C><D>nome_coluna</D>…</C>    cabeçalho: nomes das colunas
     *       <R><D>valor</D>…</R>…         um <R> por registro, valores na
     *                                     MESMA ORDEM do cabeçalho (por índice)
     *     </ResponseData>
     *     <ResponseResult><ResponseSuccess>True</ResponseSuccess></ResponseResult>
     *   </Microvix>
     *
     * Retorno: lista de arrays associativos (coluna => valor). Tudo STRING —
     * conversão de tipo é de quem consome, não do transporte. Em especial o
     * timestamp é BIGINT do SQL Server: não pode virar int em PHP 32 bits.
     *
     *   []   = cabeçalho presente, sem <R>: nada mudou desde o timestamp (4.1)
     *   null = sem cabeçalho <C>: o cadastro não existe no portal
     */
    protected function extraiRegistros(string $xml, string $method): ?array
    {
        $xml = $this->removeBom($xml);

        $anterior = libxml_use_internal_errors(true);
        $sx = simplexml_load_string($xml);
        libxml_use_internal_errors($anterior);

        if ($sx === false) {
            throw new MicrovixException("Resposta de {$method} não é XML válido.", $xml);
        }

        // ResponseSuccess é lido ANTES de qualquer parsing de dados.
        if (! isset($sx->ResponseResult->ResponseSuccess)) {
            throw new MicrovixException(
                "Resposta de {$method} sem <ResponseResult><ResponseSuccess>.",
                $xml,
            );
        }

        if (strcasecmp(trim((string) $sx->ResponseResult->ResponseSuccess), 'true') !== 0) {
            throw new MicrovixException(
                "Consulta {$method} retornou ResponseSuccess diferente de True. "
                    .'ResponseResult completo: '.trim((string) $sx->ResponseResult->asXML()),
                $xml,
            );
        }

        if (! isset($sx->ResponseData->C)) {
            return null;
        }

        $colunas = [];
        foreach ($sx->ResponseData->C->D as $d) {
            $colunas[] = (string) $d;
        }

        $registros = [];
        $posicao = 0;

        foreach ($sx->ResponseData->R as $r) {
            $posicao++;

            $valores = [];
            foreach ($r->D as $d) {
                $valores[] = (string) $d;
            }

            // Contagem divergente NUNCA passa: casamento posicional errado
            // corromperia o dado em silêncio (valor na coluna errada).
            if (count($valores) !== count($colunas)) {
                throw new MicrovixException(sprintf(
                    'Consulta %s: registro na posição %d tem %d valores para %d colunas do cabeçalho (%s).',
                    $method,
                    $posicao,
                    count($valores),
                    count($colunas),
                    implode(', ', $colunas),
                ), $xml);
            }

            $registros[] = array_combine($colunas, $valores);
        }

        return $registros;
    }

    /** BOM UTF-8 no início da resposta quebra alguns parsers de XML. */
    protected function removeBom(string $xml): string
    {
        return str_starts_with($xml, "\xEF\xBB\xBF") ? substr($xml, 3) : $xml;
    }

    protected function xmlParaArray(string $xml, string $contexto): array
    {
        $anterior = libxml_use_internal_errors(true);
        $sx = simplexml_load_string($xml);
        libxml_use_internal_errors($anterior);

        if ($sx === false) {
            throw new MicrovixException("Resposta de {$contexto} não é XML válido.", $xml);
        }

        return json_decode(json_encode($sx), true) ?: [];
    }

    /** Remove prefixos de namespace (s:, linx:, ...) para leitura tolerante do SOAP. */
    protected function removePrefixosNamespace(string $xml): string
    {
        return preg_replace('/(<\/?)[\w.-]+:/', '$1', $xml) ?? $xml;
    }

    /* -----------------------------------------------------------------
     | Log (credenciais mascaradas)
     ------------------------------------------------------------------ */

    /**
     * Grava a chamada em microvix_logs. O request nunca vai com credencial:
     * password e chave viram *** (o restante do envelope fica íntegro).
     */
    protected function log(
        string $direction,
        string $method,
        string $request,
        ?string $response,
        ?int $httpStatus,
        bool $success,
        ?string $error,
        float $started,
    ): void {
        MicrovixLog::create([
            'order_id'    => $this->orderId,
            'direction'   => $direction,
            'method'      => $method,
            'request'     => self::limparSegredos($request),
            // A resposta do Importador ecoa o envelope enviado (<b:XML>, com
            // chave e senha em texto puro) e a chave (<b:Chave>) — limpa tudo.
            'response'    => $response === null ? null : self::limparSegredos($response),
            'http_status' => $httpStatus,
            'success'     => $success,
            'error'       => $error,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
    }

    /**
     * Tudo que vai pra microvix_logs passa por aqui (e o
     * microvix:limpar-segredos-log aplica o mesmo nos registros antigos):
     * senha/chave dos envelopes mascaradas, <XML> ecoado pelo Importador
     * trocado por "[omitido]" e <Chave> do importacaoLOG mascarada.
     * Elementos casados por local-name (qualquer prefixo de namespace).
     */
    public static function limparSegredos(string $xml): string
    {
        // Envelope de saída: atributo password + <Parameter id="chave">.
        $xml = preg_replace('/password="[^"]*"/', 'password="***"', $xml);
        $xml = preg_replace('/(<Parameter id="chave">)[^<]*(<\/Parameter>)/', '$1***$2', $xml);

        // Envelope SOAP: <linx2:Pass> + o CommandParameter de nome "chave".
        $xml = preg_replace('/(<linx2:Pass>)[^<]*(<\/linx2:Pass>)/', '$1***$2', $xml);
        $xml = preg_replace(
            '/(<linx1:Name>\s*chave\s*<\/linx1:Name>\s*<linx1:Value>)[^<]*(<\/linx1:Value>)/s',
            '$1***$2',
            $xml,
        );

        // Resposta do Importador: envelope ecoado (escapado) e a chave.
        $xml = preg_replace('/(<(?:[\w.-]+:)?XML(?:\s[^>]*)?>)[\s\S]*?(<\/(?:[\w.-]+:)?XML>)/', '$1[omitido]$2', $xml);
        $xml = preg_replace('/(<(?:[\w.-]+:)?Chave(?:\s[^>]*)?>)[^<]*(<\/(?:[\w.-]+:)?Chave>)/', '$1***$2', $xml);

        return $xml;
    }
}
