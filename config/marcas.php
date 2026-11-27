<?php

/*
|--------------------------------------------------------------------------
| Domínios próprios das marcas da holding (vitrine isolada)
|--------------------------------------------------------------------------
| Cada marca com domínio próprio ganha uma entrada aqui. O middleware
| MarcaHost resolve a marca pelo Host da requisição: na raiz serve a view
| com o layout isolado (layouts.marca); qualquer outro caminho leva 301
| para o mesmo caminho no domínio da APTK (config app.url), exceto assets
| e infra (lista de passthrough no middleware).
|
| 'hosts' inclui o host de dev *.localhost: resolve para 127.0.0.1 sem
| editar o arquivo hosts (RFC 6761), então http://ice4pros.localhost:8000
| funciona direto com o artisan serve. A comparação ignora a porta.
|
| BARIN entra depois só acrescentando uma entrada (e o @extends na view).
*/

return [

    'marcas' => [

        'ice4pros' => [
            'nome'  => 'Ice4Pros',
            'view'  => 'shop.ice4pros',
            'url'   => 'https://ice4pros.com.br',
            'hosts' => ['ice4pros.com.br', 'www.ice4pros.com.br', 'ice4pros.localhost'],

            // Leva 21: páginas além da raiz servidas no domínio da marca
            // (caminho => view). O resto leva 301 pra APTK. No domínio da
            // APTK a mesma view sai em /ice4pros/{caminho} (marca_url()).
            'paginas' => [
                'produtos' => 'shop.ice4pros-produtos',
            ],

            // Leva 21: WhatsApp comercial próprio (só dígitos, com DDI) e a
            // mensagem de todos os CTAs comerciais das páginas da marca.
            'whatsapp' => preg_replace('/\D+/', '', (string) env('ICE4PROS_WHATSAPP', '5511917248795')),
            'whatsapp_mensagem' => 'Olá! Conheci a Ice4Pros através do site e gostaria de receber mais informações sobre o portfólio e as soluções disponíveis.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Link de cada marca nos cards do ecossistema (Quem Somos, leva 10)
    |--------------------------------------------------------------------------
    | O briefing pede o Instagram de cada marca. BARIN e Ice4Pros ainda não
    | mandaram o perfil: até lá apontam pra LP / site. Trocar aqui (url +
    | label) quando chegar. Caminho relativo ('/barin') abre na mesma aba;
    | URL completa abre em aba nova. Lembrar do config:cache em produção.
    */
    'links' => [
        'aptk'     => ['url' => 'https://www.instagram.com/aptkspirits/', 'label' => '@aptkspirits'],
        'barin'    => ['url' => '/barin', 'label' => 'Conhecer a BARIN'],
        'ice4pros' => ['url' => 'https://ice4pros.com.br', 'label' => 'Conhecer a Ice4Pros'],
    ],

    /*
    |--------------------------------------------------------------------------
    | E-mail de contato (leva 14)
    |--------------------------------------------------------------------------
    | Leva 20: só texto copiável nos rodapés (public, marca, Ice4Pros).
    | Nenhum botão usa mailto: (no Windows abria o Outlook); contato
    | comercial é sempre WhatsApp. Lembrar do config:cache em produção.
    */
    'email_contato' => 'contato@aptkspirits.com',

    /*
    |--------------------------------------------------------------------------
    | WhatsApp da APTK
    |--------------------------------------------------------------------------
    | Número de todos os links de WhatsApp das páginas da APTK (e da BARIN):
    | só dígitos, com DDI e DDD. Link sempre via whatsapp_link(); número
    | escrito via whatsapp_formatado(). Marca com número próprio (Ice4Pros,
    | leva 21) tem 'whatsapp' na entrada dela em 'marcas' e passa o slug:
    | whatsapp_link($msg, 'ice4pros'). Lembrar do config:cache em produção.
    */
    'whatsapp' => preg_replace('/\D+/', '', (string) env('APTK_WHATSAPP', '5511934298435')),

];
