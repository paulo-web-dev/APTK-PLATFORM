<?php

namespace App\Services\Microvix;

use App\Exceptions\MicrovixException;
use App\Models\MicrovixLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

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
    /**
     * Consulta (WS de Saída). Injeta chave e cnpjEmp automaticamente;
     * o chamador passa o resto (timestamp, filtros).
     *
     * @param  bool  $incluiCnpj  B2CConsultaCNPJsChave não recebe cnpjEmp
     * @return array<int, array<string, string>> registros retornados
     */
    public function consulta(string $method, array $params = [], bool $incluiCnpj = true): array
    {
        $envelope = $this->envelopeConsulta($method, $params, $incluiCnpj);

        // Retry 2x SÓ em falha de rede (ConnectionException) e SÓ em consulta.
        // Erro de negócio (resposta com erro) nunca é retentado.
        $started  = microtime(true);
        $attempts = 0;
        $response = null;

        do {
            $attempts++;
            try {
                $response = Http::withHeaders(['Content-Type' => 'text/xml; charset=utf-8'])
                    ->timeout((int) config('microvix.timeout', 60))
                    ->withBody($envelope, 'text/xml')
                    ->post($this->urlSaida());
                break;
            } catch (ConnectionException $e) {
                if ($attempts >= 3) { // 1 chamada + 2 retries
                    $this->log('out', $method, $envelope, null, null, false, $e->getMessage(), $started);
                    throw new MicrovixException("Falha de rede ao consultar {$method}: {$e->getMessage()}");
                }
            }
        } while (true);

        return $this->finalizaConsulta($method, $envelope, $response, $started);
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
    public function importa(string $comando, array $registros): array
    {
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

    protected function finalizaConsulta(string $method, string $envelope, Response $response, float $started): array
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
     * A doc não especifica o envelope de RESPOSTA da consulta, então o parser
     * é deliberadamente tolerante: procura elementos <Registro> (qualquer
     * profundidade); sem eles, procura o primeiro grupo de irmãos repetidos
     * com filhos. Resposta vazia NÃO é erro (seção 4.1: "nada mudou").
     * O XML cru fica em microvix_logs para conferir o formato real.
     */
    protected function extraiRegistros(string $xml, string $method): array
    {
        if (trim($xml) === '') {
            return [];
        }

        $dados = $this->xmlParaArray($xml, $method);

        $registros = $this->buscaChave($dados, 'registro');
        if ($registros !== null) {
            return $this->normalizaLista($registros);
        }

        $lista = $this->buscaListaRepetida($dados);

        return $lista !== null ? $this->normalizaLista($lista) : [];
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

    /** Busca (case-insensitive, em profundidade) o valor de uma chave. */
    protected function buscaChave(array $dados, string $chave): mixed
    {
        foreach ($dados as $k => $v) {
            if (strcasecmp((string) $k, $chave) === 0) {
                return $v;
            }
            if (is_array($v) && ($achado = $this->buscaChave($v, $chave)) !== null) {
                return $achado;
            }
        }

        return null;
    }

    /** Primeira lista numérica de arrays associativos encontrada em profundidade. */
    protected function buscaListaRepetida(array $dados): ?array
    {
        if (array_is_list($dados) && $dados !== [] && is_array($dados[0]) && ! array_is_list($dados[0])) {
            return $dados;
        }

        foreach ($dados as $v) {
            if (is_array($v) && ($lista = $this->buscaListaRepetida($v)) !== null) {
                return $lista;
            }
        }

        return null;
    }

    /** Um registro único vem como array associativo — embrulha em lista. */
    protected function normalizaLista(mixed $registros): array
    {
        if (! is_array($registros)) {
            return [];
        }

        return array_is_list($registros) ? $registros : [$registros];
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
            'direction'   => $direction,
            'method'      => $method,
            'request'     => $this->mascaraCredenciais($request),
            // A resposta pode ecoar o Command enviado (com a chave) — mascara também.
            'response'    => $response === null ? null : $this->mascaraCredenciais($response),
            'http_status' => $httpStatus,
            'success'     => $success,
            'error'       => $error,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
    }

    protected function mascaraCredenciais(string $xml): string
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

        return $xml;
    }
}
