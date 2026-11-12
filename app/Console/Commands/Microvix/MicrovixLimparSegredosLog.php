<?php

namespace App\Console\Commands\Microvix;

use App\Models\MicrovixLog;
use App\Services\Microvix\MicrovixClient;
use Illuminate\Console\Command;

/**
 * Aplica nos registros já gravados de microvix_logs a mesma limpeza que o
 * client faz hoje antes de gravar (MicrovixClient::limparSegredos): <XML>
 * ecoado pelo Importador → "[omitido]", <Chave> e senha mascaradas.
 * Idempotente: rodar de novo não muda nada.
 */
class MicrovixLimparSegredosLog extends Command
{
    protected $signature = 'microvix:limpar-segredos-log
        {--dry-run : Só conta quantos registros seriam limpos}';

    protected $description = 'Remove do microvix_logs o envelope ecoado pelo Importador e mascara chave/senha nos registros já gravados';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $total = 0;
        $limpos = 0;

        MicrovixLog::query()->orderBy('id')->chunkById(200, function ($logs) use (&$total, &$limpos, $dryRun) {
            foreach ($logs as $log) {
                $total++;
                $mudou = [];

                foreach (['request', 'response'] as $campo) {
                    if ($log->{$campo} === null) {
                        continue;
                    }
                    $limpo = MicrovixClient::limparSegredos($log->{$campo});
                    if ($limpo !== $log->{$campo}) {
                        $mudou[$campo] = $limpo;
                    }
                }

                if ($mudou === []) {
                    continue;
                }
                $limpos++;
                if (! $dryRun) {
                    // Sem tocar em updated_at/created_at: a trilha mantém as datas.
                    MicrovixLog::whereKey($log->id)->toBase()->update($mudou);
                }
            }
        });

        $this->info(($dryRun ? 'Dry-run: ' : '')."{$limpos} de {$total} registro(s) ".($dryRun ? 'seriam limpos.' : 'limpos.'));

        return self::SUCCESS;
    }
}
