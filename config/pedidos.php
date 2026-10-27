<?php

/*
 * Aviso de pedido novo por e-mail para a equipe (leva 16).
 *
 * notificar: PEDIDOS_NOTIFICAR no .env, lista separada por vírgula. Endereço
 * inválido é descartado (não derruba o envio dos outros).
 * reply_to: MAIL_REPLY_TO no .env. O remetente (MAIL_FROM_ADDRESS) é do
 * domínio validado no SMTP; a resposta da equipe vai pra cá. Vazio = sem
 * reply-to.
 * Mudou → config:cache em produção.
 */
return [

    'notificar' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('PEDIDOS_NOTIFICAR', ''))),
        fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
    )),

    'reply_to' => env('MAIL_REPLY_TO') ?: null,

];
