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
