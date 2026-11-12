<?php

namespace App\Console\Commands\Microvix;

use App\Models\Setting;
use App\Services\Microvix\MicrovixReconciler;
use Illuminate\Console\Command;
use Throwable;

/**
 * Espelho da Linx + conciliação num passo só (agendado a cada 15 min com
 * MICROVIX_SYNC_AGENDADO=true). A conciliação SÓ roda se os três métodos do
 * sync terminaram sem erro: espelho pela metade gravaria preço/estoque
 * parcial. Com MICROVIX_FONTE_VERDADE=site a conciliação roda em dry-run
 * (nada é gravado), como o microvix:conciliar.
 *
 * O resultado fica em settings (microvix.ultima_sincronizacao) para o
 * painel /admin/microvix.
 */
class MicrovixSincronizar extends Command
{
    public const SETTING = 'microvix.ultima_sincronizacao';

    protected $signature = 'microvix:sincronizar';

    protected $description = 'Sincroniza o espelho da Linx e, se os três métodos deram certo, concilia preço e estoque';

    public function handle(MicrovixReconciler $reconciler): int
    {
        $inicio = now();
        $registro = fn (bool $ok, string $mensagem, int $alteracoes = 0) => Setting::set(self::SETTING, json_encode([
            'inicio'     => $inicio->toIso8601String(),
            'fim'        => now()->toIso8601String(),
            'ok'         => $ok,
            'mensagem'   => $mensagem,
            'alteracoes' => $alteracoes,
        ]), 'microvix');

        try {
            $sync = $this->call('microvix:sync');
        } catch (Throwable $e) {
            $sync = self::FAILURE;
            report($e);
        }

        if ($sync !== self::SUCCESS) {
            $registro(false, 'Sync com erro: conciliação não rodou. Detalhe em microvix_logs.');
            $this->error('Sync com erro — conciliação NÃO rodou.');

            return self::FAILURE;
        }

        try {
            if (config('microvix.fonte_da_verdade') === 'erp') {
                $plano = $reconciler->executar();
                $n = count($plano['mudancas']);
                $registro(true, "Sync ok · {$n} alteração(ões) gravada(s).", $n);
            } else {
                $n = count($reconciler->plano()['mudancas']);
                $registro(true, "Sync ok · fonte = site, conciliação em dry-run ({$n} alteração(ões) pendente(s), nada gravado).");
            }
        } catch (Throwable $e) {
            report($e);
            $registro(false, 'Sync ok, mas a conciliação falhou: '.mb_substr($e->getMessage(), 0, 300));
            $this->error('Conciliação falhou: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sincronização concluída.');

        return self::SUCCESS;
    }

    /** Próxima execução agendada (quarto de hora seguinte), null se o agendamento está desligado. */
    public static function proxima(): ?\Illuminate\Support\Carbon
    {
        if (! config('microvix.sync_agendado')) {
            return null;
        }

        $agora = now()->startOfMinute();

        return $agora->copy()->addMinutes(15 - ($agora->minute % 15));
    }

    /** @return array{inicio: string, fim: string, ok: bool, mensagem: string, alteracoes: int}|null */
    public static function ultima(): ?array
    {
        $json = Setting::get(self::SETTING);

        return $json ? json_decode($json, true) : null;
    }
}
