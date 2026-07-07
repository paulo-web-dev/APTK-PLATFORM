<?php

/*
|--------------------------------------------------------------------------
| Appmax — gateway de pagamento (Pix, cartão de crédito e boleto)
|--------------------------------------------------------------------------
| API v3. Autenticação por access-token enviado no corpo de cada requisição.
|
| .env necessário:
|   APPMAX_TOKEN=            (Configurações → Chaves APIs no painel Appmax)
|   APPMAX_ENV=sandbox       (sandbox | production)
|   APPMAX_WEBHOOK_TOKEN=    (string aleatória sua — compõe a URL do webhook)
|
| URL do webhook a cadastrar no painel (Configurações → Apphooks):
|   https://SEU-DOMINIO/webhooks/appmax/{APPMAX_WEBHOOK_TOKEN}
*/

return [

    'token' => env('APPMAX_TOKEN'),

    // sandbox | production
    'environment' => env('APPMAX_ENV', 'sandbox'),

    'base_urls' => [
        'production' => 'https://admin.appmax.com.br/api/v3',
        'sandbox'    => 'https://homolog.sandboxappmax.com.br/api/v3',
    ],

    // Token secreto que compõe a URL do webhook (valida a origem).
    'webhook_token' => env('APPMAX_WEBHOOK_TOKEN'),

    // Descrição na fatura do cartão (máx. 13 caracteres, sem especiais).
    'soft_descriptor' => env('APPMAX_SOFT_DESCRIPTOR', 'APTKSPIRITS'),

    // Validade da chave Pix, em minutos.
    'pix_expiration_minutes' => (int) env('APPMAX_PIX_EXPIRATION', 60),

    // Parcelamento máximo exibido no checkout (juros conforme painel Appmax).
    'max_installments' => (int) env('APPMAX_MAX_INSTALLMENTS', 12),

    'http_timeout' => 30,
];
