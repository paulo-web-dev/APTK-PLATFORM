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
    | Destino de todos os CTAs de e-mail do site (Collabs, Eventos), com
    | assunto pré-preenchido (PageController::mailto). Lembrar do
    | config:cache em produção.
    */
    'email_contato' => 'contato@aptkspirits.com',

];
