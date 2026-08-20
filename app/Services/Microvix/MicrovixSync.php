<?php

namespace App\Services\Microvix;

use App\Models\MicrovixSyncState;
use Throwable;

/**
 * Sync incremental por método (doc, seção 4.1).
 *
 * O timestamp é o TIMESTAMP do SQL Server: contador por método, não é tempo.
 * Regra de ouro: o last_timestamp só avança (para o MAIOR retornado) quando
 * TODOS os registros do lote foram processados sem erro. Se avançasse com
 * erro no meio, os registros não processados sumiriam para sempre — a próxima
 * consulta só retorna timestamps maiores.
 */
class MicrovixSync
{
    public function __construct(protected MicrovixClient $client)
    {
    }

    /**
     * @param  callable(array<string, string>): void  $handler  recebe cada registro
     * @return int quantidade de registros processados
     */
    public function sync(string $method, callable $handler, array $extraParams = []): int
    {
        $state = MicrovixSyncState::firstOrCreate(
            ['method' => $method],
            ['last_timestamp' => 0],
        );

        try {
            $registros = $this->client->consulta(
                $method,
                ['timestamp' => $state->last_timestamp] + $extraParams,
            );

            $maior = (int) $state->last_timestamp;
            $count = 0;

            foreach ($registros as $registro) {
                $handler($registro);
                $count++;

                $ts = (int) ($registro['timestamp'] ?? 0);
                if ($ts > $maior) {
                    $maior = $ts;
                }
            }

            // Só chega aqui com o lote inteiro processado.
            $state->update([
                'last_timestamp' => $maior,
                'last_run_at'    => now(),
                'last_error'     => null,
                'records_synced' => $count,
            ]);

            return $count;
        } catch (Throwable $e) {
            // Registra o erro mas NÃO avança o timestamp.
            $state->update([
                'last_run_at' => now(),
                'last_error'  => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
