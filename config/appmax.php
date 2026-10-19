<?php

/*
|--------------------------------------------------------------------------
| Appmax — gateway de pagamento (Pix, cartão de crédito e boleto)
|--------------------------------------------------------------------------
| API v4 com OAuth2 (ver AppmaxService). Modelo em uso: credenciais da
| PRÓPRIA LOJA geradas no painel (menu "API", doc guides/meus-tokens).
|
| .env (produção):
|   APPMAX_CLIENT_ID=             (card da loja no painel → Client ID)
|   APPMAX_CLIENT_SECRET=         (exibido UMA vez ao gerar; nunca logar)
|   APPMAX_AUTH_URL=https://auth.appmax.com.br/oauth2/token
|   APPMAX_API_URL=https://api.appmax.com.br
|   APPMAX_EXTERNAL_ID=           (card da loja → External ID, p/ o Appmax JS)
|   APPMAX_WEBHOOK_TOKEN=         (string aleatória sua — compõe a URL do webhook)
|
| Sem essas variáveis vale o legado (fluxo de instalação de app): URLs por
| APPMAX_ENV e credenciais/external_id gravados em settings pelo health check.
|
| URL do webhook (painel → API → Webhooks):
|   https://SEU-DOMINIO/webhooks/appmax/{APPMAX_WEBHOOK_TOKEN}
*/

return [

    // Credenciais do MERCHANT (painel → API). Têm prioridade sobre as
    // gravadas em settings pelo fluxo de instalação. O fluxo de instalação
    // (legado, fora de uso) também lê este par como credenciais do app.
    'client_id'     => env('APPMAX_CLIENT_ID'),
    'client_secret' => env('APPMAX_CLIENT_SECRET'),

    // Fallback legado das credenciais do merchant (depois das settings).
    'merchant_client_id'     => env('APPMAX_MERCHANT_CLIENT_ID'),
    'merchant_client_secret' => env('APPMAX_MERCHANT_CLIENT_SECRET'),

    // URLs explícitas: prioridade sobre o mapa por ambiente abaixo.
    'auth_url' => env('APPMAX_AUTH_URL'),
    'api_url'  => env('APPMAX_API_URL'),

    // External ID da loja (Appmax JS). Fallback: setting appmax_install_external_id.
    'external_id' => env('APPMAX_EXTERNAL_ID'),

    // sandbox | production — só vale quando APPMAX_AUTH_URL/API_URL estão vazias.
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
    // O ambiente do script segue a URL da API (AppmaxService::scriptUrl).
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

    // Reconciliação de Pix/boleto pendentes (pagamentos:reconciliar, a cada
    // 5 min): consulta GET /v1/orders/{id} dentro da janela, contada da
    // criação do pedido. Fora dela, sem resposta conclusiva, o pedido NÃO é
    // cancelado sozinho: vira "pagamento a conferir" no admin.
    'reconciliacao' => [
        'pix_horas'   => 2,
        'boleto_dias' => 10,   // vencimento + compensação bancária
        // Pix só cancela se a Appmax disser "não pago" depois da expiração
        // + esta folga (pagamento no último segundo ainda propagando).
        'pix_folga_minutos' => 10,
        // Polling da tela de sucesso: no máximo 1 consulta por pedido neste intervalo.
        'polling_segundos' => 30,
    ],

    'http_timeout' => 30,

    // Retry (só token e GET — POST de pedido/pagamento NUNCA, risco de
    // cobrança dupla): tentativas totais e espera entre elas.
    'http_retry_times'    => 3,
    'http_retry_sleep_ms' => 300,
];
