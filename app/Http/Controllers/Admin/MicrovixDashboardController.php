<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\EnviarPedidoMicrovix;
use App\Models\MicrovixLog;
use App\Models\MicrovixProduct;
use App\Models\MicrovixReconcileLog;
use App\Models\MicrovixSyncState;
use App\Models\Order;
use App\Services\Microvix\MicrovixEnvio;
use App\Services\Microvix\MicrovixReconciler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Throwable;

/**
 * Painel de saúde da integração Microvix: estado do sync por método,
 * vínculos, conciliações e chamadas ao WS — com disparo manual.
 */
class MicrovixDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $filtroLog = $request->query('log'); // sucesso | erro | null

        return view('admin.microvix.index', [
            'fonte'      => config('microvix.fonte_da_verdade'),
            'syncStates' => MicrovixSyncState::orderBy('method')->get(),
            'stats'      => [
                'total'      => MicrovixProduct::count(),
                'vinculados' => MicrovixProduct::whereNotNull('product_id')->count(),
                'semVinculo' => MicrovixProduct::whereNull('product_id')->count(),
            ],
            'conciliacoes' => MicrovixReconcileLog::with('product')->latest('id')->limit(20)->get(),
            // Fase 2: fila de envio de pedidos ao ERP.
            'enviaLigado'  => (bool) config('microvix.envia_pedido'),
            'pedidos'      => [
                'pendentes'       => Order::whereIn('microvix_envio_estado', ['pendente', 'enviando'])->count(),
                'erro'            => Order::where('microvix_envio_estado', 'erro')->count(),
                'manual'          => Order::where('microvix_envio_estado', 'manual')->count(),
                'enviadosHoje'    => Order::where('microvix_envio_estado', 'enviado')
                    ->whereDate('microvix_synced_at', today())
                    ->count(),
            ],
            'pedidosComErro' => Order::with('user')
                ->whereIn('microvix_envio_estado', MicrovixEnvio::REENVIAVEIS)
                ->orderByDesc('microvix_envio_em')
                ->limit(20)
                ->get(),
            'filtroLog'    => $filtroLog,
            'logs'         => MicrovixLog::query()
                ->when($filtroLog === 'sucesso', fn ($q) => $q->where('success', true))
                ->when($filtroLog === 'erro', fn ($q) => $q->where('success', false))
                ->latest('id')
                ->limit(30)
                ->get(),
        ]);
    }

    /**
     * Trilha de envio de um pedido: cada chamada ao WS (real ou SIMULADA
     * pela trava) com o XML enviado/que seria enviado e a resposta.
     */
    public function pedido(Order $order): View
    {
        return view('admin.microvix.pedido', [
            'order' => $order->load('user'),
            'logs'  => MicrovixLog::where('order_id', $order->id)->latest('id')->get(),
        ]);
    }

    /**
     * Reenvio manual ("Reenviar para a Linx"): só pedido em erro ou manual,
     * pago, do período da integração e nunca enviado (microvix_pedido_id
     * vazio). A trava MICROVIX_ENVIA_PEDIDO continua valendo — ela vive no
     * MicrovixPedidoSender, não aqui.
     */
    public function reenviarPedido(Order $order): RedirectResponse
    {
        if (! MicrovixEnvio::podeReenviar($order)) {
            return back()->withErrors(['reenviar' => $this->motivoSemReenvio($order)]);
        }

        $order->forceFill([
            'microvix_envio_estado'     => 'pendente',
            'microvix_envio_tentativas' => 0,
            'microvix_envio_erro'       => null,
        ])->save();

        try {
            EnviarPedidoMicrovix::dispatch($order->id);
        } catch (Throwable $e) {
            return back()->withErrors(['reenviar' => "Falha ao re-enfileirar o pedido #{$order->id}: {$e->getMessage()}"]);
        }

        $aviso = config('microvix.envia_pedido')
            ? ''
            : ' Trava MICROVIX_ENVIA_PEDIDO desligada: o envio será SIMULADO (XML na trilha, nada sai pro ERP).';

        return back()->with('status', "Pedido #{$order->id} reenfileirado para a Linx.{$aviso}");
    }

    /**
     * Pedido manual (ou em erro) que a equipe lançou à mão na Linx: para de
     * descontar estoque na conciliação e nunca mais é enviado.
     */
    public function marcarLancado(Order $order): RedirectResponse
    {
        if ($order->microvix_pedido_id !== null || ! in_array($order->microvix_envio_estado, MicrovixEnvio::REENVIAVEIS, true)) {
            return back()->withErrors(['reenviar' => "Pedido #{$order->id}: só pedido manual ou em erro pode ser marcado como lançado na Linx."]);
        }

        $order->forceFill([
            'microvix_envio_estado' => 'lancado_manual',
            'microvix_envio_em'     => now(),
        ])->save();

        return back()->with('status', "Pedido #{$order->id} marcado como lançado manualmente na Linx.");
    }

    protected function motivoSemReenvio(Order $order): string
    {
        return match (true) {
            $order->microvix_pedido_id !== null => "Pedido #{$order->id} já está na Linx (pedido {$order->microvix_pedido_id}): não é reenviado.",
            ! MicrovixEnvio::noPeriodo($order)  => "Pedido #{$order->id} é anterior à integração (MICROVIX_ENVIO_A_PARTIR_DE): não vai para a Linx.",
            ! $order->isPaid() || $order->status === 'cancelled' => "Pedido #{$order->id} não está pago: só pedido pago vai para a Linx.",
            default => "Pedido #{$order->id}: só pedido em erro ou manual pode ser reenviado.",
        };
    }

    /** Dispara o sync do espelho (síncrono — o projeto não usa queue). */
    public function sync(): RedirectResponse
    {
        try {
            Artisan::call('microvix:sync-produtos');
        } catch (Throwable $e) {
            return back()->withErrors(['sync' => 'Sync falhou: '.$e->getMessage()]);
        }

        return back()->with('status', 'Sync executado. Detalhes por método abaixo; chamadas em microvix_logs.');
    }

    /**
     * Dispara a conciliação. Com fonte_da_verdade=site nada é gravado — o
     * MicrovixReconciler recusa no service; aqui rodamos só o plano e
     * mostramos o que SERIA feito.
     */
    public function conciliar(MicrovixReconciler $reconciler): RedirectResponse
    {
        try {
            if (config('microvix.fonte_da_verdade') === 'erp') {
                $plano = $reconciler->executar();

                return back()->with('status', count($plano['mudancas']).' alteração(ões) gravada(s) no catálogo — trilha em "Últimas conciliações".');
            }

            $plano = $reconciler->plano();

            return back()->with('status', sprintf(
                'Dry-run (fonte da verdade = site, nada gravado): %d alteração(ões) pendente(s), %d aviso(s). Detalhe: php artisan microvix:conciliar',
                count($plano['mudancas']),
                count($plano['avisos']),
            ));
        } catch (Throwable $e) {
            return back()->withErrors(['conciliar' => 'Conciliação falhou: '.$e->getMessage()]);
        }
    }
}
