<?php

namespace App\Console\Commands\Microvix;

use App\Exceptions\MicrovixException;
use App\Services\Microvix\MicrovixClient;
use Illuminate\Console\Command;

/**
 * Base dos comandos microvix:* — só o helper de primeira carga.
 */
abstract class MicrovixCommand extends Command
{
    /**
     * Primeira carga: a seção 4.1 manda timestamp 0, mas o exemplo da
     * seção 8 usa a string NULL. Tenta 0; se vier vazio ou erro, tenta
     * NULL antes de suspeitar de credencial.
     *
     * null repassa a distinção do client: resposta sem cabeçalho <C>
     * (cadastro inexistente no portal), diferente de [] (nada mudou).
     */
    protected function consultaPrimeiraCarga(
        MicrovixClient $client,
        string $method,
        array $params = [],
        bool $incluiCnpj = true,
    ): ?array {
        try {
            $rows = $client->consulta($method, $params + ['timestamp' => 0], $incluiCnpj);
            if ($rows !== [] && $rows !== null) {
                return $rows;
            }
        } catch (MicrovixException $e) {
            $this->warn("  {$method} com timestamp 0 falhou ({$e->getMessage()}) — tentando NULL...");
        }

        return $client->consulta($method, $params + ['timestamp' => 'NULL'], $incluiCnpj);
    }

    /** Imprime registros como tabela, limitada às colunas que existem. */
    protected function tabela(?array $rows, array $colunas): void
    {
        if ($rows === null) {
            $this->line('  (sem cadastro no portal — resposta veio sem cabeçalho <C>)');

            return;
        }

        if ($rows === []) {
            $this->line('  (vazio — nada mudou desde o timestamp consultado)');

            return;
        }

        $presentes = array_values(array_filter(
            $colunas,
            fn ($c) => array_key_exists($c, $rows[0]),
        ));

        if ($presentes === []) {
            $presentes = array_keys($rows[0]);
        }

        $this->table($presentes, array_map(
            fn ($r) => array_map(
                fn ($c) => is_array($r[$c] ?? null) ? '' : (string) ($r[$c] ?? ''),
                $presentes,
            ),
            $rows,
        ));
    }
}
