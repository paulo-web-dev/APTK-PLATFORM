<?php

/*
|--------------------------------------------------------------------------
| Appmax — gateway de pagamento (Pix, cartão de crédito e boleto)
|--------------------------------------------------------------------------
| API v4, fluxo de aplicativo com OAuth2 (ver AppmaxService).
|
| .env:
|   APPMAX_ENV=sandbox            (sandbox | production — troca auth, API e script JS)
|   APPMAX_CLIENT_ID=             (credenciais do APLICATIVO, painel do desenvolvedor)
|   APPMAX_CLIENT_SECRET=
|   APPMAX_WEBHOOK_TOKEN=         (string aleatória sua — compõe a URL do webhook)
|
| URL do webhook a cadastrar no app:
|   https://SEU-DOMINIO/webhooks/appmax/{APPMAX_WEBHOOK_TOKEN}
*/

return [

    // Credenciais do APLICATIVO (painel do desenvolvedor). Usadas no fluxo
    // de instalação; as credenciais do MERCHANT (que transacionam) chegam
    // pelo health check e ficam na tabela settings (grupo "appmax"),
    // com fallback opcional nas envs abaixo.
    'client_id'     => env('APPMAX_CLIENT_ID'),
    'client_secret' => env('APPMAX_CLIENT_SECRET'),

    'merchant_client_id'     => env('APPMAX_MERCHANT_CLIENT_ID'),
    'merchant_client_secret' => env('APPMAX_MERCHANT_CLIENT_SECRET'),

    // sandbox | production
    'environment' => env('APPMAX_ENV', 'sandbox'),

    'auth_urls' => [
        'production' => 'https://auth.appmax.com.br/oauth2/token',
        'sandbox'    => 'https://auth.sandboxappmax.com.br/oauth2/token',
    ],
    'api_urls' => [
        'production' => 'https://api.appmax.com.br',
        'sandbox'    => 'https://api.sandboxappmax.com.br',
    ],

    // Appmax JS: cada ambiente tem o SEU build (a URL da API vai embutida no
    // bundle). Script de um ambiente com external_id do outro = tokenização
    // contra a API errada (404 Merchant not found) — doc "Ambientes".
    'script_urls' => [
        'production' => 'https://scripts.appmax.com.br/appmax.min.js',
        'sandbox'    => 'https://scripts.sandboxappmax.com.br/appmax.min.js',
    ],

    // Token secreto que compõe a URL do webhook (valida a origem).
    'webhook_token' => env('APPMAX_WEBHOOK_TOKEN'),

    // Descrição na fatura do cartão (máx. 13 caracteres, sem especiais).
    'soft_descriptor' => env('APPMAX_SOFT_DESCRIPTOR', 'APTKSPIRITS'),

    // Validade da chave Pix, em minutos (vai em payment_data.pix.expiration_date).
    'pix_expiration_minutes' => (int) env('APPMAX_PIX_EXPIRATION', 60),

    // Parcelamento SEM juros (a loja absorve): até N parcelas, nenhuma abaixo
    // do valor mínimo. A Appmax não calcula juros — cobra o total enviado.
    'max_installments'      => (int) env('APPMAX_MAX_INSTALLMENTS', 3),
    'min_installment_value' => (float) env('APPMAX_MIN_INSTALLMENT_VALUE', 30.00),

    'http_timeout' => 30,
];
