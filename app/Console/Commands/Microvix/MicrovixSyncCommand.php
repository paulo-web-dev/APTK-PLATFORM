<?php

namespace App\Console\Commands\Microvix;

use App\Services\Microvix\MicrovixProductMirror;
use App\Services\Microvix\MicrovixSync;
use Illuminate\Console\Command;
use Throwable;

class MicrovixSyncCommand extends Command
{
    protected $signature = 'microvix:sync {method? : Um dos métodos suportados; vazio = todos}';

    protected $description = 'Sync incremental (por timestamp) dos métodos de saída suportados';

    public function handle(MicrovixSync $sync, MicrovixProductMirror $mirror): int
    {
        $method = $this->argument('method');

        $metodos = $method ? [$method] : MicrovixProductMirror::METHODS;

        if ($method && ! in_array($method, MicrovixProductMirror::METHODS, true)) {
            $this->error("Método não suportado: {$method}");
            $this->line('Suportados: '.implode(', ', MicrovixProductMirror::METHODS));

            return self::FAILURE;
        }

        $falhou = false;

        foreach ($metodos as $m) {
            try {
                $count = $sync->sync($m, $mirror->handler($m));
                $this->info("{$m}: {$count} registro(s).");
            } catch (Throwable $e) {
                $this->error("{$m}: {$e->getMessage()} (timestamp NÃO avançou)");
                $falhou = true;
            }
        }

        return $falhou ? self::FAILURE : self::SUCCESS;
    }
}
