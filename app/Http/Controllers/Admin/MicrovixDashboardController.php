<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MicrovixLog;
use App\Models\MicrovixProduct;
use App\Models\MicrovixReconcileLog;
use App\Models\MicrovixSyncState;
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
            'filtroLog'    => $filtroLog,
            'logs'         => MicrovixLog::query()
                ->when($filtroLog === 'sucesso', fn ($q) => $q->where('success', true))
                ->when($filtroLog === 'erro', fn ($q) => $q->where('success', false))
                ->latest('id')
                ->limit(30)
                ->get(),
        ]);
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
