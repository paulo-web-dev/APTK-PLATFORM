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

        // Worker da fila database SEM supervisor — para de pegar job novo
        // depois de 50 s e morre; o cron do minuto seguinte sobe outro.
        // Filas: notificacoes (aviso de pedido por e-mail, leva 16) e
        // microvix (fase 2). Um envio à Linx pode levar minutos (20–45 s por
        // chamada): withoutOverlapping(10) não sobe worker novo enquanto o
        // anterior processa (trava expira em 10 min = $timeout do job). O lock
        // WithoutOverlapping do próprio job segue garantindo concorrência 1.
        // Linx → site a cada 15 min: espelho + conciliação (só concilia se o
        // sync inteiro deu certo). Desligado sem MICROVIX_SYNC_AGENDADO=true.
        // Com a Linx levando 20–45 s por chamada, uma rodada pode passar de
        // 15 min: withoutOverlapping(30) e em background pra não segurar o cron.
        $schedule->command('microvix:sincronizar')
            ->everyFifteenMinutes()
            ->withoutOverlapping(30)
            ->runInBackground()
            ->when(fn () => (bool) config('microvix.sync_agendado'));

        $schedule->command('queue:work --queue=notificacoes,microvix --stop-when-empty --max-time=50')
            ->everyMinute()
            ->withoutOverlapping(10);
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
