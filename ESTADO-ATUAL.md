# Estado atual — APTK Platform

Diário de estado do projeto. Complementa o CLAUDE.md (que descreve como o
código É); aqui fica o que está PENDENTE, o que foi DESCOBERTO fora do código
(ERP, gateway, dados) e as decisões em aberto. Atualizar a cada leva.

Última revisão: **2026-08-27** (fechamento da Microvix fase 1.5, etapa 5).

---

## 1. Microvix — diagnóstico do ERP (descoberta de 21/08/2026)

Tudo abaixo vem das respostas reais do WS gravadas em `microvix_logs`
(`microvix:descobrir` + `microvix:sync-produtos`, portal 18443, ambiente
`aceitacao`). Reconferido em 27/08 lendo os logs.

### 1.1 Catálogo: 1237 produtos, NENHUM liberado para B2C

`B2CConsultaProdutosStatus` devolve 1237 registros: 1207 ativos e 30 inativos,
**todos com `b2c=0`**. Consequência direta: `B2CConsultaProdutos` e
`B2CConsultaProdutosDetalhes` voltam vazios (cabeçalho sem `<R>`), o espelho
real está em zero (`microvix_sync_state.records_synced = 0` nos três métodos)
e **nada da fase 1.5 roda com dado real**. Só quem marca produto como B2C é a
Linx/gestão do ERP — não existe método de escrita para isso no WS.

Enquanto isso, o desenvolvimento usa `microvix:fake` (26 registros fictícios
na faixa `codigoproduto >= 900000`, 15 já vinculados aos 14 produtos do site).
Bloqueado em produção pelo próprio comando.

### 1.2 Zero tabela de preço

`B2CConsultaProdutosTabelas` volta vazio: **não há nenhuma tabela de preço
cadastrada no portal**, logo `B2CConsultaProdutosTabelasPrecos` também. Sem
tabela não existe `precovenda` — a conciliação de preço (`size_prices`) não
tem fonte, mesmo depois do B2C liberado. `MICROVIX_ID_TABELA_PRECO` segue
vazio no `.env` por isso.

### 1.3 Empresa APTK: envia produto, NÃO envia estoque

`B2CConsultaEmpresas` (com o `cnpjEmp` do `.env`) devolve uma única empresa:
`empresa=4`, APTK SPIRITS, CNPJ 36491550000106, Rua João Moura 1182,
Pinheiros/SP, com `envia_produtos_b2c=True` e **`envia_estoque_b2c=False`**.
Ou seja, além do B2C por produto, a empresa precisa ter o envio de estoque
ligado — senão `saldo` nunca chega e `size_stock` não se popula.

### 1.4 Cadastros auxiliares confirmados (candidatos para o `.env`)

Os parâmetros de faturamento que a fase 2 (`B2CCadastraPedido`) exige já têm
valor identificado, só não estão preenchidos no `.env`:

| Variável | Valor encontrado | Origem |
|---|---|---|
| `MICROVIX_EMPRESA` | `4` (APTK SPIRITS) | B2CConsultaEmpresas |
| `MICROVIX_COD_VENDEDOR` | `15` — vendedor "SITE", tipo V, ativo | B2CConsultaVendedores (103 registros) |
| `MICROVIX_ID_DEPOSITO` | `1` — "Estoque", disponível S; o `2` é "Devolvidos (com defeito)" | B2CConsultaProdutosDepositos |
| `MICROVIX_ID_STATUS_INICIAL` | `3` AGUARDANDO PAGAMENTO (ou `1` EM ABERTO); existem `0` SEM STATUS e `2` ENCERRADO | B2CConsultaStatus |
| `MICROVIX_ID_TIPO_ENCOMENDA` | `99` Transportadora, `41106` PAC, `40010` Sedex, `2500` Retirada na Loja (10 tipos) | B2CConsultaTipoEncomenda |
| `MICROVIX_PLANO_PAGAMENTO` | ver 1.6 — incompleto | B2CConsultaPlanos |
| `MICROVIX_ID_TABELA_PRECO` | **não existe** (1.2) | B2CConsultaProdutosTabelas |

Decisão pendente com a gestão: status inicial (3 ou 1) e qual tipo de
encomenda representa o frete do site.

### 1.5 Estrutura societária no ERP: Ice4Pros tem CNPJ próprio, Barín não existe

`B2CConsultaCNPJsChave` enxerga 12 CNPJs, todos no portal 18443 ("APTK
Spirits - Franqueadora"), classificação Cliente, `b2c=False` em todos:

- `empresa=1` SMALL BATCHES HOLDING S.A (40535175000118)
- `empresa=2` ICE4PROS (30057638000120) e `empresa=3` ICE4PROS FILIAL (30057638000200)
- `empresa=4` APTK SPIRITS (36491550000106) — a única no `.env`
- `empresa=5` NOVA SACRAMENTO, `6..10` APTK MALLS (Shops, Cidade Jardim,
  Tamboré, Leblon, Rio Sul), `11/12` SYLVIUS SP e RJ

Implicações:
- **Ice4Pros** (gelo) fatura por CNPJ separado. Estoque e pedido de gelo
  vivem em `empresa=2`, não em `4`. O código hoje assume UM `cnpjEmp`
  (`MicrovixClient` injeta `config('microvix.cnpj_emp')` em toda consulta).
  Vender gelo pelo site com ERP na frente exige suporte a multi-empresa —
  não previsto na fase 1/1.5.
- **Barín** não aparece em lugar nenhum: nem CNPJ, nem empresa. Se Barín for
  vendida pelo site, ou é faturada por outra empresa do grupo (qual?) ou
  não está no ERP.

### 1.6 Planos de pagamento APPMAX incompletos

`B2CConsultaPlanos` devolve 279 planos. Com "APPMAX" no nome só existem três:
`279` APPMAX CRÉDITO 1X, `280` 2X, `281` 3X (forma_pagamento=5, cartão).
**Não há plano APPMAX para Pix nem para boleto**, e cartão para acima de 3x.
O site oferece Pix, cartão e boleto — o pedido de Pix/boleto não teria plano
pra apontar na fase 2. Precisa ser criado no ERP pela gestão.

### 1.7 Ausência de portal de teste

`MICROVIX_ENV=aceitacao` aponta para `webapi-aceitacao.microvix.com.br`, mas
o que responde ali é o **portal real 18443 com dados reais** (os 12 CNPJs,
103 vendedores, 279 planos, 1237 produtos). `microvix:descobrir --teste`
(classificação 6) não devolveu portal de teste nenhum. Não há sandbox: toda
chamada de escrita da fase 2 (`B2CCadastraPedido`) vai cair no ERP de
verdade. Isso muda a estratégia de homologação da fase 2 — testar com pedido
marcado (`somente_reserva`, valor simbólico) e cancelar no ERP em seguida.

---

## 2. Catálogo do site (banco local, 27/08)

- **14 produtos, todos ativos, `stock_qty` chapado em 30** em todos. Nada
  esgota, nunca esgotou. O estoque real só vai existir quando `size_stock`
  for populado pela conciliação (hoje `null` nos 14).
- **5 produtos sem `size_prices`** (usam só `price`): `#7 Penicillin`
  (375 ml, 199,90), `#11 Cachaça Prata` (750 ml, 129,90), `#12 Cachaça Ouro`
  (750 ml, 159,90), `#13 Neutral Gin` (750 ml, 219,90), `#14 Vodka VDK`
  (750 ml, 169,90). Na 1ª conciliação com fonte `erp` o preço exibido desses
  cinco muda no ato (o `microvix:conciliar --dry-run` já marca isso).
- Pedidos: 35 no total, **7 `pending`** (3 pix, 2 boleto, 2 cartão) — ver §3.
- `subscription_plans` local ainda tem os 5 planos antigos (Admirador, Bronze,
  Prata, Ouro, Aged) ativos ao lado dos 4 do CLAUDE.md (Explorer, Collector,
  Connoisseur, Le Cercle). Conferir em produção se os antigos foram
  desativados.

---

## 3. Pendência: pedido `pending` de cartão com estoque preso

Janela residual do checkout (pré-existente, não é da fase 1.5): em
`Shop\CheckoutController`, o `placeFromCart` commita o pedido local e debita
estoque ANTES da chamada à Appmax; recusa do gateway é tratada por
compensação (`OrderService::cancelAndRestoreStock`). Se o processo morrer
entre o commit e a compensação (kill, OOM, deploy no meio), fica um pedido
`pending` com estoque debitado e nenhuma venda.

- Antes era invisível: `stock_qty` chapado em 30, nada esgotava.
- Com o ERP mandando no estoque (`size_stock`), pedido pending preso = produto
  sumindo da vitrine sem motivo.
- Pix e boleto se resolvem sozinhos pelo webhook de expiração da Appmax.
- **Saída combinada (não implementada):** comando que cancela pedidos
  `pending` de CARTÃO parados há mais de X minutos e devolve o estoque via
  `cancelAndRestoreStock` (idempotente, já devolve `size_stock`). Sem queue
  no projeto, vai de cron (`schedule:run`) no servidor.

---

## 4. O que falta para virar a chave (`MICROVIX_FONTE_VERDADE=erp`)

Código pronto e testado (99 testes). A virada é trocar uma linha no `.env`
do servidor — mas antes, nesta ordem:

1. **Linx/gestão:** marcar os produtos vendidos no site como `b2c=1` no ERP
   (hoje 0 de 1237) — §1.1.
2. **Linx/gestão:** ligar `envia_estoque_b2c` na empresa 4 — §1.3.
3. **Gestão:** criar ao menos uma tabela de preço no portal e colocar os
   produtos do site nela (ou decidir que preço continua sendo do site e a
   conciliação só grava saldo — o `MicrovixReconciler` hoje grava os dois;
   precisaria de flag) — §1.2.
4. **Paulo:** rodar `microvix:sync-produtos` e confirmar que o espelho real
   chega (`/admin/microvix`); apagar os fictícios com `microvix:fake --limpar`
   (bloqueado em produção — lá o espelho já nasce limpo).
5. **Paulo + gestão:** vincular cada `codigoproduto` a produto+volume em
   `/admin/microvix/produtos` (matcher só sugere).
6. **Paulo:** `microvix:conciliar --dry-run`, revisar os deltas de preço
   (especialmente os 5 sem `size_prices`, §2) com a gestão.
7. **Paulo:** implementar o comando do §3 antes da virada, porque a partir
   dela estoque preso aparece na vitrine.
8. **Decisão:** gelo (Ice4Pros, empresa 2) e Barín ficam fora do ERP na
   virada, ou a fase 1.5 ganha multi-empresa — §1.5.
9. Virar `MICROVIX_FONTE_VERDADE=erp` no `.env` do servidor +
   `php artisan config:cache`. A partir daí `/admin/produtos` e `/admin/estoque`
   travam preço/estoque de produto vinculado.

Para a **fase 2** (pedido → ERP) somam-se: preencher os 7 parâmetros do §1.4,
planos APPMAX de Pix/boleto (§1.6) e a estratégia de homologação sem sandbox
(§1.7).

---

## 5. Histórico — varredura de 2026-08-20 (mantida como referência)

> Snapshot ANTERIOR ao commit `0c07f3a` (Microvix fase 1). O que mudou desde:
> Microvix tem espelho, descoberta, matcher, conciliação, estoque por volume
> e painel (fases 1 e 1.5); `Shop\FreteController` existe (casca fixa R$ 10);
> endereço no pedido virou snapshot em colunas de `orders`
> (`add_shipping_snapshot_to_orders_table`) + cpf em `users`; suíte de testes
> roda em sqlite :memory: com testes de domínio.

### Git (na época)

- Último commit então: `6215a68` de 23/07/2026 ("Alterações 23/07").
- Histórico antigo pouco informativo ("A", "UP", "S"). Do `0c07f3a` em diante
  as mensagens descrevem o que mudou.

### Schema (derivado das migrations)

Nenhuma tabela tem foreign key de verdade — os `*_id` são `unsignedBigInteger`
com índice, comentados "sem FK".

**users**: id, name, email (unique), email_verified_at, password, role (default
`customer`, indexado), phone, cpf, remember_token, timestamps.

**products**: id, category_id, name, slug (unique), description,
short_description, base, abv (tinyint %), sizes (json), size_prices (json),
size_stock (json, nullable), price, compare_price, sku, stock_qty, weight (kg),
active, featured, timestamps.

**addresses**: id, user_id, label, name, street, number, complement,
neighborhood, city, state (UF), zipcode, is_default, timestamps.

**orders**: id, user_id, status (default `pending`), subtotal, discount,
shipping_cost, total, payment_method, payment_status (default `pending`),
appmax_order_id, appmax_customer_id, appmax_pay_reference, payment_details
(json), paid_at, shipping_method, notes, snapshot `ship_*`, campos microvix,
timestamps.

**order_items**: id, order_id, product_id (nullable), product_name,
product_sku, size, qty, unit_price, total_price, timestamps.

**microvix_products**: codigoproduto, product_id, size, referencia,
codauxiliar1, nome_produto, saldo, preco, ativo, raw, synced_at.
Mais: microvix_sync_state, microvix_logs, microvix_reconcile_log.

Outras: product_categories (parent_id auto-referente), product_images,
coupons, leads, settings, subscription_plans, subscriptions, posts, mais as
padrão do Laravel.

### Pela metade

- **B2B**: rota `/b2b/ping` + middleware + role. Pasta `resources/views/b2b`
  sem controller.
- **Frete**: casca fixa R$ 10 em `Shop\FreteController@cotar`; sem conta
  SuperFrete.
- **Assinatura recorrente**: `subscriptions` não tem NENHUM campo `appmax_*`.
  Cobrança recorrente não está ligada ao gateway — assinatura vive só no
  banco. Guarda `shipping_address` como texto próprio, separado de
  `addresses`.

### Miudezas

- `composer.json` esqueleto Laravel + Guzzle/Sanctum/Tinker. Appmax é HTTP
  direto via Guzzle; nenhum SDK.
- Zero Jobs, queue não usada — e-mail do Custom sai síncrono; sync e
  conciliação do painel Microvix também são síncronos.
- `api.php` só Sanctum. `b2b.php` só `/ping`.
- `/dashboard` do Breeze ativo com a view genérica.
- `/appmax/callback` é closure que loga query+body inteiros no laravel.log
  (inclusive credenciais de merchant) — provisória.
- `welcome.blade.php` default no repo; `teste.blade.php` órfão; 5 CSS
  herdados sem uso.
- `public/fonts/` tem um `Amithen.woff2` que o CLAUDE.md não menciona.
- `.env` local: `APPMAX_ENV=sandbox` com `APPMAX_MERCHANT_TOKEN` vazio.

### Perguntas em aberto (as que ainda valem)

1. **Frete**: implementar SuperFrete (precisa de conta/token) ou manter a
   casca até a decisão?
2. **Imagens**: consertar o `.gitignore` para voltar a versionar uploads
   novos, ou parar de versionar e sincronizar por outro canal (rsync/S3)?
3. **Assinatura**: renovação é manual mesmo, ou falta integrar cobrança
   recorrente na Appmax?
4. **B2B**: escopo mínimo antes do primeiro controller.
5. **Sandbox Appmax**: seguia fora do ar (504) — como testar pagamento sem
   ele? (Mesmo problema do Microvix, §1.7.)
