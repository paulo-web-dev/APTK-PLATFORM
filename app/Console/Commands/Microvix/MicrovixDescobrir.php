<?php

namespace App\Console\Commands\Microvix;

use App\Exceptions\MicrovixException;
use App\Services\Microvix\MicrovixClient;
use Throwable;

/**
 * Descobre os parâmetros de faturamento que ainda faltam no .env,
 * consultando os cadastros auxiliares do portal.
 */
class MicrovixDescobrir extends MicrovixCommand
{
    protected $signature = 'microvix:descobrir
        {--teste : Inclui portais de teste no B2CConsultaCNPJsChave (id_classificacao=6)}';

    protected $description = 'Consulta os cadastros auxiliares e sugere os valores do .env';

    public function handle(MicrovixClient $client): int
    {
        $sugestoes = [];

        // [método, colunas da tabela, chave .env, filtro p/ sugestão, campo do valor]
        $consultas = [
            ['B2CConsultaEmpresas',
                ['empresa', 'nome_emp', 'cnpjEmp', 'portal', 'centro_distribuicao'],
                'MICROVIX_EMPRESA',
                fn (array $r) => true,
                'empresa'],
            ['B2CConsultaVendedores',
                ['cod_vendedor', 'nome_vendedor', 'ativo', 'tipo'],
                'MICROVIX_COD_VENDEDOR',
                fn (array $r) => (string) ($r['ativo'] ?? '') === '1',
                'cod_vendedor'],
            ['B2CConsultaProdutosDepositos',
                ['id_deposito', 'nome_deposito', 'disponivel', 'outlet'],
                'MICROVIX_ID_DEPOSITO',
                fn (array $r) => strcasecmp((string) ($r['disponivel'] ?? ''), 'S') === 0,
                'id_deposito'],
            ['B2CConsultaProdutosTabelas',
                ['id_tabela', 'nome_tabela', 'ativa'],
                'MICROVIX_ID_TABELA_PRECO',
                fn (array $r) => strcasecmp((string) ($r['ativa'] ?? ''), 'S') === 0,
                'id_tabela'],
            ['B2CConsultaStatus',
                ['id_status', 'descricao_status'],
                'MICROVIX_ID_STATUS_INICIAL',
                fn (array $r) => true,
                'id_status'],
            ['B2CConsultaTipoEncomenda',
                ['id_tipo_encomenda', 'nm_tipo_encomenda'],
                'MICROVIX_ID_TIPO_ENCOMENDA',
                fn (array $r) => true,
                'id_tipo_encomenda'],
            ['B2CConsultaPlanos',
                ['plano', 'nome_plano', 'qtde_parcelas', 'desativado', 'tipo_plano', 'usa_tef'],
                'MICROVIX_PLANO_PAGAMENTO',
                fn (array $r) => strcasecmp((string) ($r['desativado'] ?? ''), 'N') === 0
                    && strcasecmp((string) ($r['tipo_plano'] ?? 'V'), 'V') === 0,
                'plano'],
        ];

        foreach ($consultas as [$method, $colunas, $chaveEnv, $filtro, $campo]) {
            $this->newLine();
            $this->info("== {$method}");

            try {
                $rows = $this->consultaPrimeiraCarga($client, $method);
            } catch (Throwable $e) {
                $this->error("  erro: {$e->getMessage()}");

                continue;
            }

            $this->tabela($rows, $colunas);

            // Sugere valor só quando há UMA opção óbvia após o filtro.
            $candidatos = array_values(array_filter($rows ?? [], $filtro));
            if (count($candidatos) === 1 && isset($candidatos[0][$campo])) {
                $sugestoes[$chaveEnv] = (string) $candidatos[0][$campo];
            } else {
                $sugestoes[$chaveEnv] = '';
            }
        }

        // CNPJs que a chave enxerga (sem cnpjEmp; por padrão só produção).
        $this->newLine();
        $this->info('== B2CConsultaCNPJsChave'.($this->option('teste') ? ' (portais de teste, id_classificacao=6)' : ''));

        try {
            $params = $this->option('teste') ? ['id_classificacao' => 6] : [];
            $cnpjs = $client->consulta('B2CConsultaCNPJsChave', $params, incluiCnpj: false);
            $this->tabela($cnpjs, [
                'CNPJ', 'nome_empresa', 'empresa', 'portal', 'nome_portal',
                'classificacao_portal', 'b2c',
            ]);
        } catch (MicrovixException $e) {
            $this->error("  erro: {$e->getMessage()}");
        }

        $this->newLine();
        $this->info('Bloco pronto para o .env (confira antes de colar — vazio = mais de uma opção, decidir com o cliente):');
        $this->newLine();
        foreach ($sugestoes as $chave => $valor) {
            $this->line("{$chave}={$valor}");
        }

        return self::SUCCESS;
    }
}
