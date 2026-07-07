<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Instalação do aplicativo Appmax (doc 2.3 — Criar as credenciais do Merchant).
 *
 * A "URL de validação" cadastrada no app (/appmax/health) recebe:
 *  - GET  → status simples (conferência manual no navegador)
 *  - POST → HEALTH CHECK server-to-server disparado DENTRO do
 *           POST /app/client/generate. É aqui que as credenciais do
 *           merchant chegam. Regras da doc:
 *             · responder HTTP 200 + {"external_id": "<uuid v4 NOVO a cada
 *               chamada>"} (+ alias opcional)
 *             · só app_id é obrigatório; campos ausentes NUNCA podem gerar
 *               400/422 (causa nº 1 de falha de instalação)
 *
 * As credenciais recebidas (client_id/client_secret do merchant) são
 * gravadas na tabela settings (grupo "appmax") e usadas pelo AppmaxService.
 */
class AppmaxInstallController extends Controller
{
    public function health(Request $request): JsonResponse
    {
        // GET: conferência manual/status (não é o health check da Appmax).
        if ($request->isMethod('get')) {
            return response()->json([
                'ok'          => true,
                'app'         => 'APTK Spirits Platform',
                'environment' => config('appmax.environment'),
                'time'        => now()->toIso8601String(),
            ]);
        }

        // POST: health check da instalação. Nunca rejeitar por campo opcional.
        $payload = $request->all();

        Log::info('Appmax health check (instalação) recebido', [
            'app_id'       => $payload['app_id'] ?? null,
            'external_key' => $payload['external_key'] ?? null,
            'tem_client_id'     => filled($payload['client_id'] ?? null),
            'tem_client_secret' => filled($payload['client_secret'] ?? null),
        ]);

        // Guarda as credenciais do merchant quando vierem (grupo "appmax").
        if (filled($payload['client_id'] ?? null)) {
            Setting::set('appmax_merchant_client_id', (string) $payload['client_id'], 'appmax');
        }
        if (filled($payload['client_secret'] ?? null)) {
            Setting::set('appmax_merchant_client_secret', (string) $payload['client_secret'], 'appmax');
        }
        if (filled($payload['app_id'] ?? null)) {
            Setting::set('appmax_app_id', (string) $payload['app_id'], 'appmax');
        }

        // external_id: UUID v4 novo a cada chamada (exigência da doc).
        $externalId = (string) Str::uuid();
        Setting::set('appmax_install_external_id', $externalId, 'appmax');

        return response()->json([
            'external_id' => $externalId,
            'alias'       => 'APTK Spirits',
        ]);
    }
}
