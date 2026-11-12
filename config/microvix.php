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
            'producao'  => 'https://webapi.microvix.com.br/api/integracao',
            'aceitacao' => 'https://webapi-aceitacao.microvix.com.br/api/integracao',
        ],
        'entrada' => [
            'producao'  => 'https://webapi.microvix.com.br/1.0/importador.svc',  
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

    'timeout' => (int) env('MICROVIX_TIMEOUT', 60),

    /*
    | Quem manda em preço e estoque do catálogo:
    |   site (padrão) — nada do ERP é gravado; microvix:conciliar roda SEMPRE
    |                   em dry-run, mesmo sem a flag. Admin segue editável.
    |   erp           — a conciliação grava size_stock/size_prices e o admin
    |                   trava esses campos para produto vinculado.
    | O dia da virada é trocar esta linha no .env, não um deploy.
    */
    'fonte_da_verdade' => env('MICROVIX_FONTE_VERDADE', 'site'),

    /*
    | Fase 2 — envio de pedido ao ERP (B2CCadastraPedido e afins).
    | Com a trava desligada (padrão), o MicrovixPedidoSender monta o payload
    | e grava o envelope em microvix_logs (simulated=true), mas NÃO chama o
    | webservice. Ligada, o pedido só vai depois de PAGO (somente_reserva=0,
    | sem reserva no checkout) e só se criado a partir de envio_a_partir_de. A trava vive no service, não no job — vale também pro
    | reenvio manual do painel. Não existe portal de teste (ESTADO-ATUAL
    | §1.7): ligar isso escreve no ERP de verdade.
    */
    'envia_pedido' => (bool) env('MICROVIX_ENVIA_PEDIDO', false),

    /*
    | Data de corte do envio (dd/mm/aaaa, OBRIGATÓRIA para enviar): pedido
    | criado antes dela nunca vai para a Linx (nem job, nem reenvio), não
    | desconta estoque na conciliação e aparece no admin como "não aplicável
    | (anterior à integração)". Vazia ou inválida = nada é enviado.
    */
    'envio_a_partir_de' => env('MICROVIX_ENVIO_A_PARTIR_DE'),

    /*
    | Meio de pagamento (e parcelas, no cartão) → plano_pagamento da Linx
    | (B2CConsultaPlanos). Vazio = sem plano: o pedido não é enviado e fica
    | "manual" ("lançar manualmente na Linx"). Nunca há plano único padrão.
    */
    'planos_pagamento' => [
        'pix'    => env('MICROVIX_PLANO_PIX', 286),
        'boleto' => env('MICROVIX_PLANO_BOLETO'),
        'cartao' => [
            1 => env('MICROVIX_PLANO_CARTAO_1X', 279),
            2 => env('MICROVIX_PLANO_CARTAO_2X', 280),
            3 => env('MICROVIX_PLANO_CARTAO_3X', 281),
        ],
    ],
];
