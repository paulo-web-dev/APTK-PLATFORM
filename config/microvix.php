<?php

/*
|--------------------------------------------------------------------------
| Linx Microvix — WS B2C (ERP)
|--------------------------------------------------------------------------
| Dois webservices com protocolos diferentes:
|   - Saída  (consultas B2CConsulta*): POST de XML puro no envelope <LinxMicrovix>
|   - Entrada (cadastros B2CCadastra*): SOAP no Importador.svc
|
| Doc: docs/linx-microvix-ws-b2c.md (conferir nomes de campo SEMPRE contra o
| PDF original docs/linx-microvix-ws-b2c-original.pdf — o .md é conversão).
|
| Os parâmetros de faturamento (empresa, vendedor, depósito, tabela de preço,
| status inicial, tipo de encomenda e plano) são descobertos via:
|   php artisan microvix:descobrir
*/

return [

    // aceitacao | producao
    'environment' => env('MICROVIX_ENV', 'aceitacao'),

    'urls' => [
        'saida' => [
            'producao'  => 'http://webapi.microvix.com.br/api/integracao',
            'aceitacao' => 'https://webapi-aceitacao.microvix.com.br/api/integracao',
        ],
        'entrada' => [
            'producao'  => 'http://webapi.microvix.com.br/1.0/importador.svc',
            'aceitacao' => 'https://webapi-aceitacao.microvix.com.br/Importador.svc',
        ],
    ],

    // ---- Credenciais (item 3 da doc) ----
    'id_portal' => env('MICROVIX_ID_PORTAL'),
    'cnpj_emp'  => env('MICROVIX_CNPJ_EMP'),
    'user'      => env('MICROVIX_USER'),
    'password'  => env('MICROVIX_PASSWORD'),
    'chave'     => env('MICROVIX_CHAVE'),

    // ---- Parâmetros de faturamento (descobertos via microvix:descobrir) ----
    'empresa'            => env('MICROVIX_EMPRESA'),
    'cod_vendedor'       => env('MICROVIX_COD_VENDEDOR'),
    'id_deposito'        => env('MICROVIX_ID_DEPOSITO'),
    'id_tabela_preco'    => env('MICROVIX_ID_TABELA_PRECO'),
    'id_status_inicial'  => env('MICROVIX_ID_STATUS_INICIAL'),
    'id_tipo_encomenda'  => env('MICROVIX_ID_TIPO_ENCOMENDA'),
    'plano_pagamento'    => env('MICROVIX_PLANO_PAGAMENTO'),

    'timeout' => 60,

    /*
    | Quem manda em preço e estoque do catálogo:
    |   site (padrão) — nada do ERP é gravado; microvix:conciliar roda SEMPRE
    |                   em dry-run, mesmo sem a flag. Admin segue editável.
    |   erp           — a conciliação grava size_stock/size_prices e o admin
    |                   trava esses campos para produto vinculado.
    | O dia da virada é trocar esta linha no .env, não um deploy.
    */
    'fonte_da_verdade' => env('MICROVIX_FONTE_VERDADE', 'site'),
];
