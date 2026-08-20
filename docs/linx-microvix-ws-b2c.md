# Especificação — Web Service B2C Linx Microvix Padrão

> **Cliente/Grupo:** APTK SPIRITS MATRIZ - WS B2C
> **Área:** Arquitetura
> **Fonte:** Documentação_Integração_WS_B2C_-_APTK_SPIRITS_MATRIZ.pdf (Linx Sistemas)
> **Última revisão do doc original:** 58 — 21/08/2025

---

## Índice

1. [Introdução](#1-introdução)
2. [Especificações](#2-especificações)
3. [Credenciais de acesso](#3-credenciais-de-acesso)
4. [Orientações gerais](#4-orientações-gerais)
5. [Fluxogramas de integração](#5-fluxogramas-de-integração)
6. [Layout e descrição dos campos](#6-layout-e-descrição-dos-campos)
   - [6.1 Web Service Saída](#61-web-service-saída)
   - [6.2 Web Service Entrada](#62-web-service-entrada)
7. [Definições de STATUS e SITUAÇÃO](#7-definições-dos-campos-status-e-situação)
8. [Exemplos de envelope XML](#8-exemplos)
9. [Restrições](#9-restrições)
10. [Benefícios](#10-benefícios)
11. [Documentação dos métodos](#11-documentação-dos-métodos)
12. [Entrega / Suporte](#12-entrega--suporte)
13. [Ativação e condição comercial](#13-ativação-e-condição-comercial)
14. [Controle de revisão](#18-controle-de-revisão)

---

## 1 Introdução

O WebService B2C Linx Microvix padrão é uma oferta que visa facilitar a integração com plataformas de e-commerce de sistemas terceiros.

O layout é **único e padrão**, não recebe customizações — somente melhorias, que são aplicadas a todos os clientes que utilizam o serviço.

## 2 Especificações

### 2.1 Cenário atual
WebService B2C Linx Microvix padrão.

### 2.2 Escopo do WebService
Os métodos são padrões no WS B2C da Linx Microvix. É possível **buscar registros** da base de um Portal e **incluir informações** de pedidos e clientes nessa base.

---

## 3 Credenciais de acesso

| Item | Valor |
|---|---|
| WS de **Saída** — Produção | `http://webapi.microvix.com.br/api/integracao` |
| WS de **Saída** — Teste | `https://webapi-aceitacao.microvix.com.br/api/integracao` |
| WS de **Entrada** — Produção | `http://webapi.microvix.com.br/1.0/importador.svc` |
| WS de **Entrada** — Teste | `https://webapi-aceitacao.microvix.com.br/Importador.svc` |
| Usuário | `linx_b2c` |
| Senha | `linx_b2c` |
| Chave | `a247a10f-c1a8-4706-b68f-e3910fd449a4` |
| Nome do grupo | `APTK SPIRITS MATRIZ - WS B2C` |

### Headers (somente métodos de **entrada**)

```
SOAPAction: http://tempuri.org/IImportador/Importar
Content-Type: text/xml;charset=UTF-8
Accept: gzip,deflate
Accept: text/xml
```

### 3.1 Migração Base Stage → Web Service B2C

Se o portal usa a integração antiga (base stage), ao ativar a nova integração B2C via WebService em produção **a antiga será desligada**. Não há viabilidade técnica de manter as duas em paralelo.

---

## 4 Orientações gerais

### 4.1 Timestamps

Forma correta de consumo:

1. Consulta inicial com `timestamp = 0` (carga total do passado da loja);
2. Popula a base local;
3. Nas consultas seguintes, informa o **maior timestamp** retornado na consulta anterior;
4. A nova consulta retorna apenas registros com timestamp **maior** que o informado — ou seja, só o que foi alterado ou adicionado (carga incremental).

**Exemplo:**

```
produto_1 criado:    8146561520
produto_2 criado:    8146561521
produto_2 alterado:  8146561522
produto_1 alterado:  8146561523
produto_3 criado:    8146561524
```

Consultando `B2CConsultaProduto` com timestamp `8146561522` → retorna apenas **produto_1** e **produto_3**.

> Se nenhum evento ocorreu, a resposta vem **vazia**.

#### ⚠️ Ponto crítico

> Os timestamps do Linx Microvix **NÃO são UNIX**. São do tipo `TIMESTAMP` do SQL Server: **incrementais**, um contador relativo à vida do banco. **Não representam tempo.**

- Cada timestamp é único no banco; cada operação incrementa uma unidade.
- No WebService, o timestamp é **por método**.
- Métodos que fazem JOIN entre tabelas retornam o **maior timestamp** das tabelas relacionadas àquele método. Ex.: `LinxPedidosVenda` junta `empresa`, `clientes_fornecedores` e `orçamento`.

**Armadilha comum:**

```
1. Cadastra cliente → timestamp 8137625972
2. Cadastra pedido  → timestamp 8137625973
3. Altera endereço do cliente no CRM → timestamp 8137625974
4. Consulta usando 8137625974 → RETORNA VAZIO
   (só retorna resultados MAIORES que o informado)
   ✅ O correto seria consultar com 8137625972
```

### 4.2 Acesso ao ERP da loja

O integrador precisa ter acesso ao ERP, liberado pelo responsável pela loja. A Linx **não se responsabiliza** pelo envio de qualquer informação presente no ERP do cliente.

---

## 5 Fluxogramas de integração

### 5.1 Integração de Pedido

1. Pedido é criado na plataforma de e-commerce;
2. Antes de integrar, validar se o pedido já foi integrado via `B2CConsultaPedidos`;
3. Integrar o novo pedido usando os métodos de entrada **nesta sequência**:

```
B2CCadastraClientes
  → B2CCadastraClientesContatos      (opcional)
  → B2CCadastraEnderecosEntrega      (opcional)
  → B2CCadastraPedido
  → B2CCadastraPedidoPlanos
  → B2CCadastraPedidoItens
```

4. Após a integração, consultar o pedido pelos métodos de saída (`B2CConsultaPedidos`, `B2CConsultaPedidosStatus`).

### 5.2 Atualização de Pedido

| Situação | Método |
|---|---|
| Status do pedido alterado no e-commerce | `B2CAtualizaPedidosStatus` |
| Pedido possui código de rastreio | `B2CAtualizaCodigoRastreio` |
| Pedido cancelado no e-commerce | `B2CCancelaPedido` |

---

## 6 Layout e descrição dos campos

### 6.1 Web Service Saída

#### B2CConsultaEmpresas

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| empresa | Sim | INT | Código da empresa no MicrovixERP | 1 |
| nome_emp | Não | VARCHAR(50) | Nome da empresa | '01 – LOJA SHOPPING FANTASIA' |
| cnpjEmp | Não | VARCHAR(14) | CNPJ da empresa | '12345678912345' |
| end_unidade | Não | VARCHAR(250) | Endereço | 'Avenida das alamedas nº 467' |
| complemento_end_unidade | Não | VARCHAR(60) | Complemento | 'Fundos' |
| nr_rua_unidade | Não | VARCHAR(20) | Número | '467' |
| bairro_unidade | Não | VARCHAR(60) | Bairro | 'Boa Vista' |
| cep_unidade | Não | VARCHAR(9) | CEP | '89234-091' |
| cidade_unidade | Não | VARCHAR(50) | Cidade | 'Joinville' |
| uf_unidade | Não | CHAR(2) | Estado | 'SC' |
| email_unidade | Não | VARCHAR(50) | E-mail | 'empresa@empresa.com.br' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| data_criacao | Sim | SMALLDATETIME | Data de criação | 2012-08-09 11:37:00 |
| centro_distribuicao | Sim | BIT | 0 = não é CD / 1 = é CD | 0 |
| portal | Sim | INT | Número do portal | 8266 |
| envia_produtos_b2c | Sim | BIT | Envia produtos ao Commerce Linx | 0 |
| envia_estoque_b2c | Sim | BIT | Envia estoque ao Commerce Linx | 0 |
| integracao_oms | Não | BIT | Integração OMS Linx | 0 |

**Parâmetros de consulta:** `chave` (Sim, UNIQUEIDENTIFIER), `cnpjEmp` (Sim, VARCHAR(14)), `timestamp` (Sim, BIGINT)

---

#### B2CConsultaFormasPagamento

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| cod_forma_pgto | Sim | INT | Código da forma de pagamento | 1 |
| forma_pgto | Sim | VARCHAR(50) | Descrição | 'Dinheiro' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave`, `cnpjEmp`, `timestamp` (todos obrigatórios)

---

#### B2CConsultaPlanos

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| plano | Sim | INT | Código do plano de pagamento | 1 |
| nome_plano | Sim | VARCHAR(100) | Nome do plano | '30 vezes' |
| forma_pagamento | Sim | INT | Forma de pagamento padrão do plano | 1 |
| qtde_parcelas | Sim | INT | Quantidade de parcelas | 5 |
| valor_minimo_parcela | Não | MONEY | Valor mínimo de cada parcela | 149.99 |
| indice | Não | MONEY | Índice de acréscimo/desconto | 0,95 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| desativado | Sim | CHAR(1) | 'S' ou 'N' | 'S' |
| tipo_plano | Sim | CHAR(1) | 'V' venda ou 'C' compra | 'V' |
| portal | Sim | INT | Número do portal | 8266 |
| usa_tef | Sim | CHAR(1) | Indica se usa TEF | N |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `plano` (Não, INT), `timestamp` (Sim), `tipo_plano` (Não, CHAR(1))

---

#### B2CConsultaPlanosParcelas

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| plano | Sim | INT | Código do plano de pagamento | 1 |
| ordem_parcela | Sim | INT | Ordem de pagamento de cada parcela | 5 |
| prazo_parc | Sim | INT | Prazo entre parcelas (dias) | 30 |
| id_planos_parcelas | Sim | INT | Código da parcela do plano | 18 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `plano` (Não), `timestamp` (Sim)

---

#### B2CConsultaSetores

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| codigo_setor | Sim | INT | Código do setor | 3 |
| nome_setor | Sim | VARCHAR(100) | Descrição do setor | 'BLUSAS' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigo_setor` (Não), `timestamp` (Sim)

---

#### B2CConsultaLinhas

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| codigo_linha | Sim | INT | Código da linha | 12 |
| nome_linha | Sim | VARCHAR(30) | Descrição da linha | 'Infantil' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| setores | Não | VARCHAR(250) | Códigos dos setores relacionados, separados por vírgula | 1,2,3,4 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigo_linha` (Não), `timestamp` (Sim)

---

#### B2CConsultaMarcas

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| codigo_marca | Sim | INT | Código da marca | 1 |
| nome_marca | Sim | VARCHAR(100) | Descrição da marca | 'FAKE' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| linhas | Não | VARCHAR(250) | Códigos relacionados, separados por vírgula | 1,2,3,4 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigo_marca` (Não), `timestamp` (Sim)

---

#### B2CConsultaColecoes

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| codigo_colecao | Sim | INT | Código da coleção | 8 |
| nome_colecao | Sim | VARCHAR(100) | Descrição da coleção | 'INVERNO 2012' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| marcas | Não | VARCHAR(250) | Códigos das marcas relacionadas | 1,2,3,4 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigo_colecao` (Não), `timestamp` (Sim)

---

#### B2CConsultaEspessuras

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| codigo_espessura | Sim | INT | Código da espessura | 22 |
| nome_espessura | Sim | VARCHAR(100) | Descrição da espessura | 'FIO' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigo_espessura` (Não), `timestamp` (Sim)

---

#### B2CConsultaGrade1 (tamanho)

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| codigo_grade1 | Sim | INT | Código do tamanho | 7 |
| nome_grade1 | Sim | VARCHAR(100) | Descrição do tamanho | 'P' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigo_grade1` (Não), `timestamp` (Sim)

---

#### B2CConsultaGrade2 (cor)

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| codigo_grade2 | Sim | INT | Código da cor | 3 |
| nome_grade2 | Sim | VARCHAR(100) | Descrição da cor | 'AZUL' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigo_grade2` (Não), `timestamp` (Sim)

---

#### B2CConsultaUnidade

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| unidade | Sim | VARCHAR(50) | Descrição da unidade | 'PC' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `unidade` (Não, INT), `timestamp` (Sim)

---

#### B2CConsultaClassificacao

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| codigo_classificacao | Sim | INT | Código da classificação | 2 |
| nome_classificacao | Sim | VARCHAR(50) | Nome da classificação | 'LEGAL' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigo_classificacao` (Não), `timestamp` (Sim)

---

#### B2CConsultaProdutos

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| codigoproduto | Sim | BIGINT | Código do produto no MicrovixERP (SKU) | 12837 |
| referencia | Não | VARCHAR(20) | Código de referência do produto agrupado | '1403EF' |
| codauxiliar1 | Não | VARCHAR(40) | Código auxiliar do produto | 'ant.0012' |
| descricao_basica | Não | VARCHAR(100) | Descrição resumida | 'Bota MX' |
| nome_produto | Não | VARCHAR(250) | Descrição completa | 'Bota MX 1403EF verde 37' |
| peso_liquido | Não | FLOAT | Peso líquido | 0.35 |
| codigo_setor | Não | INT | Código do setor | 5 |
| codigo_linha | Não | INT | Código da linha | 3 |
| codigo_marca | Não | INT | Código da marca | 2 |
| codigo_colecao | Não | INT | Código da coleção | 10 |
| codigo_espessura | Não | INT | Código da espessura | 1 |
| codigo_grade1 | Não | INT | Código do tamanho | 7 |
| codigo_grade2 | Não | INT | Código da cor | 9 |
| unidade | Não | VARCHAR(50) | Código da unidade | 'CX' |
| ativo | Não | BIT | Indica se o produto deve aparecer na loja virtual | 1 |
| codigo_classificacao | Não | INT | Código da classificação | 3 |
| dt_cadastro | Não | SMALLDATETIME | Data de cadastro no ERP | 2014-01-01 |
| observacao | Não | VARCHAR(MAX) | Observações | 'Produto para demonstração' |
| cod_fornecedor | Sim | INT | Código do fornecedor | 3 |
| dt_update | Não | SMALLDATETIME | Data de atualização do cadastro | '2014-01-01' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| altura_para_frete | Não | FLOAT | Altura para frete | 1.28 |
| largura_para_frete | Não | FLOAT | Largura para frete | 0.90 |
| comprimento_para_frete | Não | FLOAT | Comprimento para frete | 0.52 |
| peso_bruto | Não | FLOAT | Peso bruto | 1.68 |
| portal | Sim | INT | Número do portal | 8266 |
| descricao_completa_commerce | Não | VARCHAR(8000) | Descrição completa do commerce | |
| canais_ecommerce_publicados | Não | TINYINT | Canais e-commerce publicados | |
| inicio_publicacao_produto | Não | DATE | Início da publicação | |
| fim_publicacao_produto | Não | DATE | Fim da publicação | |
| codigo_integracao_oms | Não | VARCHAR(50) | Código de integração com OMS Linx | '1231.A45.UN' |
| descricao_garantia | Não | VARCHAR(MAX) | Descrição da garantia | 'Garantia de um ano.' |
| meses_garantia_fabrica | Não | SMALLINT | Meses de garantia | 12 |
| ncm | Não | VARCHAR(20) | NCM do produto | '38029040' |
| cest | Não | VARCHAR(10) | CEST do produto | '01.001.00' |
| codigo_adicional_reshop | Não | VARCHAR(100) | Código adicional destinado ao Reshop | '38029040' |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigoproduto` (Não), `referencia` (Não, VARCHAR(20)), `codauxiliar1` (Não, VARCHAR(40)), `dt_cadastro_inicial` (Não, DATETIME), `dt_cadastro_fim` (Não, DATETIME), `timestamp` (Sim)

---

#### B2CConsultaProdutosDetalhes

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| Id_prod_det | Sim | BIGINT | Código único de controle | 56234 |
| codigoproduto | Sim | BIGINT | Código do produto | 12093 |
| empresa | Sim | INT | Empresa relacionada ao produto | 2 |
| saldo | Sim | FLOAT | Quantidade em estoque | 12.3 |
| controla_lote | Sim | BIT | Se o produto controla lotes | 0 |
| nomeproduto_alternativo | Não | VARCHAR(250) | Nome alternativo configurado no ERP | 'Bota Militar' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| referencia | Sim | VARCHAR(20) | Referência do produto | '12345678' |
| localizacao | Não | VARCHAR(50) | Localização do produto | |
| portal | Sim | INT | Número do portal | 8266 |
| tempo_preparacao_estoque | Não | SMALLINT | Tempo de preparação de estoque | |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigoproduto` (Não), `timestamp` (Sim), `empresas` (Não, VARCHAR(100))

> **Obs.:** em `empresas` pode ser informada mais de uma empresa do portal, separadas por vírgula. Ex.: `1,2,3`

---

#### B2CConsultaProdutosCustos

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_produtos_custos | Sim | INT | Código único de controle | 981273 |
| codigoproduto | Sim | BIGINT | Código do produto | 12938 |
| empresa | Sim | INT | Empresa relacionada | 1 |
| custoicms1 | Sim | FLOAT | Custo de compra | 12.48 |
| ipi1 | Sim | FLOAT | Percentual do IPI | 0 |
| markup | Sim | FLOAT | Percentual de lucro | 100 |
| customedio | Sim | FLOAT | Custo médio | 12.48 |
| frete1 | Sim | FLOAT | Valor de frete | 5 |
| precisao | Sim | INT | Casas decimais dos valores | 2 |
| precominimo | Sim | FLOAT | Preço mínimo para venda | 24 |
| dt_update | Não | SMALLDATETIME | Data de atualização | '2012-06-11' |
| custoliquido | Não | FLOAT | Custo líquido | 12.48 |
| precovenda | Não | FLOAT | Preço de venda | 24.96 |
| custototal | Não | FLOAT | Custo total | 12.48 |
| precocompra | Não | FLOAT | Preço de compra | 12.48 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| desconto_especial | Sim | FLOAT | Desconto para atacado | 0 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigoproduto` (Não), `timestamp` (Sim)

---

#### B2CConsultaProdutosCampanhas

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| codigo_campanha | Sim | INT | Código interno da campanha | 12 |
| nome_campanha | Sim | VARCHAR(60) | Nome da campanha | 'QUEIMA DE ESTOQUE' |
| vigencia_inicio | Sim | SMALLDATETIME | Início da campanha | '2012-06-11' |
| vigencia_fim | Sim | SMALLDATETIME | Fim da campanha | '2012-06-30' |
| observacao | Não | VARCHAR(MAX) | Observações | 'Avisar fulano' |
| ativo | Sim | BIT | Campanha ativa/desativa | 1 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigo_campanha` (Não), `vigencia_inicio` (Não, DATETIME), `vigencia_fim` (Não, DATETIME), `timestamp` (Sim)

---

#### B2CConsultaProdutosPromocao

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| codigo_promocao | Sim | BIGINT | Código interno da promoção | 92387 |
| empresa | Sim | INT | Empresa de vigência | 1 |
| codigoproduto | Sim | BIGINT | Código do produto | 1283 |
| preco | Sim | MONEY | Preço em promoção | 23.89 |
| data_inicio | Sim | SMALLDATETIME | Início da promoção | '2012-06-11' |
| data_termino | Sim | SMALLDATETIME | Término da promoção | '2012-06-30' |
| data_cadastro | Sim | DATETIME | Data de cadastro | '2012-06-11' |
| ativa | Sim | CHAR(1) | Promoção ativa/desativa | 'S' |
| codigo_campanha | Não | INT | Campanha vinculada | 12 |
| promocao_opcional | Sim | BIT | Se o preço normal pode ser praticado durante a vigência | 1 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| referencia | Não | VARCHAR(20) | Referência do produto agrupado | '1403EF' |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigo_promocao` (Não), `codigoproduto` (Não), `data_inicio` (Não), `data_termino` (Não), `filtra_empresa` (Não, BIT), `timestamp` (Sim), `somente_promocao_ativa` (Não, BIT), `filtra_campanha` (Não, BIT)

---

#### B2CConsultaProdutosCodebar

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| codigoproduto | Sim | BIGINT | Código do produto | 9283 |
| codebar | Sim | VARCHAR(20) | Código de barras | '17283947263902' |
| id_produtos_codebar | Não | INT | Código interno do registro | 9283 |
| principal | Sim | BIT | Se é o código de barras principal | 1 |
| empresa | Sim | INT | Código da empresa | 9999 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| tipo_codebar | Não | VARCHAR(20) | Tipo de código de barras | GTIN13 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codebar` (Não), `codigoproduto` (Não), `timestamp` (Sim), `tipo_codebar` (Não, VARCHAR(20))

> **Valores possíveis para `tipo_codebar`:** Código Gerencial, GTIN 08, GTIN 12, GTIN 13, GTIN 14

---

#### B2CConsultaProdutosInformacoes

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_produtos_informacoes | Sim | INT | Código interno do registro | 235 |
| codigoproduto | Sim | BIGINT | Código do produto | 23094 |
| informacoes_produto | Sim | VARCHAR(MAX) | Informações adicionais em texto | '&lt;P&gt;lápis&lt;/P&gt;' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigoproduto` (Não), `timestamp` (Sim)

---

#### B2CConsultaProdutosDimensoes

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| codigoproduto | Sim | BIGINT | Código do produto | 23094 |
| altura | Sim | DECIMAL(18,2) | Altura do produto | 293.03 |
| comprimento | Sim | DECIMAL(18,2) | Comprimento do produto | 123.01 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| largura | Sim | DECIMAL(18,2) | Largura do produto | 145.65 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigoproduto` (Não), `timestamp` (Sim)

---

#### B2CConsultaProdutosStatus

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| codigoproduto | Sim | BIGINT | Código do produto (SKU) | 12837 |
| referencia | Não | VARCHAR(20) | Referência do produto agrupado | '1403EF' |
| ativo | Sim | BIT | Produto ativo | 1 |
| b2c | Sim | BIT | Produto disponível para loja virtual | 1 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `timestamp` (Sim), `codigoproduto` (Não), `referencia` (Não), `ativo` (Não, BIT), `b2c` (Não, BIT)

---

#### B2CConsultaImagens

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_imagem | Sim | INT | Código interno do registro | 12 |
| imagem | Não | IMAGE | Imagem convertida em binário | '0xFD8FFE0001...' |
| md5 | Não | CHAR(32) | Hash de identificação da imagem | E1A6B1B1750E25179FC1E714C2155594 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |
| url_imagem_blob | Não | VARCHAR(200) | URL da imagem | `https://stgmvxprodimagens.blob.core.windows.net/produtos/16239-...jpeg` |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `id_imagem` (Não), `timestamp` (Sim)

---

#### B2CConsultaProdutosImagens

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_imagem_produto | Sim | INT | Código interno da tabela | 983 |
| id_imagem | Sim | INT | Código da imagem | 439 |
| codigoproduto | Sim | BIGINT | Código do produto | 90298 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |
| principal | Sim | BIT | Indica a imagem principal do produto | 0 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `id_imagem` (Não), `codigoproduto` (Não), `timestamp` (Sim)

---

#### B2CConsultaProdutosImagensURL

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| codigoproduto | Sim | BIGINT | Código do produto | 123 |
| url_imagem_blob | Sim | VARCHAR(200) | URL da imagem | `https://stgmvxprodimagens.blob.core.windows.net/produtos/...` |
| tipo | Sim | TINYINT | 1 = Imagem de Referência; 2 = Imagem de Catálogo; 3 = Demais imagens | 1 |
| portal | Sim | INT | Número do portal | 8266 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `timestamp` (Sim)

---

#### B2CConsultaProdutosAssociados

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id | Sim | INT | Código interno da tabela | 123 |
| codigoproduto | Sim | BIGINT | Produto que receberá a associação | 90298 |
| codigoproduto_associado | Sim | BIGINT | Produto associado | 90299 |
| coeficiente_desconto | Sim | FLOAT | Percentual de desconto nos associados | 10 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |
| qtde_item | Não | FLOAT | Quantidade de itens | 25,30 |
| item_obrigatorio | Não | BIT | Item obrigatório | 0 ou 1 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigoproduto_associado` (Não), `codigoproduto` (Não), `timestamp` (Sim), `processa_unico_timestamp` (Não, BIGINT)

---

#### B2CConsultaPedidos

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_pedido | Sim | INT | Código do pedido | 583 |
| dt_pedido | Sim | SMALLDATETIME | Data do pedido | '2012-06-11' |
| cod_cliente_erp | Sim | INT | Código do cliente no Microvix | 70 |
| cod_cliente_b2c | Sim | INT | Código do cliente no B2C | 80 |
| vl_frete | Não | FLOAT | Valor do frete | 8.5 |
| forma_pgto | Sim | INT | Forma de pagamento | 3 |
| plano_pagamento | Sim | INT | Plano de pagamento | 12 |
| anotacao | Não | VARCHAR(400) | Observação do pedido | 'primeira venda' |
| taxa_impressao | Não | FLOAT | Taxa de impressão | 0.85 |
| finalizado | Sim | BIT | Pedido finalizado | 1 |
| valor_frete_gratis | Não | FLOAT | Valor do frete grátis | 0 |
| tipo_frete | Não | INT | Identificador do tipo de frete | 99 |
| id_status | Não | INT | Status atual do pedido | 0 |
| cod_transportador | Não | INT | Código do transportador | 704 |
| tipo_cobranca_frete | Não | INT | Tipo de cobrança do frete | 1 |
| ativo | Sim | BIT | Pedido ativo (1) ou cancelado (0) | 1 |
| empresa | Não | INT | Empresa em que o pedido será integrado | 1 |
| id_tabela_preco | Sim | INT | ID da tabela de preço do Microvix | 1 |
| valor_credito | Não | MONEY | Valor de crédito usado como pagamento | 10.00 |
| cod_vendedor | Não | INT | Código do vendedor | 2 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| dt_insert | Não | SMALLDATETIME | Data de inserção no Microvix | '2012-06-11' |
| dt_disponivel_faturamento | Não | SMALLDATETIME | Data em que ficou disponível p/ faturamento | '2012-06-11' |
| mensagem_falha_faturamento | Não | VARCHAR(MAX) | Mensagem de falha do faturamento | 'Falha ao autorizar a NFe' |
| portal | Sim | INT | Número do portal | 8266 |
| order_id | Não | VARCHAR(40) | Identificação do pedido no Commerce Linx | 00001-00004 |
| fulfillment_id | Não | VARCHAR(40) | Identificação do fulfillment no Commerce Linx | e800ec3f-62f4-426a-aeae-145e0b6f9bcd |
| id_tipo_b2c | Não | TINYINT | Indica tipo B2C | 2 |
| ecommerce_origem | Não | VARCHAR(200) | E-commerce de origem | |
| id_endereco_entrega | Não | INT | ID do registro de endereço de entrega | 1 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `id_pedido` (Não), `data_origem_inicial` (Não, DATETIME), `data_origem_fim` (Não, DATETIME), `timestamp` (Sim)

---

#### B2CConsultaPedidosItens

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_pedido_item | Sim | INT | Código interno do item do pedido | 87354 |
| id_pedido | Sim | INT | Código do pedido | 583 |
| codigoproduto | Sim | BIGINT | Código do produto | 23453 |
| quantidade | Sim | INT | Quantidade pedida | 2 |
| vl_unitario | Sim | FLOAT | Valor unitário | 23.30 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `id_pedido` (Não), `codigoproduto` (Não), `timestamp` (Sim)

---

#### B2CConsultaClientes

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| cod_cliente_b2c | Sim | INT | Código do cliente na base B2C | 13 |
| cod_cliente_erp | Sim | INT | Código do cliente no Microvix ERP | 3344 |
| doc_cliente | Não | VARCHAR(14) | CPF do cliente | 77303835431 |
| nm_cliente | Não | VARCHAR(50) | Nome do cliente | 'João da Silva' |
| nm_mae | Não | VARCHAR(50) | Nome da mãe | 'Maria da Silva' |
| nm_pai | Não | VARCHAR(50) | Nome do pai | 'José da Silva' |
| nm_conjuge | Não | VARCHAR(50) | Nome do cônjuge | 'Marta da Silva' |
| dt_cadastro | Não | SMALLDATETIME | Data de cadastro na loja | '2012-06-13' |
| dt_nasc_cliente | Não | SMALLDATETIME | Data de nascimento | '1973-04-21' |
| end_cliente | Não | VARCHAR(250) | Endereço | 'Avenida das alamedas' |
| complemento_end_cliente | Não | VARCHAR(50) | Complemento | 'fundos' |
| nr_rua_cliente | Não | VARCHAR(20) | Número | 467 |
| bairro_cliente | Não | VARCHAR(60) | Bairro | 'Centro' |
| cep_cliente | Não | VARCHAR(9) | CEP | '89339238' |
| cidade_cliente | Não | VARCHAR(40) | Cidade | 'Santana do Parnaíba' |
| uf_cliente | Não | CHAR(2) | Estado | 'SP' |
| fone_cliente | Não | VARCHAR(20) | Telefone | '1134586732' |
| fone_comercial | Não | VARCHAR(20) | Telefone comercial | '1134586732' |
| cel_cliente | Não | VARCHAR(20) | Celular | '1199399483' |
| email_cliente | Não | VARCHAR(50) | E-mail | 'jao30@mail.com' |
| rg_cliente | Não | VARCHAR(20) | RG | 83949309 |
| rg_orgao_emissor | Não | VARCHAR(7) | Órgão emissor do RG | 'SSPSC' |
| estado_civil_cliente | Não | TINYINT | Estado civil | 2 |
| empresa_cliente | Não | VARCHAR(30) | Empresa em que trabalha | 'JAUM REPRESENTAÇÕES' |
| cargo_cliente | Não | VARCHAR(30) | Cargo | 'Dono' |
| sexo_cliente | Não | CHAR(1) | Sexo | 'M' |
| dt_update | Não | SMALLDATETIME | Última atualização | '2012-06-13' |
| ativo | Sim | BIT | Cliente ativo na loja | 1 |
| receber_email | Sim | BIT | Deseja receber e-mails | 1 |
| dt_expedicao_rg | Não | SMALLDATETIME | Data de expedição do RG | '1983-04-21' |
| naturalidade | Não | VARCHAR(40) | Naturalidade | 'Joinville' |
| tempo_residencia | Não | TINYINT | Tempo de residência | 3 |
| renda | Não | MONEY | Renda mensal | 1923.00 |
| numero_compl_rua_cliente | Não | VARCHAR(10) | Complemento do número da rua | 'Fnd 302' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| tipo_pessoa | Sim | CHAR(1) | 'F' física ou 'J' jurídica | 'F' |
| portal | Sim | INT | Número do portal | 8266 |
| aceita_programa_fidelidade | Não | BIT | Aceita programa de fidelidade | 1 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `cod_cliente_erp` (Não), `cod_cliente_b2c` (Não), `doc_cliente` (Não, VARCHAR(14)), `timestamp` (Sim), `exibe_clientes_erp` (Não, BIT — 1 = Sim, 0 = Não)

---

#### B2CConsultaClientesContatosParentesco

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_parentesco | Sim | INT | Código interno do parentesco | 1 |
| descricao_parentesco | Sim | VARCHAR(50) | Descrição do parentesco | Pai |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `id_parentesco` (Não), `descricao_parentesco` (Não, VARCHAR(50)), `timestamp` (Sim)

---

#### B2CConsultaClientesEnderecosEntrega

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_endereco_entrega | Sim | INT | Código interno do endereço de entrega | 832094 |
| cod_cliente_erp | Sim | INT | Código do cliente no Microvix ERP | 3344 |
| cod_cliente_b2c | Sim | INT | Código do cliente na base B2C | 13 |
| endereco_cliente | Sim | VARCHAR(250) | Nome da rua | 'R. dos Capinzais' |
| numero_rua_cliente | Não | VARCHAR(20) | Número da residência | '23' |
| complemento_end_cli | Não | VARCHAR(60) | Complemento | 'fundos' |
| cep_cliente | Não | CHAR(9) | CEP | 89773745 |
| bairro_cliente | Não | VARCHAR(60) | Bairro | 'centro' |
| cidade_cliente | Não | VARCHAR(40) | Cidade | 'Jaborá' |
| uf_cliente | Não | CHAR(2) | Estado | 'SC' |
| descricao | Não | VARCHAR(250) | Descrição do endereço | '' |
| principal | Sim | BIT | Endereço principal para entregas | 1 |
| id_cidade | Não | INT | ID da cidade no IBGE | 4208609 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| id_endereco_entrega_b2c | Não | BIGINT | ID de origem do endereço cadastrado via WS | 10 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `cod_cliente_erp` (Não), `cod_cliente_b2c` (Não), `timestamp` (Sim)

---

#### B2CConsultaTipoEncomenda

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_tipo_encomenda | Sim | INT | Código interno do tipo de encomenda | 99 |
| nm_tipo_encomenda | Sim | VARCHAR(100) | Nome do tipo de encomenda | 'Transportadora' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `timestamp` (Sim)

---

#### B2CConsultaProdutosCamposAdicionaisNomes

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_campo | Sim | INT | Código interno do cadastro auxiliar adicional | 2 |
| ordem | Sim | TINYINT | Ordem em que o campo aparece no ERP | 3 |
| legenda | Sim | VARCHAR(30) | Descrição do campo adicional | 'GRUPO' |
| tipo | Sim | CHAR(1) | Tipo de validação: texto [c], inteiro [i], numérico [n], lógico [b] | 'c' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `id_campo` (Não), `timestamp` (Sim)

---

#### B2CConsultaProdutosCamposAdicionaisDetalhes

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_campo_detalhe | Sim | INT | Código interno do detalhamento | 15 |
| ordem | Não | INT | Ordem em que o campo aparece no ERP | 3 |
| descricao | Sim | VARCHAR(30) | Descrição do detalhamento | 'MEIA IDADE' |
| id_campo | Sim | INT | FK com `produtos_campos_adicionais_nomes` | 2 |
| ativo | Sim | BIT | Registro ativo | 1 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `id_campo` (Não), `id_campo_detalhe` (Não), `timestamp` (Sim)

---

#### B2CConsultaProdutosCamposAdicionaisValores

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_campo_valor | Sim | INT | Código interno do valor do cadastro adicional | 1585 |
| codigoproduto | Sim | BIGINT | FK com `produtos` (SKU) | 583 |
| id_campo_detalhe | Sim | INT | FK com `produtos_campos_adicionais_detalhes` | 15 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigoproduto` (Não), `id_campo_detalhe` (Não), `timestamp` (Sim)

---

#### B2CConsultaStatus

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_status | Sim | TINYINT | Código interno do status cadastrado no ERP | 2 |
| descricao_status | Sim | VARCHAR(30) | Descrição do status | 'EM ABERTO' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `timestamp` (Sim)

---

#### B2CConsultaPedidosStatus

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id | Sim | INT | Código interno do registro | 135 |
| id_status | Sim | TINYINT | FK com a tabela `status` | 2 |
| id_pedido | Sim | INT | FK com a tabela de pedidos | 44 |
| data_hora | Sim | DATETIME | Data/hora da alteração do status no ERP | '2014-04-20 14:30' |
| anotacao | Sim | VARCHAR(80) | Anotação anexada à troca de status | 'manipulação indevida' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `id_pedido` (Não), `data_inicial` (Não, DATETIME), `data_fim` (Não, DATETIME), `timestamp` (Sim)

---

#### B2CConsultaFornecedores

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| cod_fornecedor | Sim | INT | Código interno do fornecedor | 3 |
| nome | Sim | VARCHAR(60) | Nome | 'Comércio LTDA' |
| nome_fantasia | Não | VARCHAR(60) | Nome fantasia | 'Comércio LTDA' |
| tipo_pessoa | Sim | CHAR(1) | Pessoa física ou jurídica | 'F' |
| tipo_fornecedor | Sim | CHAR(1) | Cadastrado como cliente, fornecedor ou transportador | 'F' |
| endereco | Não | VARCHAR(250) | Endereço | 'Avenida das alamedas' |
| numero_rua | Não | VARCHAR(20) | Número | '467' |
| bairro | Não | VARCHAR(60) | Bairro | 'Centro' |
| cep | Não | CHAR(9) | CEP | '892227-65' |
| cidade | Sim | VARCHAR(40) | Cidade | 'Joinville' |
| uf | Sim | VARCHAR(200) | Estado | 'SC' |
| documento | Sim | VARCHAR(14) | CPF/CNPJ | '05746237784' |
| fone | Não | VARCHAR(20) | Telefone | '34223456' |
| email | Não | VARCHAR(50) | E-mail | 'sac@comercioltda.com.br' |
| pais | Não | VARCHAR(80) | País | 'Brasil' |
| obs | Não | VARCHAR(MAX) | Observação | 'Rápido' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `cod_fornecedor` (Não), `documento` (Não, VARCHAR(14)), `timestamp` (Sim)

---

#### B2CConsultaProdutosTabelas

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_tabela | Sim | INT | Código interno da tabela de preço | 2 |
| nome_tabela | Sim | VARCHAR(50) | Nome da tabela de preço | 'Liquidação' |
| ativa | Sim | CHAR(1) | Tabela ativa ou inativa | 'S' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `id_tabela` (Não), `timestamp` (Sim)

> ⚠️ **Obs.:** A tabela de preço parametrizada para B2C só pode ser do tipo **"Preços diferentes por produto"**. O B2C **não aceita** tabelas com "markup %", "desconto %", "Índice % para todas as Linhas" ou "Índice % diferente por Linha".

---

#### B2CConsultaProdutosTabelasPrecos

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_prod_tab_preco | Sim | INT | Código interno do preço na tabela | 203 |
| id_tabela | Sim | INT | Código da tabela de preço relacionada | 2 |
| codigoproduto | Sim | BIGINT | Código do produto | 2398 |
| precovenda | Sim | MONEY | Valor do item na tabela | 3,40 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `id_tabela` (Não), `codigoproduto` (Não), `timestamp` (Sim)

> ⚠️ Mesma restrição de tipo de tabela de preço descrita acima.

---

#### B2CConsultaTransportadores

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| cod_transportador | Sim | INT | Código do transportador (mesmo do ERP) | 714 |
| nome | Sim | VARCHAR(60) | Nome | 'Transp. XV novembro' |
| nome_fantasia | Não | VARCHAR(60) | Nome fantasia | 'Transp. XV novembro' |
| tipo_pessoa | Sim | CHAR(1) | Física (F) ou jurídica (J) | 'F' |
| tipo_transportador | Sim | CHAR(1) | Tipo do transportador (T) | 'T' |
| endereco | Sim | VARCHAR(250) | Endereço | 'Avenida das alamedas' |
| numero_rua | Não | VARCHAR(20) | Número | '467' |
| bairro | Não | VARCHAR(60) | Bairro | 'Centro' |
| cep | Não | CHAR(9) | CEP | '058596422' |
| cidade | Sim | VARCHAR(40) | Cidade | 'Joinville' |
| uf | Sim | VARCHAR(20) | Estado | 'SC' |
| documento | Sim | VARCHAR(14) | CPF ou CNPJ | '05745841154' |
| fone | Não | VARCHAR(20) | Telefone | '4734554673' |
| email | Não | VARCHAR(50) | E-mail | 'mail@mail.com' |
| pais | Não | VARCHAR(80) | País | 'Brasil' |
| obs | Não | VARCHAR(MAX) | Observações | 'Após às 16' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `cod_transportador` (Não), `documento` (Não, VARCHAR(14)), `timestamp` (Sim)

---

#### B2CConsultaTiposCobrancaFrete

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| codigo_tipo_cobranca_frete | Sim | INT | Código do tipo de cobrança | 1 |
| nome_tipo_cobranca_frete | Sim | VARCHAR(60) | Descrição do tipo de cobrança | 'CIF' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `timestamp` (Sim)

---

#### B2CConsultaClientesSaldo

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| saldo | Sim | MONEY | Saldo disponível no momento da compra | 193.40 |
| cod_cliente_erp | Sim | INT | Código do cliente | 293 |
| empresa | Sim | INT | Código da empresa | 1 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `cod_cliente_erp` (Não), `timestamp` (Sim)

---

#### B2CConsultaProdutosDepositos

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_deposito | Sim | INT | Código do depósito | 1 |
| nome_deposito | Sim | VARCHAR(50) | Nome do depósito | Devolvidos |
| disponivel | Sim | CHAR(1) | Se o depósito está disponível para consulta (se não, produtos relacionados não constam em estoque) | 'S' |
| disponivel_transferencia | Sim | BIT | Se pode receber transferência de produtos | 1 |
| disponivel_franquias | Sim | BIT | Se está disponível para as franquias | 1 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |
| outlet | Sim | BIT | Se é depósito de outlet | 0 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `id_deposito` (Não), `timestamp` (Sim)

---

#### B2CConsultaProdutosDetalhesDepositos

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| codigoproduto | Sim | BIGINT | Código do produto | 9234 |
| empresa | Sim | INT | Código da empresa do saldo | 1 |
| id_deposito | Sim | INT | Código do depósito | 1 |
| saldo | Sim | FLOAT | Saldo do produto no depósito para a empresa | 8 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |
| tempo_preparacao_estoque | Não | SMALLINT | Tempo de preparação de estoque | |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `codigoproduto` (Não), `timestamp` (Sim), `deposito` (Não, INT)

---

#### B2CConsultaClientesSaldoLinx

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| cod_cliente_erp | Sim | INT | Código do cliente no Microvix ERP | 3344 |
| cod_cliente_b2c | Sim | INT | Código do cliente na base B2C | 13 |
| empresa | Sim | INT | Empresa em que o crédito está disponível | 1 |
| valor | Sim | MONEY | Valor de crédito disponível para faturar | 50.00 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `cod_cliente_erp` (Não), `timestamp` (Sim)

---

#### B2CConsultaVendedores

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| cod_vendedor | Sim | INT | Código do vendedor no Microvix ERP | 12 |
| nome_vendedor | Sim | VARCHAR(50) | Nome do vendedor | 'Zé vendedor' |
| comissao_produtos | Não | MONEY | Percentual de comissão por produtos | 1 |
| comissao_servicos | Não | MONEY | Percentual de comissão por serviços | 1 |
| tipo | Não | CHAR(1) | Comprador ou vendedor | 'C' |
| ativo | Não | BIT | Vendedor ativo | 0 |
| comissionado | Não | BIT | Vendedor comissionado | 1 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `cod_vendedor` (Não), `timestamp` (Sim)

---

#### B2CConsultaNFe

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_nfe | Sim | INT | Código interno da NFe no Microvix ERP | 9 |
| id_pedido | Sim | INT | Código do pedido do B2C | 7 |
| documento | Sim | INT | Número da nota fiscal | 8 |
| data_emissao | Sim | SMALLDATETIME | Data de emissão | '2016-12-31' |
| chave_nfe | Não | CHAR(44) | Chave de 44 dígitos da NFe | '35130614439371001231550020000000051074042931' |
| situacao | Sim | TINYINT | Situação da NFe | 3 |
| xml | Não | VARCHAR(MAX) | Dados do arquivo XML da NFe | |
| excluido | Sim | BIT | NFe excluída ou não | 1 |
| identificador_microvix | Não | UNIQUEIDENTIFIER | Identificador único interno do MicrovixERP | 7639AE3A-AF95-4C2B-B6EF-872B159C9240 |
| dt_insert | Sim | SMALLDATETIME | Data de inserção do registro | '2017-01-01' |
| valor_nota | Não | MONEY | Valor total final da nota | 1114.30 |
| serie | Não | VARCHAR(10) | Série da nota | '2' |
| frete | Não | MONEY | Valor total do frete | 8.90 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |
| nProt | Não | VARCHAR(15) | Protocolo de NF para saídas | '333110001225528' |
| codigo_modelo_nf | Não | VARCHAR(3) | Código do modelo de nota fiscal | '65' |
| justificativa | Não | VARCHAR(255) | Justificativa da NFe | |
| tpAmb | Não | INT | Ambiente: (1) Produção, (2) Homologação | 1 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `id_nfe` (Não), `id_pedido` (Não), `documento` (Não), `chave_nfe` (Não, CHAR(44)), `data_inicial` (Não, DATETIME), `data_fim` (Não, DATETIME), `timestamp` (Sim)

---

#### B2CConsultaNFeSituacao

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_nfe_situacao | Sim | TINYINT | Código único da situação da NFe | 1 |
| descricao | Sim | VARCHAR(30) | Descrição da situação | Autorizada |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `timestamp` (Sim)

---

#### B2CConsultaClientesEstadoCivil

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_estado_civil | Sim | INT | Código do estado civil | 2 |
| estado_civil | Sim | VARCHAR(20) | Descrição | 'Casado' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `timestamp` (Sim)

---

#### B2CConsultaCodigoRastreio

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_pedido | Sim | INT | Código do pedido do B2C | 7 |
| documento | Sim | INT | Número da nota fiscal | 8 |
| serie | Sim | VARCHAR(10) | Série da nota | '2' |
| codigo_rastreio | Sim | VARCHAR(20) | Código de rastreio dos Correios | ABC123 |
| sequencia_volume | Sim | VARCHAR(20) | Sequência do volume | 1/1 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `id_pedido` (Não), `documento` (Não), `codigo_rastreio` (Não, VARCHAR(20)), `timestamp` (Sim), `apenas_faturado` (Não, BIT)

> **Obs.:** `apenas_faturado` não informado ou = 1 → exporta apenas códigos de rastreio de pedidos faturados. Se = 0 → retorna todos.

---

#### B2CConsultaPedidosPlanos

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_pedido_planos | Sim | BIGINT | Código do plano do pedido | 12345 |
| id_pedido | Sim | INT | Código do pedido | 123 |
| plano_pagamento | Sim | INT | Código do plano de pagamento | 10 |
| valor_plano | Sim | MONEY | Valor do plano | 10.99 |
| nsu_sitef | Não | VARCHAR(20) | Código NSU SiTef | '10' |
| cod_autorizacao | Não | VARCHAR(50) | Código de autorização | '11' |
| texto_comprovante | Não | VARCHAR(MAX) | Texto do comprovante | 'Texto' |
| cod_loja_sitef | Não | VARCHAR(10) | Código da loja SiTef | '12' |
| timestamp | Sim | TIMESTAMP | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `id_pedido` (Não), `timestamp` (Sim)

---

#### B2CConsultaImagensHD

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| portal | Sim | INT | Número do portal | 8266 |
| identificador_imagem | Sim | UNIQUEIDENTIFIER | Identificador da imagem | 74D2E54B-EEB8-4B48-83DB-4D9F98F92DC5 |
| codigoproduto | Sim | BIGINT | Código do produto | 90298 |
| imagem | Sim | VARBINARY(MAX) | Imagem convertida em Base64 | /9j/4AAQSkZJRgABAQEA... |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| url_imagem_blob | Não | VARCHAR(200) | URL da imagem | `https://stgmvxprodimagens.blob.core.windows.net/produtos/...` |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `timestamp` (Sim), `codigoproduto` (**Sim**, BIGINT)

---

#### B2CConsultaLegendasCadastrosAuxiliares

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| empresa | Sim | INT | Código da empresa no MicrovixERP | 1 |
| legenda_setor | Sim | VARCHAR(20) | Legenda do Setor | setor |
| legenda_linha | Sim | VARCHAR(20) | Legenda da Linha | linha |
| legenda_marca | Sim | VARCHAR(20) | Legenda da Marca | marca |
| legenda_colecao | Sim | VARCHAR(20) | Legenda da Coleção | colecao |
| legenda_grade1 | Sim | VARCHAR(20) | Legenda da Grade 1 | grade1 |
| legenda_grade2 | Sim | VARCHAR(20) | Legenda da Grade 2 | grade2 |
| legenda_espessura | Sim | VARCHAR(20) | Legenda da Espessura | espessura |
| legenda_classificacao | Sim | VARCHAR(20) | Legenda da Classificação | classificacao |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `timestamp` (Sim)

---

#### B2CConsultaPedidosTipos

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_tipo_b2c | Sim | TINYINT | Código tipo B2C | 3 |
| descricao | Sim | VARCHAR(200) | Descrição | '' |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `timestamp` (Sim)

---

#### B2CConsultaTags

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| portal | Sim | INT | Número do portal | 8266 |
| id_b2c_tags | Sim | INT | ID da tag | 1 |
| descricao | Sim | VARCHAR(300) | Descrição da tag | Tag 1 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `timestamp` (Sim)

---

#### B2CConsultaProdutosTags

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| portal | Sim | INT | Número do portal | 8266 |
| id_b2c_tags_produtos | Sim | INT | ID da tag-produto | 1 |
| id_b2c_tags | Sim | INT | ID da tag | 1 |
| codigoproduto | Sim | BIGINT | Código do produto | 123 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| descricao_b2c_tags | Sim | VARCHAR(300) | Descrição da tag | Tag 1 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `timestamp` (Sim)

---

#### B2CConsultaFlags

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| portal | Sim | INT | Número do portal | 8266 |
| id_b2c_flags | Sim | INT | ID da flag | 1 |
| descricao | Sim | VARCHAR(300) | Descrição da flag | Flag 1 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `timestamp` (Sim)

---

#### B2CConsultaProdutosFlags

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| portal | Sim | INT | Número do portal | 8266 |
| id_b2c_flags_produtos | Sim | INT | ID da flag-produto | 1 |
| id_b2c_flags | Sim | INT | ID da flag | 1 |
| codigoproduto | Sim | BIGINT | Código do produto | 123 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| descricao_b2c_flags | Sim | VARCHAR(300) | Descrição da flag | Flag 1 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `timestamp` (Sim)

---

#### B2CConsultaPalavrasChavePesquisa

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| portal | Sim | INT | Número do portal | 8266 |
| id_b2c_palavras_chave_pesquisa | Sim | INT | ID da palavra-chave de pesquisa | 1 |
| descricao | Sim | VARCHAR(300) | Descrição da palavra-chave | Palavra 1 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `timestamp` (Sim)

---

#### B2CConsultaProdutosPalavrasChavePesquisa

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| portal | Sim | INT | Número do portal | 8266 |
| id_b2c_palavras_chave_pesquisa_produtos | Sim | INT | ID da palavra-chave-produto | 1 |
| id_b2c_palavras_chave_pesquisa | Sim | INT | ID da palavra-chave | 1 |
| codigoproduto | Sim | BIGINT | Código do produto | 123 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| descricao_b2c_palavras_chave_pesquisa | Sim | VARCHAR(300) | Descrição da palavra-chave | Palavra 1 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `timestamp` (Sim)

---

#### B2CConsultaCNPJsChave

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| CNPJ | Sim | VARCHAR(14) | CNPJ da empresa | 12345678912345 |
| nome_empresa | Sim | VARCHAR(300) | Nome da empresa | Loja 1 |
| id_empresas_rede | Não | SMALLINT | ID da rede | 1 |
| rede | Não | VARCHAR(100) | Nome da rede | ABC |
| portal | Sim | INT | Número do portal | 8266 |
| nome_portal | Sim | VARCHAR(50) | Nome do portal | ABC - Lojas |
| empresa | Sim | INT | Código da empresa no MicrovixERP | 1 |
| classificacao_portal | Sim | VARCHAR(50) | Classificação do portal | Cliente |
| b2c | Sim | BIT | B2C habilitado | 1 |
| oms | Sim | BIT | OMS habilitado | 1 |
| b2c_marketplace | Sim | BIT | B2C Marketplace habilitado | 1 |

**Parâmetros de consulta:** `chave` (Sim), `portal` (Não, INT), `id_classificacao` (Não, INT)

> **Obs.:** por padrão retorna apenas portais de **produção**. Para outros tipos, informar `id_classificacao`. Portais de **teste** = valor **6**:
> ```xml
> <Parameter id="id_classificacao">6</Parameter>
> ```

---

#### B2CConsultaPedidosIdentificador
*(retorna os registros da tabela `B2C_vendas`)*

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| portal | Sim | INT | Número do portal | 8266 |
| empresa | Sim | INT | Código da empresa no MicrovixERP | 1 |
| identificador | Não | UNIQUEIDENTIFIER | Código interno Microvix p/ identificar e agrupar dados do documento | 99 |
| id_venda | Não | INT | ID da venda na tabela `b2c_vendas` | 54 |
| order_id | Não | VARCHAR(40) | Código do pedido no B2C | 4545 |
| id_cliente | Não | INT | Código do cliente no Microvix | 90 |
| valor_frete | Não | MONEY | Valor do frete | 20 |
| data_origem | Não | DATETIME | Data de origem do pedido | 2023-01-01 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `data_origem_fim` (Não, DATETIME), `data_origem_inicial` (Não, DATETIME), `id_pedido` (Não, INT), `identificador` (Não, UNIQUEIDENTIFIER), `order_id` (Não, INT), `processa_unico_timestamp` (Não, BIT), `timestamp` (Sim)

---

#### B2CConsultaClientesContatos

| Nome | Obrig. | Tipo | Descrição | Exemplo |
|---|---|---|---|---|
| id_clientes_contatos | Sim | INT | Chave primária da tabela | 1 |
| id_contato_b2c | Não | INT | ID do contato recebido via `B2CCadastraClientesContatos` | 2 |
| nome_contato | Sim | VARCHAR(50) | Nome do contato | 'João' |
| data_nasc_contato | Não | DATETIME | Data de nascimento | '2002-06-13' |
| sexo_contato | Não | CHAR(1) | Sexo | 'M' |
| id_parentesco | Não | TINYINT | ID do cadastro de parentesco | 1 |
| fone_contato | Não | VARCHAR(20) | Telefone | '(47) 9999-9999' |
| celular_contato | Não | VARCHAR(20) | Celular | '(47) 5555-5555' |
| email_contato | Não | VARCHAR(50) | E-mail | 'joao@email.com' |
| cod_cliente_erp | Não | INT | Código do cliente no Microvix | 2014 |
| timestamp | Sim | BIGINT | Timestamp da tabela | 185318313 |
| portal | Sim | INT | Número do portal | 8266 |

**Parâmetros de consulta:** `chave` (Sim), `cnpjEmp` (Sim), `timestamp` (Sim)

---

### 6.2 Web Service Entrada

#### B2CCadastraClientes

| Nome | Obrig. | Tipo | Chave Primária |
|---|---|---|---|
| cod_cliente | Sim | INT | **Sim** |
| doc_cliente | Sim | VARCHAR(14) | Não |
| ativo | Sim | BIT | Não |
| receber_email | Sim | BIT | Não |
| nm_cliente | Não | VARCHAR(50) | Não |
| nm_mae | Não | VARCHAR(50) | Não |
| nm_pai | Não | VARCHAR(50) | Não |
| nm_conjuge | Não | VARCHAR(50) | Não |
| dt_cadastro | Não | DATETIME | Não |
| dt_nasc_cliente | Não | DATETIME | Não |
| end_cliente | Não | VARCHAR(250) | Não |
| complemento_end_cliente | Não | VARCHAR(50) | Não |
| nr_rua_cliente | Não | VARCHAR(20) | Não |
| bairro_cliente | Não | VARCHAR(60) | Não |
| cep_cliente | Não | VARCHAR(9) | Não |
| cidade_cliente | Não | VARCHAR(40) | Não |
| uf_cliente | Não | VARCHAR(2) | Não |
| fone_cliente | Não | VARCHAR(20) | Não |
| fone_comercial | Não | VARCHAR(20) | Não |
| cel_cliente | Não | VARCHAR(20) | Não |
| email_cliente | Não | VARCHAR(50) | Não |
| rg_cliente | Não | VARCHAR(20) | Não |
| rg_orgao_emissor | Não | VARCHAR(7) | Não |
| estado_civil_cliente | Não | BIT | Não |
| empresa_cliente | Não | VARCHAR(30) | Não |
| cargo_cliente | Não | VARCHAR(30) | Não |
| sexo_cliente | Não | VARCHAR(1) | Não |
| dt_update | Não | DATETIME | Não |
| dt_expedicao_rg | Não | DATETIME | Não |
| naturalidade | Não | VARCHAR(40) | Não |
| tempo_residencia | Não | BIT | Não |
| renda | Não | MONEY | Não |
| numero_compl_rua_cliente | Não | VARCHAR(10) | Não |
| atualizar_por_cpf | Não | BIT | Não |
| cliente_contribuinte | Não | BIT | Não |
| inscricao_cliente | Não | VARCHAR(20) | Não |
| aceita_programa_fidelidade | Não | BIT | Não |

> **Obs. 1:** Se os campos básicos de endereço não forem preenchidos, haverá problemas de faturamento, como *"detalhamento de configuração tributária não encontrado"*.
> **Obs. 2:** `apelido_cliente`, `cartao_proprio`, `dt_admissao` foram **descontinuados** — não devem ser enviados.
> **Obs. 3:** Para evitar duplicidade, usar `atualizar_por_cpf = 1` — o B2C identifica pelo CPF se o cliente já existe.

---

#### B2CCadastraClientesContatos

| Nome | Obrig. | Tipo | Chave Primária |
|---|---|---|---|
| id_contato | Sim | INT | **Sim** |
| nome_contato | Sim | VARCHAR(50) | Não |
| doc_cliente | Sim | VARCHAR(14) | Não |
| data_nasc_contato | Não | DATETIME | Não |
| sexo_contato | Não | CHAR(1) | Não |
| id_parentesco | Não | TINYINT | Não |
| fone_contato | Não | VARCHAR(20) | Não |
| celular_contato | Não | VARCHAR(20) | Não |
| email_contato | Não | VARCHAR(50) | Não |

---

#### B2CCancelaPedido

| Nome | Obrig. | Tipo | Chave Primária |
|---|---|---|---|
| id_pedido | Sim | INT | **Sim** |
| ativo | Sim | BIT | Não |

---

#### B2CCadastraClientesEnderecosEntrega

| Nome | Obrig. | Tipo | Chave Primária |
|---|---|---|---|
| id_endereco_entrega | Sim | INT | **Sim** |
| cod_cliente | Sim | INT | Não |
| endereco_cliente | Sim | VARCHAR(250) | Não |
| principal | Sim | BIT | Não |
| numero_rua_cliente | Não | VARCHAR(20) | Não |
| complemento_end_cli | Não | VARCHAR(60) | Não |
| cep_cliente | Não | VARCHAR(9) | Não |
| bairro_cliente | Não | VARCHAR(60) | Não |
| cidade_cliente | Não | VARCHAR(40) | Não |
| uf_cliente | Não | VARCHAR(2) | Não |
| descricao | Não | VARCHAR(250) | Não |
| id_cidade | Não | INT | Não |

---

#### B2CCadastraPedido

| Nome | Obrig. | Tipo | Chave Primária |
|---|---|---|---|
| id_pedido | Sim | INT | **Sim** |
| dt_pedido | Sim | DATETIME | Não |
| cod_cliente | Sim | INT | Não |
| tipo_frete | Sim | INT | Não |
| id_status | Sim | INT | Não |
| valor_frete | Sim | MONEY | Não |
| ativo | Sim | BIT | Não |
| plano_pagamento | Não | INT | Não |
| anotacao | Não | VARCHAR(400) | Não |
| taxa_impressao | Não | MONEY | Não |
| valor_frete_gratis | Não | MONEY | Não |
| cod_transportador | Não | INT | Não |
| tipo_cobranca_frete | Não | INT | Não |
| empresa | Não | INT | Não |
| valor_credito | Não | MONEY | Não |
| cod_vendedor | Não | INT | Não |
| qtde_itens | Não | INT | Não |
| codigo_rastreio | Não | VARCHAR(20) | Não |
| somente_reserva | Não | BIT | Não |
| multiplos_planos | Não | BIT | Não |
| desconto | Não | MONEY | Não |
| especie_volumes | Não | VARCHAR(60) | Não |
| id_endereco_entrega | Não | INT | Não |
| quantidade_volumes | Não | INT | Não |
| id_deposito_faturamento | Não | INT | Não |
| ecommerce_origem | Não | VARCHAR(200) | Não |
| id_tipo_b2c | Não | TINYINT | Não |
| marketplace_cnpj | Não | VARCHAR(14) | Não |
| marketplace_grupo | Não | VARCHAR(60) | Não |
| marketplace_loja | Não | VARCHAR(60) | Não |
| descricao_cupom_desconto | Não | VARCHAR(160) | Não |
| valor_seguro | Não | MONEY | Não |
| marketplace_full_url_xml | Não | VARCHAR(200) | Não |

**Observações:**

1. Em `tipo_frete` deve ser informado o `id_tipo_encomenda`, conforme exportado por `B2CConsultaTipoEncomenda`.
2. `somente_reserva = 1` → o pedido é criado mas fica em marca d'água, indisponível para faturar. Só ao enviar `somente_reserva = 0` o pedido pode ser efetivado. Para gerar o pedido, chamar `B2CCadastraPedido` com `somente_reserva = 0`. Para cancelar a reserva, chamar `B2CCancelaPedido`.
3. Quando o pedido possui **múltiplos planos**, o plano informado em `B2CCadastraPedido` é **ignorado** — valem apenas os de `B2CCadastraPedidoPlanos`.
4. Se o ID do depósito não for enviado, vale o depósito preferencial em *Empresa > Parâmetros globais > B2C*. Sem configuração, vale o depósito 1.
5. ⚠️ **Não fazer múltiplas requisições de cadastro no mesmo método, mesmo portal e mesmo segundo** — afeta a quantidade de produtos reservados em estoque.
6. O `plano_pagamento` deve ser o código cadastrado no MicrovixERP.
7. `id_pedido` deve ser **maior que 0** (não permite ID negativo).
8. Descontinuados (não enviar): `id_status_pedido`, `vl_frete`, `vl_total`, `forma_pgto`, `finalizado`, `dt_disponivel_faturamento`, `finalizado_erp`, `dt_insert`.
9. `fulfillment_id` e `order_id` são de uso interno da Linx — **não enviar**.
10. Para pedidos com NFC-e (cupom fiscal) na opção "Retira em Loja", não pode informar planos que utilizem TEF.

---

#### B2CCadastraPedidoPlanos

| Nome | Obrig. | Tipo | Chave Primária |
|---|---|---|---|
| id_pedido_planos | Sim | BIGINT | **Sim** |
| id_pedido | Sim | INT | Não |
| plano_pagamento | Sim | INT | Não |
| valor_plano | Sim | MONEY | Não |
| cod_autorizacao | Não | VARCHAR(50) | Não |
| cod_loja_sitef | Não | VARCHAR(10) | Não |
| nsu_sitef | Não | VARCHAR(20) | Não |
| texto_comprovante | Não | VARCHAR(MAX) | Não |
| cnpj_beneficiario | Não | VARCHAR(14) | Não |
| cnpj_recebimento | Não | VARCHAR(14) | Não |
| cnpj_rede_adquirente | Não | VARCHAR(14) | Não |
| data_pagamento | Não | SMALLDATETIME | Não |
| id_terminal_pagamento | Não | VARCHAR(40) | Não |
| uf_pagamento | Não | CHAR(2) | Não |

> **Obs.:** Só serão integrados os planos se o pedido estiver marcado como múltiplos planos **e** a empresa estiver habilitada para múltiplos planos no MicrovixERP.

---

#### B2CCadastraPedidoItens

| Nome | Obrig. | Tipo | Chave Primária |
|---|---|---|---|
| id_pedido_item | Sim | INT | **Sim** |
| id_pedido | Sim | INT | Não |
| codigoproduto | Sim | INT | Não |
| quantidade | Sim | INT | Não |
| vl_unitario | Sim | MONEY | Não |
| descricao_cupom_desconto | Não | VARCHAR(160) | Não |

> **Obs. 1:** `id_pedido` e `id_pedido_item` devem ser maiores que 0.
> **Obs. 2:** `txt_observacao` e `dt_insert` foram descontinuados — não enviar.

---

#### B2CAtualizaCodigoRastreio

| Nome | Obrig. | Tipo | Chave Primária |
|---|---|---|---|
| id_pedido | Sim | INT | **Sim** |
| codigo_rastreio | Sim | VARCHAR(20) | Não |

---

#### B2CAtualizaPedidoStatus

| Nome | Obrig. | Tipo | Chave Primária |
|---|---|---|---|
| id_pedido | Sim | VARCHAR(100) | **Sim** |
| id_status | Sim | INT | **Sim** |

---

#### B2CCadastraImagensHD

| Nome | Obrig. | Tipo | Chave Primária | Descrição |
|---|---|---|---|---|
| id_imagem | Sim | VARCHAR(100) | **Sim** | ID da imagem no sistema origem |
| extensao | Sim | VARCHAR(100) | Não | Extensão da imagem, sem ponto |
| imagem | Sim | IMAGE | Não | Imagem em Base64 |
| nome | Sim | VARCHAR(100) | Não | Nome da imagem |

> **Obs. 1:** Imagens em Base64 (ex. de conversão: `https://codebeautify.org/image-to-base64-converter`).
> **Obs. 2:** Limite máximo de **2 MB** por imagem.
> **Obs. 3:** São permitidas **5 imagens por produto**.

---

#### B2CCadastraProdutosImagensHD

| Nome | Obrig. | Tipo | Chave Primária | Descrição |
|---|---|---|---|---|
| codigoproduto | Sim | BIGINT | **Sim** | Código do produto no MicrovixERP |
| id_imagem | Sim | VARCHAR(100) | **Sim** | ID da imagem no sistema origem |

---

## 7 Definições dos campos STATUS e SITUAÇÃO

### STATUS

É **somente informativo** — o cliente pode cadastrar quantos quiser no ERP. Normalmente determina algum fluxo que o pedido deve seguir. A alteração pode ocorrer via `B2CAtualizaPedidoStatus`. Exceção: o status inicial, que deve ser informado em `B2CCadastraPedido`.

Exemplo de status:

```
0.  SEM STATUS
1.  FATURADO
2.  PRONTO PARA SEPARAÇÃO
3.  NÃO É A PRIMEIRA SEPARAÇÃO
4.  EM SEPARAÇÃO
5.  RELATÓRIO EMITIDO
6.  AGUARDANDO ESTOQUE
7.  ESTOQUE RESERVADO
8.  ENTREGUE À TRANSPORTADORA
9.  CANCELADO
10. TROCA
```

**Métodos envolvidos:**

- `B2CConsultaStatus` — disponibiliza os status cadastrados no Microvix ERP. Todos os demais métodos com status têm FK com este cadastro.
- `B2CCadastraPedido` — informar o status inicial em `id_status` (FK com `B2CConsultaStatus`).
- `B2CConsultaPedidos` — `id_status` retorna o status atual do pedido.
- `B2CConsultaPedidosStatus` — histórico de status de um pedido (cada alteração feita no Microvix ERP).

### SITUAÇÃO

Duas informações determinam se o pedido aparece na fila de faturamento do módulo B2C: **ativo** e **finalizado**. Se uma delas não for verdadeira, o pedido não aparece na listagem de compras efetuadas no B2C do ERP.

**Ativo** — binário: pedido ativo (1) ou não (0). Não ativo = cancelado. Quando houver cancelamento na Loja Virtual, é este campo que deve ser atualizado no Microvix. Pode ser alterado tanto no Microvix quanto na Loja Virtual (via API).

Métodos envolvidos: `B2CCadastraPedido` (informar/atualizar `ativo`), `B2CCancelaPedido`, `B2CConsultaPedidos`.

**Finalizado** — binário: indica se o fluxo do pedido já foi finalizado (1) ou não (0). Pode se dar por faturamento, entrega, encerramento por falta de pagamento etc. — definição do próprio cliente.

> ⚠️ Com exceção da situação inicial (informada em `B2CCadastraPedido`), a alteração dessa situação **só pode ser feita dentro do Microvix** e é apenas disponibilizada para consulta na API. **Não há método para a Loja Virtual atualizar** essa situação.

Método envolvido: `B2CConsultaPedidos` (propriedade `finalizado`).

---

## 8 Exemplos

### Consulta nos métodos de Saída

```xml
<?xml version='1.0' encoding='utf-8' ?>
<LinxMicrovix>
  <Authentication user="linx_b2c" password="linx_b2c"/>
  <ResponseFormat>xml</ResponseFormat>
  <IdPortal>18948</IdPortal>
  <Command>
    <Name>B2CConsultaProdutos</Name>
    <Parameters>
      <Parameter id="chave">243BEEAC-F846-4709-8505-51448251F64F</Parameter>
      <Parameter id="cnpjEmp">03411448000196</Parameter>
      <Parameter id="timestamp">NULL</Parameter>
    </Parameters>
  </Command>
</LinxMicrovix>
```

### Inclusão nos métodos de Entrada (SOAP)

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
  xmlns:tem="http://tempuri.org/"
  xmlns:linx="http://schemas.datacontract.org/2004/07/Linx.Microvix.WebApi.Importacao.Requests"
  xmlns:linx1="http://schemas.datacontract.org/2004/07/Linx.Microvix.WebApi.Business.Api"
  xmlns:linx2="http://schemas.datacontract.org/2004/07/Linx.Microvix.WebApi.Importacao">
  <soapenv:Header/>
  <soapenv:Body>
    <tem:Importar>
      <tem:request>
        <linx:ParamsSeletorDestino>
          <linx1:CommandParameter>
            <linx1:Name>chave</linx1:Name>
            <linx1:Value>243BEEAC-F846-4709-8505-51448251F64F</linx1:Value>
          </linx1:CommandParameter>
          <linx1:CommandParameter>
            <linx1:Name>cnpjEmp</linx1:Name>
            <linx1:Value>03411448000196</linx1:Value>
          </linx1:CommandParameter>
          <linx1:CommandParameter>
            <linx1:Name>IdPortal</linx1:Name>
            <linx1:Value>18948</linx1:Value>
          </linx1:CommandParameter>
        </linx:ParamsSeletorDestino>
        <linx:Tabela>
          <linx2:Comando>B2CCadastraClientes</linx2:Comando>
          <linx2:Registros>
            <linx:Registros>
              <linx:Colunas>
                <linx1:CommandParameter>
                  <linx1:Name>cod_cliente</linx1:Name>
                  <linx1:Value>NULL</linx1:Value>
                </linx1:CommandParameter>
                <linx1:CommandParameter>
                  <linx1:Name>doc_cliente</linx1:Name>
                  <linx1:Value>NULL</linx1:Value>
                </linx1:CommandParameter>
                <linx1:CommandParameter>
                  <linx1:Name>ativo</linx1:Name>
                  <linx1:Value>NULL</linx1:Value>
                </linx1:CommandParameter>
                <linx1:CommandParameter>
                  <linx1:Name>receber_email</linx1:Name>
                  <linx1:Value>NULL</linx1:Value>
                </linx1:CommandParameter>
              </linx:Colunas>
            </linx:Registros>
          </linx2:Registros>
        </linx:Tabela>
        <linx:UserAuth>
          <linx2:Pass>linx_b2c</linx2:Pass>
          <linx2:User>linx_b2c</linx2:User>
        </linx:UserAuth>
      </tem:request>
    </tem:Importar>
  </soapenv:Body>
</soapenv:Envelope>
```

---

## 9 Restrições

- O cliente precisa ter equipe especializada em infraestrutura/manutenção de WebService e XML.
- A Linx Microvix **não fará nenhum tratamento** nos dados fornecidos via WebService.
- A Linx Microvix **não fornece consultoria ou apoio** no formato de conexão ao WebService nem em linguagens de programação para consumo dos métodos. Testes de conexão serão feitos **com Postman** — nenhum outro aplicativo será considerado para validação.
- Só é possível vincular **um CNPJ por portal** para configuração da empresa que vai faturar os pedidos de venda.

## 10 Benefícios

- Acesso em tempo real à estrutura de dados do MicrovixERP;
- Acesso de qualquer lugar à API;
- Segurança: WebService restrito com usuário, senha e chave;
- Documentação de utilização da API;
- Atualização de melhorias automaticamente (*sem comunicado prévio*).

## 11 Documentação dos métodos

Documentação atualizada: `https://share.linx.com.br/x/2yINCg`

## 12 Entrega / Suporte

| Canal | Endereço |
|---|---|
| Chat | `https://cliente.linx.com.br/livechat/` |
| Abertura de chamados | `https://cliente.linx.com.br/webchamados/` |
| Telefones | 3003-6632 / 0800-718-6632 |

**Horário de atendimento:**
- Segunda a sexta: 08:30h às 22:00h (após 18:00h apenas faturamento parado)
- Sábados: 09:00h às 22:00h
- Domingos e feriados nacionais: 10:00h às 20:00h (exceto 25/12 e 01/01)

## 13 Ativação e condição comercial

| Processo | Esforço | Condição |
|---|---|---|
| Taxa de Ativação de Serviço | 30 horas | — |
| OS | 34 horas* | À vista |
| Manutenção | Mensal | Conforme negociação comercial |

\* Serviço vinculado ao item de Homologação. Para contratar, é necessário contato com o Gerente de Relacionamento.

---

## 18 Controle de revisão

| Rev. | Data | Item alterado | Revisado por |
|---|---|---|---|
| 1 | 23/11/2017 | Criação do documento | Thiago Rochadel |
| 2 | 11/06/2018 | Criação do item 2.6 | Roana de Mello |
| 3 | 23/06/2018 | B2CConsultaProdutosDetalhes | Isabelli Nicolau Cunico |
| 4 | 14/09/2018 | B2CConsultaProdutosCodebar / B2CCadastraPedido | Roana de Mello |
| 5 | 19/09/2018 | B2CConsultaClientesContatosParentesco / B2CCadastraClientesContatos | Roana de Mello |
| 6 | 26/09/2018 | Novo método B2CAtualizaCodigoRastreio | Roana de Mello |
| 7 | 24/10/2018 | B2CCadastraClientes; orientação sobre Status, Situação e Timestamp | Roana de Mello |
| 8 | 22/11/2018 | B2CConsultaProdutos — campos de frete | Roana de Mello |
| 9 | 07/02/2019 | Novo método B2CConsultaCodigoRastreio | Christhian S. Stoever |
| 10 | 25/07/2019 | Alteração em B2CCadastraPedido e B2CConsultaPlanos | Christhian S. Stoever |
| 15 | 15/10/2019 | peso_bruto, tipo_pessoa, data_criacao, centro_distribuicao, mensagem_falha_faturamento | Roney Kuntz |
| 16 | 11/11/2019 | Nome do grupo no item 2; campo depósito em B2CConsultaProdutosDetalhesDepositos | Roney Kuntz |
| 17 | 05/12/2019 | Inclusão de B2CConsultaProdutosStatus | Roana de Mello |
| 18 | 10/01/2020 | id_deposito_faturamento em B2CCadastraPedido | Roney Kuntz |
| 19 | 04/05/2020 | Observação em B2CConsultaProdutosTabelas | Roana de Mello / Danúbia Gattis |
| 20 | 02/06/2020 | somente_promocao_ativa; qtde_item e item_obrigatorio; orientações sobre timestamps e acesso ao ERP; B2CConsultaImagensHD; obs. em B2CCadastraClientes e B2CCadastraPedido | Roney Kuntz / Danúbia Gattis |
| 21 | 10/07/2020 | filtra_campanha em B2CConsultaProdutosPromocao | Danúbia Gattis |
| 22 | 30/07/2020 | B2CConsultaLegendasCadastrosAuxiliares | Danúbia Gattis / Roana de Mello |
| 23 | 25/08/2020 | B2CConsultaPedidosTipos; id_tipo_b2c e ecommerce_origem | Danúbia Gattis |
| 24 | 11/09/2020 | Obs. sobre campo "empresas" em B2CConsultaProdutosDetalhes | Danúbia Gattis |
| 25 | 24/09/2020 | Inclusão de B2CAtualizaPedidoStatus | Danúbia Gattis |
| 26 | 25/09/2020 | Atualização da anotação sobre status | Roana de Mello |
| 27 | 21/01/2021 | Atualização do documento | Thiago Rochadel |
| 28 | 02/02/2021 | Descrição do item empresa em B2CConsultaPedidos | Danúbia Gattis |
| 29 | 01/04/2021 | B2CCadastraImagensHD e B2CCadastraProdutosImagensHD | Danúbia Gattis |
| 30 | 03/08/2021 | Campos de commerce/publicação; métodos de Tags, Flags e Palavras-chave | Danúbia Gattis |
| 31 | 10/08/2021 | Credenciais de acesso — inclusão dos Headers | Danúbia Gattis |
| 32 | 11/08/2021 | descricao_b2c_tags; descricao_b2c_palavras_chave_pesquisa; tempo_preparacao_estoque | Danúbia Gattis |
| 33 | 16/09/2021 | Inclusão de B2CConsultaCNPJsChave | Danúbia Gattis |
| 34 | 03/08/2022 | endereco_cliente em B2CCadastraClientesEnderecosEntrega | Danúbia Gattis |
| 35 | 10/08/2022 | cliente_contribuinte, inscricao_cliente em B2CCadastraClientes | Danúbia Gattis |
| 36 | 29/11/2022 | Observações em B2CCadastraPedido e B2CCadastraPedidoItens | Danúbia Gattis |
| 37 | 28/02/2023 | Tipo do campo cod_autorizacao | Danúbia Gattis |
| 38 | 05/04/2023 | justificativa em B2CConsultaNFe | Danúbia Gattis |
| 39 | 17/05/2023 | marketplace_cnpj, marketplace_grupo, marketplace_loja | Danúbia Gattis |
| 40 | 26/05/2023 | Novo método B2CConsultaPedidosIdentificador | Danúbia Gattis |
| 41 | 11/07/2023 | somente_promocao_ativa e filtra_campanha | Danúbia Gattis |
| 42 | 08/09/2023 | somente_reserva (obs. 2 de B2CCadastraPedido) | Danúbia Gattis |
| 43 | 28/11/2023 | Fluxogramas de integração de pedidos | Ellen Cunha |
| 44 | 02/04/2024 | marketplace_full_url_xml; aceita_programa_fidelidade | Danúbia Gattis |
| 45 | 02/07/2024 | Campos de pagamento em B2CCadastraPedidoPlanos | Danúbia Gattis |
| 46 | 29/07/2024 | B2CConsultaClientesContatos; codigo_integracao_oms | Danúbia Gattis |
| 47 | 06/09/2024 | url_imagem_blob em B2CConsultaImagens e B2CConsultaImagensHD | Danúbia Gattis |
| 48 | 16/09/2024 | nProt, codigo_modelo_nf em B2CConsultaNFe | Danúbia Gattis |
| 49 | 18/09/2024 | tpAmb em B2CConsultaNFe | Danúbia Gattis |
| 50 | 10/10/2024 | b2c_marketplace; descricao_garantia e meses_garantia_fabrica; filtro tipo_codebar; B2CConsultaProdutosImagensURL | Juliana Fandaruff |
| 51 | 07/01/2024 | id_endereco_entrega em B2CConsultaPedidos | Danúbia Gattis |
| 52 | 22/01/2024 | envia_produtos_b2c, envia_estoque_b2c, integracao_oms; desconto_especial, principal; order_id, fulfillment_id; id_endereco_entrega_b2c; outlet; descricao_cupom_desconto, valor_seguro | Danúbia Gattis |
| 53 | 25/02/2025 | ncm e cest em B2CConsultaProdutos | Danúbia Gattis |
| 54 | 03/04/2025 | nome_plano → VARCHAR(100) em B2CConsultaPlanos | Danúbia Gattis |
| 55 | — | — | — |
| 56 | 28/05/2025 | Inclusão da URL de entrada para teste | Juliana Fandaruff |
| 57 | 24/06/2025 | codigo_adicional_reshop em B2CConsultaProdutos | Juliana Fandaruff |
| 58 | 21/08/2025 | usa_tef em B2CConsultaPlanos | Juliana Fandaruff |
