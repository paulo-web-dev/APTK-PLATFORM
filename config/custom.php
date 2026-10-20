<?php

/*
 | Custom Simples (leva 11): garrafa 375 ml com rótulo personalizado, compra
 | direta pelo configurador de /custom (CustomService valida tudo daqui).
 |
 | - preco: fixo por garrafa, qualquer bebida. O carrinho e o checkout sempre
 |   recalculam daqui; o valor do formulário nunca entra.
 | - volume: a garrafa custom consome o estoque desse volume da bebida e pesa
 |   como ele no frete.
 | - fontes: só fonte que existe em public/fonts/ (a prévia tem que ser a
 |   impressa). `classe` está em aptk-produto.css; `escala` multiplica o
 |   tamanho da bebida (a Amithen desenha menor que a PP Rader).
 | - cores: tinta do rótulo. `token` é a variável de aptk-tokens.css usada na
 |   tela; `hex` é a referência pra impressão. A chave vai pro pedido
 |   (order_items.personalizacao.cor): nunca remover nem renomear chave, só
 |   acrescentar — pedido antigo continua apontando pra ela.
 | - bebidas: elegível = estar aqui (não depende de o produto estar ativo nem
 |   ter preço no 375). Chave = slug do produto. `area` é a parte em branco do
 |   rótulo, em % da imagem (x, y, largura, altura), calibrada por imagem em
 |   2026-10-20; `fonte` é o tamanho do texto em % da largura da imagem.
 */
return [
    'preco'  => 211.00,
    'volume' => '375 ml',
    'limite' => 30,  // caracteres
    'linhas' => 2,   // quebra automática; Enter não é aceito
    'aviso'  => 'As garrafas customizadas não acompanham caixa de presente.',

    'fontes' => [
        'PP Rader' => ['nome' => 'Editorial', 'classe' => 'cst-f-pprader', 'escala' => 1.0],
        'Amithen'  => ['nome' => 'Autoral',   'classe' => 'cst-f-amithen', 'escala' => 1.25],
    ],

    'cores' => [
        'preto' => ['nome' => 'Preto', 'token' => '--color-ink',    'hex' => '#020203'],
        'ambar' => ['nome' => 'Âmbar', 'token' => '--color-scotch', 'hex' => '#BF6300'],
        'cobre' => ['nome' => 'Cobre', 'token' => '--color-copper', 'hex' => '#B87333'],
    ],

    'bebidas' => [
        'negroni-classico'  => ['imagem' => 'img/custom/negroni-classico.jpeg',  'largura' => 423, 'altura' => 413, 'area' => [37.7, 55.2, 26.4, 24.9], 'fonte' => 2.9],
        'boulevardier'      => ['imagem' => 'img/custom/boulevardier.jpeg',      'largura' => 400, 'altura' => 421, 'area' => [39.2, 56.0, 27.9, 23.0], 'fonte' => 3.0],
        'caipirinha-arazul' => ['imagem' => 'img/custom/caipirinha-arazul.jpeg', 'largura' => 440, 'altura' => 410, 'area' => [34.6, 56.3, 25.0, 23.9], 'fonte' => 2.8],
        'dry-martini'       => ['imagem' => 'img/custom/dry-martini.jpeg',       'largura' => 459, 'altura' => 425, 'area' => [39.8, 55.8, 24.3, 23.5], 'fonte' => 2.7],
        'caju-amigo'        => ['imagem' => 'img/custom/caju-amigo.jpeg',        'largura' => 399, 'altura' => 416, 'area' => [37.6, 56.5, 28.6, 23.8], 'fonte' => 3.1],
        'fitzgerald'        => ['imagem' => 'img/custom/fitzgerald.jpeg',        'largura' => 441, 'altura' => 408, 'area' => [37.5, 56.9, 25.2, 24.5], 'fonte' => 2.8],
        'moscow-mule'       => ['imagem' => 'img/custom/moscow-mule.jpeg',       'largura' => 423, 'altura' => 418, 'area' => [38.9, 56.0, 26.4, 24.0], 'fonte' => 2.9],
        'cosmopolitan'      => ['imagem' => 'img/custom/cosmopolitan.jpeg',      'largura' => 455, 'altura' => 415, 'area' => [37.7, 57.1, 24.7, 23.9], 'fonte' => 2.7],
        'sur-lorange'       => ['imagem' => 'img/custom/sur-lorange.jpeg',       'largura' => 423, 'altura' => 421, 'area' => [39.2, 57.7, 26.8, 24.0], 'fonte' => 2.9],
    ],
];
