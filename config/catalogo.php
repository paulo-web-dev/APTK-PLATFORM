<?php

/*
 * Catálogo da loja.
 *
 * volumes_desativados: volume que saiu da venda em todos os produtos. O
 * catalogo:importar-planilha ignora as linhas do CSV com esse volume (as
 * linhas continuam na planilha: tirar daqui e importar de novo traz o
 * volume de volta) e o admin não aceita adicioná-lo a um produto. Quem já
 * tem o volume (o produto "Teste" de pagamento, 100 ml) não é afetado.
 * O 100 ml saiu na leva 15 (migration 2026_10_27_100000).
 * Mudou → config:cache em produção.
 */
return [

    'volumes_desativados' => ['100 ml'],

];
