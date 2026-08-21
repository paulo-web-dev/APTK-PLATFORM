<?php

namespace App\Console\Commands\Microvix;

use App\Services\Microvix\MicrovixReconciler;
use Illuminate\Console\Command;

class MicrovixConciliar extends Command
{
    protected $signature = 'microvix:conciliar
        {--dry-run : Mostra o que seria gravado, sem gravar}';

    protected $description = 'Aplica saldo e preço do espelho do ERP ao catálogo (size_stock/size_prices) — só grava com MICROVIX_FONTE_VERDADE=erp';

    public function handle(MicrovixReconciler $reconciler): int
    {
        $fonte = config('microvix.fonte_da_verdade');
        $efetivar = ! $this->option('dry-run') && $fonte === 'erp';

        if (! $efetivar && ! $this->option('dry-run')) {
            $this->warn("MICROVIX_FONTE_VERDADE={$fonte} — dry-run FORÇADO, nada será gravado. A virada é MICROVIX_FONTE_VERDADE=erp no .env.");
        }

        $plano = $reconciler->plano();
        $precos = array_values(array_filter($plano['mudancas'], fn ($m) => $m['campo'] === 'preco'));
        $saldos = array_values(array_filter($plano['mudancas'], fn ($m) => $m['campo'] === 'saldo'));

        $this->newLine();
        $this->info('== Preços ('.count($precos).' alteração(ões))');
        if ($precos !== []) {
            $this->table(
                ['Produto', 'Volume', 'Exibido hoje', '', 'ERP', 'Obs'],
                array_map(fn ($m) => [
                    $m['product']->name,
                    $m['size'] ?? '—',
                    'R$ '.number_format($m['exibido_hoje'], 2, ',', '.').($m['sem_size_prices'] ? ' (price base)' : ''),
                    // ▼ é o que passa despercebido na revisão e vira margem
                    // perdida em silêncio — por isso a direção é explícita.
                    $m['para'] > $m['exibido_hoje'] ? '▲' : ($m['para'] < $m['exibido_hoje'] ? '▼' : '='),
                    'R$ '.number_format($m['para'], 2, ',', '.'),
                    $m['sem_size_prices'] ? 'SEM size_prices — preço exibido muda no ato' : '',
                ], $precos),
            );
        }

        $this->newLine();
        $this->info('== Estoque ('.count($saldos).' alteração(ões))');
        if ($saldos !== []) {
            $this->table(
                ['Produto', 'Volume', 'Hoje', 'ERP'],
                array_map(fn ($m) => [
                    $m['product']->name,
                    $m['size'] ?? '—',
                    $m['de'] === null ? '(sem valor)' : (int) $m['de'],
                    (int) $m['para'],
                ], $saldos),
            );
        }

        if ($plano['avisos'] !== []) {
            $this->newLine();
            $this->info('== Avisos');
            foreach ($plano['avisos'] as $aviso) {
                $this->warn('  '.$aviso);
            }
        }

        $this->newLine();

        if (! $efetivar) {
            $this->info('Dry-run: nada foi gravado.');

            return self::SUCCESS;
        }

        $reconciler->executar();
        $this->info(count($plano['mudancas']).' alteração(ões) gravada(s) e registrada(s) em microvix_reconcile_log.');

        return self::SUCCESS;
    }
}
