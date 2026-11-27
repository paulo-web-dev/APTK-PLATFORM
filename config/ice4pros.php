<?php

/*
|--------------------------------------------------------------------------
| Ice4Pros: conteúdo editorial (leva 21)
|--------------------------------------------------------------------------
| Portfólio (/ice4pros/produtos e ice4pros.com.br/produtos) e clientes da
| LP. Fotos em public/img/marcas/ice4pros/fotos/ (caminho relativo a ela).
| Nomes e dados dos cards vieram das referências do briefing
| (docs/ice4pros-ref/02-portfolio-layout-ref-*.png); o briefing chama os
| formatos de SUPER 30, CUBO 50, CUBO 50X65, RETANGULAR, ESFERA 2 e
| DIAMANTE 2 — se a marca preferir esses, trocar só o 'nome' aqui.
| Títulos ficam em caixa de frase: o CSS da marca põe em caixa alta.
| Lembrar do config:cache em produção.
*/

return [

    // Bloco 1 do portfólio: formatos de gelo, na ordem do briefing.
    'formatos' => [
        'super30' => [
            'nome'   => 'Super30',
            'foto'   => 'super30.jpg',
            'medida' => '30 × 30 mm',
            'nota'   => 'Volume e frescor',
            'drinks' => 'Ideais para Caipirinha',
            'pack'   => 'Pack: 150 cubos',
        ],
        'cinquenta' => [
            'nome'   => 'Cinquenta',
            'foto'   => 'cubo50.jpg',
            'medida' => '50 × 50 mm',
            'nota'   => 'Presença no copo',
            'drinks' => 'Negroni · Old Fashioned · Penicillin',
            'pack'   => 'Pack: 35 cubos',
        ],
        '50x65' => [
            'nome'   => '50 × 65',
            'foto'   => 'cubo50x65.jpg',
            'medida' => '50 × 65 mm',
            'nota'   => 'Baixa diluição',
            'drinks' => 'Godfather · Whiskies Premium · Fitzgerald',
            'pack'   => 'Pack: 26 cubos',
        ],
        'collins' => [
            'nome'   => 'Collins',
            'foto'   => 'retangular.jpg',
            'medida' => '30 × 30 × 130 mm',
            'nota'   => 'Arquitetura para long drinks',
            'drinks' => 'Paloma · Highball · Tom Collins',
            'pack'   => 'Pack: 16 cubos',
        ],
        'esfera' => [
            'nome'   => 'Esfera',
            'foto'   => 'esfera2.jpg',
            'medida' => '55 mm',
            'nota'   => 'Ritmo lento e elegância',
            'drinks' => 'Whisky on the Rocks · Godfather · Rusty Nail',
            'pack'   => 'Pack: 20 cubos',
        ],
        'diamante' => [
            'nome'   => 'Diamante',
            'foto'   => 'diamante2.jpg',
            'medida' => '54 mm',
            'nota'   => 'Impacto cênico',
            'drinks' => 'Manhattan · Daiquiri · Aviation · Sidecar',
            'pack'   => 'Pack: 20 cubos',
        ],
    ],

    // Bloco 2 do portfólio: Custom Ice (referência 02-portfolio-layout-ref-c).
    'custom' => [
        'id-no-copo' => [
            'nome'  => 'Identidade no copo',
            'foto'  => 'id-no-copo.jpg',
            'texto' => 'Logos, gravações e marcadores',
        ],
        'elementos-afetivos' => [
            'nome'  => 'Elementos afetivos',
            'foto'  => 'elementos-afetivos.jpg',
            'texto' => 'Blocos e experiências cenográficas. Ideais para celebrações, casamentos e collabs',
        ],
        'acabamentos-premium' => [
            'nome'  => 'Acabamentos premium',
            'foto'  => 'acabamentos-premium.jpg',
            'texto' => 'Sofisticação, alto valor percebido, hotelaria e ocasiões premium',
        ],
    ],

    // Principais clientes da LP, na ordem da referência 06 (duas fileiras de
    // cinco). 'foto' = foto de coquetel do parceiro em fotos/clientes/ (3:4);
    // null = placeholder "Foto do drink" até a marca enviar.
    'clientes' => [
        ['nome' => 'Copacabana Palace',  'foto' => 'clientes/copacabana-palace.jpg'],
        ['nome' => 'Fasano',             'foto' => 'clientes/fasano.jpg'],
        ['nome' => 'Emiliano',           'foto' => null],
        ['nome' => 'Hotel Unique',       'foto' => null],
        ['nome' => 'Casa Camolese',      'foto' => null],
        ['nome' => 'Nosso Coquetelaria', 'foto' => null],
        ['nome' => 'Guilhotina',         'foto' => null],
        ['nome' => 'Astor',              'foto' => null],
        ['nome' => 'Gurumê',             'foto' => null],
        ['nome' => 'Pirajá',             'foto' => null],
    ],

];
