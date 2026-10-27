<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Pedido pending de cartão parado = processo de pagamento morto no
        // meio, com estoque preso (ESTADO-ATUAL §3). Sem queue no projeto,
        // isto depende do cron do servidor chamar o scheduler:
        //   * * * * * cd /var/www/aptk && php artisan schedule:run >> /dev/null 2>&1
        $schedule->command('pedidos:cancelar-cartao-preso')
            ->everyFifteenMinutes()
            ->withoutOverlapping();

        // Pix/boleto pendentes × Appmax: webhook perdido nunca deixa pedido
        // pago como pendente (ReconciliacaoPagamento).
        $schedule->command('pagamentos:reconciliar')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Worker da fila database SEM supervisor — roda até 50s e morre; o
        // cron do minuto seguinte sobe outro. Filas: notificacoes (aviso de
        // pedido pago por e-mail, leva 16) e microvix (fase 2). O lock
        // WithoutOverlapping do job do Microvix garante concorrência 1 mesmo
        // se dois workers coexistirem; o withoutOverlapping daqui só evita
        // processo acumulado quando um lote passa de 1 minuto.
        $schedule->command('queue:work --queue=notificacoes,microvix --stop-when-empty --max-time=50')
            ->everyMinute()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
