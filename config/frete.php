<?php

/*
 | Frete estimado (leva 08) — envio próprio a partir de São Paulo capital.
 | Valor = tabela de referência dos Correios + margem; sem transportadora
 | integrada. Quando a SuperFrete entrar, só o FreteService muda: o formato
 | de resposta ({opcoes: [{nome, valor, prazo_dias}]}) é o mesmo.
 */

return [

    // Peso de cada item = ml do volume / 1000 (kg) + embalagem.
    // 'item' soma a embalagem por unidade; 'pedido' soma uma vez só.
    'embalagem_kg'  => 1.0,
    'embalagem_por' => 'item',

    // Volume sem ml legível ("Kit", "Caixa"...): este peso no lugar dos ml
    // (a embalagem continua somando por cima).
    'peso_padrao_kg' => 1.0,

    // Margem sobre a tabela (envio próprio).
    'margem' => 0.20,

    // Dias úteis de separação/embalagem somados ao prazo da tabela.
    'dias_manuseio' => 2,

    // Opções devolvidas, na ordem de exibição: código (vai em
    // orders.shipping_method) => nome mostrado ao cliente.
    'servicos' => [
        'pac'   => 'PAC',
        'sedex' => 'SEDEX',
    ],

    // Produtos (slugs) com frete zero — só o de teste de pagamento
    // (catalogo:produto-teste). Vale só se TODO o carrinho for isento: aí a
    // única opção é "teste" (R$ 0,00). Qualquer item não isento e o frete
    // normal vale pro carrinho inteiro, peso de tudo, sem desconto.
    'isentos' => ['teste'],

    // Tabela por região: base até 1 kg + adicional por kg extra (R$);
    // prazo em dias úteis, sem o manuseio.
    'tabela' => [
        'capital'     => ['pac' => [22, 3, 3],  'sedex' => [28, 4, 1]],
        'sp_interior' => ['pac' => [28, 5, 5],  'sedex' => [40, 8, 2]],
        'zona2'       => ['pac' => [35, 7, 7],  'sedex' => [60, 12, 3]],
        'zona3'       => ['pac' => [45, 10, 9], 'sedex' => [85, 18, 4]],
        'nordeste'    => ['pac' => [60, 14, 12], 'sedex' => [110, 25, 6]],
        'norte'       => ['pac' => [75, 18, 15], 'sedex' => [140, 32, 8]],
    ],

    // SP se divide por faixa de CEP (01000–09999 = capital, inclui Grande SP).
    'regioes_sp' => [
        'capital'     => ['01000', '09999'],
        'sp_interior' => ['10000', '19999'],
    ],

    // Demais UFs → região.
    'regioes_uf' => [
        'zona2'    => ['RJ', 'ES', 'MG', 'PR', 'SC'],
        'zona3'    => ['RS', 'DF', 'GO', 'MS', 'MT'],
        'nordeste' => ['BA', 'SE', 'PE', 'AL', 'PB', 'RN', 'CE', 'PI', 'MA'],
        'norte'    => ['PA', 'AP', 'AM', 'RR', 'AC', 'RO', 'TO'],
    ],

    // Faixas de CEP por UF (5 primeiros dígitos, inclusivo).
    'faixas_uf' => [
        ['01000', '19999', 'SP'],
        ['20000', '28999', 'RJ'],
        ['29000', '29999', 'ES'],
        ['30000', '39999', 'MG'],
        ['40000', '48999', 'BA'],
        ['49000', '49999', 'SE'],
        ['50000', '56999', 'PE'],
        ['57000', '57999', 'AL'],
        ['58000', '58999', 'PB'],
        ['59000', '59999', 'RN'],
        ['60000', '63999', 'CE'],
        ['64000', '64999', 'PI'],
        ['65000', '65999', 'MA'],
        ['66000', '68899', 'PA'],
        ['68900', '68999', 'AP'],
        ['69000', '69299', 'AM'],
        ['69300', '69399', 'RR'],
        ['69400', '69899', 'AM'],
        ['69900', '69999', 'AC'],
        ['70000', '72799', 'DF'],
        ['72800', '72999', 'GO'],
        ['73000', '73699', 'DF'],
        ['73700', '76799', 'GO'],
        ['76800', '76999', 'RO'],
        ['77000', '77999', 'TO'],
        ['78000', '78899', 'MT'],
        ['79000', '79999', 'MS'],
        ['80000', '87999', 'PR'],
        ['88000', '89999', 'SC'],
        ['90000', '99999', 'RS'],
    ],

];
