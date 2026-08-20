<?php

namespace App\Console\Commands\Microvix;

use App\Models\MicrovixSyncState;
use Illuminate\Console\Command;

class MicrovixStatus extends Command
{
    protected $signature = 'microvix:status';

    protected $description = 'Mostra o estado do sync incremental por método';

    public function handle(): int
    {
        $states = MicrovixSyncState::orderBy('method')->get();

        if ($states->isEmpty()) {
            $this->line('Nenhum sync executado ainda. Rode: php artisan microvix:sync-produtos');

            return self::SUCCESS;
        }

        $this->table(
            ['método', 'last_timestamp', 'última execução', 'registros', 'último erro'],
            $states->map(fn (MicrovixSyncState $s) => [
                $s->method,
                (string) $s->last_timestamp,
                $s->last_run_at?->format('d/m/Y H:i:s') ?? '-',
                (string) ($s->records_synced ?? '-'),
                $s->last_error ? mb_strimwidth($s->last_error, 0, 60, '...') : '-',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
