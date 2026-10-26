<?php

/*
|--------------------------------------------------------------------------
| Portfólio de collabs (leva 14)
|--------------------------------------------------------------------------
| Grade de marcas que já brindaram com a APTK, nesta ordem, em /collabs e no
| portfólio de /eventos (<x-portfolio-grid>). Só entra item com 'ativo' =>
| true E foto. Foto recortada em 4:5 em public/img/aptk/collab-*.jpg.
| Parceria sem foto fica aqui com 'ativo' => false: volta com uma linha
| quando a foto chegar. Lembrar do config:cache em produção.
*/

return [

    'marcas' => [
        ['nome' => 'Montblanc',                   'imagem' => 'img/aptk/collab-montblanc.jpg',     'ativo' => true],
        ['nome' => 'Swarovski',                   'imagem' => 'img/aptk/collab-swarovski.jpg',     'ativo' => true],
        ['nome' => 'Vivara',                      'imagem' => 'img/aptk/collab-vivara.jpg',        'ativo' => true],
        ['nome' => 'Iguatemi',                    'imagem' => 'img/aptk/collab-iguatemi.jpg',      'ativo' => true],
        ['nome' => 'Emiliano',                    'imagem' => 'img/aptk/collab-emiliano.jpg',      'ativo' => true],
        ['nome' => 'Unique',                      'imagem' => 'img/aptk/collab-unique.jpg',        'ativo' => true],
        // Foto associada ao Rosewood pela gestão (garrafa "Rabo di Galo"): confirmar.
        ['nome' => 'Rosewood',                    'imagem' => 'img/aptk/collab-rosewood.jpg',      'ativo' => true],
        ['nome' => 'Banco Inter (Orange Nation)', 'imagem' => 'img/aptk/collab-orange-nation.jpg', 'ativo' => true],
        ['nome' => 'Pirajá',                      'imagem' => 'img/aptk/collab-piraja.jpg',        'ativo' => true],
        ['nome' => 'Braz',                        'imagem' => 'img/aptk/collab-braz.jpg',          'ativo' => true],
        ['nome' => 'Il Barista',                  'imagem' => 'img/aptk/collab-il-barista.jpg',    'ativo' => true],
        ['nome' => 'Freixenet',                   'imagem' => 'img/aptk/collab-freixenet.jpg',     'ativo' => true],

        // Aguardando foto (estavam no carrossel antigo de /collabs).
        ['nome' => 'Selvagem', 'imagem' => null, 'ativo' => false],
        ['nome' => 'Gurumê',   'imagem' => null, 'ativo' => false],
        ['nome' => 'Camolese', 'imagem' => null, 'ativo' => false],
        ['nome' => 'Barbacoa', 'imagem' => null, 'ativo' => false],
    ],

];
