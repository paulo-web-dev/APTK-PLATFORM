# Estado atual — APTK Platform

Diário de estado do projeto. Complementa o CLAUDE.md (que descreve como o
código É); aqui fica o que está PENDENTE, o que foi DESCOBERTO fora do código
(ERP, gateway, dados) e as decisões em aberto. Atualizar a cada leva.

Última revisão: **2026-10-25** (§13 — Custom: 7 bebidas e nome de exibição).
Antes: 2026-10-20 (§13 — leva 11, Custom vira compra direta;
§12 — fotos de produto em fundo branco com rótulo atual; fotos de ambiente com
rótulo antigo trocadas). Antes: 2026-10-19 (§11 — leva 10, texto e layout de 6 páginas pelo
briefing da gestão; §10 — ativação só com preço e foto no padrão, importador sem
`active`/galeria, `produtos:aplicar-fotos`). Antes: 2026-10-18
(§9 — Appmax com credenciais do painel, loja própria) e 2026-10-11 (leva 08,
§8 — frete estimado PAC/SEDEX, catálogo completo, admin; Microvix fora, §8.4).

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

> Desatualizado desde a leva 07 (§6): o catálogo agora vem da planilha.

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
- **Implementado (30/08):** `pedidos:cancelar-cartao-preso` cancela pedidos
  `pending` de CARTÃO parados há mais de 60 min (`--minutos` ajusta,
  `--dry-run` só lista) via `cancelAndRestoreStock`, anota o motivo no
  `notes` do pedido e loga. Janela de 60 min é proposital: cartão que chegou
  a ser cobrado recebe `order_approved` via webhook em minutos — pending de
  cartão velho é processo morto, não venda. Agendado a cada 15 min no
  `Kernel`, mas **depende do cron do servidor chamar o scheduler** — conferir
  se existe a linha
  `* * * * * cd /var/www/aptk && php artisan schedule:run >> /dev/null 2>&1`
  no crontab; sem ela o comando nunca roda sozinho.

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
7. ~~**Paulo:** implementar o comando do §3~~ — feito em 30/08
   (`pedidos:cancelar-cartao-preso`); falta só confirmar o cron do
   `schedule:run` no servidor (§3).
8. **Decisão:** gelo (Ice4Pros, empresa 2) e Barín ficam fora do ERP na
   virada, ou a fase 1.5 ganha multi-empresa — §1.5.
9. Virar `MICROVIX_FONTE_VERDADE=erp` no `.env` do servidor +
   `php artisan config:cache`. A partir daí `/admin/produtos` e `/admin/estoque`
   travam preço/estoque de produto vinculado.

## 4.1 Fase 2 (pedido → ERP): código pronto, trava fechada (31/08)

Implementada em 4 etapas (commits `d06cb89`…`1bbd60a`), tudo contra fixtures —
nenhum envio real foi feito. `MICROVIX_ENVIA_PEDIDO=false` simula: o XML que
seria enviado fica em `microvix_logs` (`simulated=true`), visível na trilha do
pedido em `/admin/microvix/pedidos/{id}`. **Desde a leva 08 (§8.4), com a trava
fechada os disparos automáticos nem enfileiram** — a simulação só roda por
reenvio manual no painel.

Para LIGAR (`MICROVIX_ENVIA_PEDIDO=true`), antes precisa:

1. **Gestão:** criar planos APPMAX de Pix e boleto no ERP (§1.6) e preencher
   `config/microvix.php` (`planos_pagamento`) — hoje pedido pix/boleto para em
   erro com mensagem clara, de propósito.
2. **Gestão:** decisões pendentes do §1.4 preenchidas no `.env` do servidor:
   `MICROVIX_ID_STATUS_INICIAL` (3 ou 1) e `MICROVIX_ID_TIPO_ENCOMENDA`;
   mais tabela de preço (§1.2) — sem ela o pedido não fatura.
3. **Servidor:** `QUEUE_CONNECTION=database` no `.env` + `php artisan migrate`
   (tabela `jobs`) + `config:cache`. O worker roda pelo cron do `schedule:run`
   que já existe — conferir que a linha do crontab está lá (§3).
4. **Homologação sem sandbox (§1.7):** primeiro envio real com pedido de valor
   simbólico, `somente_reserva=1`, conferindo em `/admin/microvix` a resposta
   crua do Importador — **o parse de sucesso é PALPITE**
   (`MicrovixPedidoSender::avaliaRespostaImporta` + fixtures `importa-*.xml`);
   ajustar parse/fixtures com o formato real e cancelar o pedido no ERP.

O que falta de código (fora do escopo da fase 2): retorno do ERP → site
(status, rastreio, NF-e) é a fase 3.

---

## 5. Faxina de repositório (13/09/2026)

Duas pendências antigas do CLAUDE.md fechadas, sem tocar em código de aplicação
(145 testes seguem passando):

- **Upload de imagem voltou a versionar.** O `*` de `storage/app/.gitignore` vale
  recursivamente, então o `!public/` de lá só liberava a pasta, não o conteúdo —
  imagem nova em `products/` ou `categories/` era ignorada em silêncio e não
  chegava ao servidor pelo deploy (as 33 antigas tinham entrado com `git add -f`).
  `storage/app/public/.gitignore` agora re-inclui as duas pastas explicitamente
  (`!products/` + `!products/**`, idem `categories/`). Conferido com
  `git check-ignore`: arquivo novo nas duas pastas aparece no `git status`; o
  resto de `storage/app/public/` continua ignorado. **Pasta de upload nova ali
  precisa do mesmo par de linhas** — senão volta a sumir calado.
- **Órfãos removidos:** `resources/views/teste.blade.php` e os cinco CSS herdados
  de outro projeto em `public/css/` (`assessment`, `report`, `result`,
  `foundation`, `marketing`). Nenhum tinha referência em view, controller, rota
  ou config — a única menção era o texto da própria `teste.blade.php`.

---

## 6. Leva 07 — catálogo pela planilha, sem Linx (07/10/2026)

A gestão quis os produtos no ar sem esperar o Microvix. Fonte:
`database/data/catalogo-planilha-2026-10.csv` (uma linha por produto + volume).
Comando `catalogo:importar-planilha [--dry-run]`, idempotente.

- **Regras:** o CSV atualiza o que tem valor e nunca remove o que já está à
  venda (volume existente sem preço na planilha mantém volume e preço). **Ativo
  só com preço E foto do pacote novo (FUNDO PRETO)** — foto antiga do site não
  conta, e a reexecução não reativa produto só com foto antiga. Produto
  existente mantém slug e categoria; novo nasce com slug = grupo e `stock_qty`
  30 (placeholder; 0 = esgotado na vitrine). `managedByErp()` é pulado; produto
  fora do CSV não é tocado, exceto `cachaca-prata` e `cachaca-ouro`
  (`DESATIVAR_FORA_DA_PLANILHA`: só foto antiga 227x520, só o `active` muda).
  SKU = o do volume padrão (maior à venda) quando a planilha tem.
- **Mapas no comando:** slug antigo → grupo (`squadra-amendoas`,
  `squadra-coco`, `neutral-gin`, `vodka-vdk`) e grupo → categoria dos novos.
- **Imagens:** origem em `public/img/fotos_produtos/FUNDO PRETO/BAIXA/` (FORA do
  git, `.gitignore`); geradas em
  `storage/app/public/products/{grupo}-{vol}-{frente|verso}.jpg`, 1200 px no
  lado maior, JPG 82 (GD). Galeria só com volumes à venda, do maior (o
  `defaultSize()`) pro menor; principal = frente do volume padrão, ou do maior
  à venda com foto. Produto sem volume à venda guarda todas as fotos. Produto
  que recebe foto nova fica com galeria só do pacote novo: garrafa antiga e
  `-serve` saem (só a linha; o arquivo fica). Sem foto nova, imagens intocadas.
  Destino existente é reaproveitado — **em produção o comando roda sem as fotos
  de origem e sem GD**. `Fundo Preto_034` (Drink Perfeito, fundo azul) pulada.
- **Resultado local:** 47 produtos, **9 ativos** (boulevardier, cosmopolitan,
  dry-martini, fitzgerald, neutral-gin, negroni-classico, old-fashioned,
  sur-lorange, vermute-circollo-rosso). 60 imagens (3,7 MB).
- **Saíram do ar por "só foto antiga"** (têm preço, esperam foto nova): caju-amigo,
  squadra-amendoas, squadra-coco, limoncello, moscow-mule, vodka-vdk,
  penicillin. Mais as duas cachaças, fora da planilha.
- **Volumes mantidos sem preço na planilha** (lista para a gestão): aptk-gin-2
  750 (219,90), licor-de-amendoas-squadra 750 (159,90), licor-de-coco-squadra
  375 (97,44, fora da planilha) e 750 (149,90), limoncello 375 (97,44) e 750
  (149,90), sur-lorange 750 (209,90).
- **36 inativos do CSV** esperando a gestão: 12 sem foto, 7 só com foto antiga,
  8 sem preço, 9 sem nenhum dos dois (lista no resumo do comando). Licor Cacau Show (42,79) e Borelli
  (41,07) entraram com preço com cara de custo — confirmar antes de ativar.
- **CSS:** token `--color-bg-media` (= `--color-ink`) no fundo da foto do card,
  da página de produto, das miniaturas e do "Você também pode gostar" (antes
  `#0D0A06` hardcoded, que fazia moldura em volta da foto preta).
- **Local:** `extension=gd` descomentado no `php.ini` do XAMPP.

**Deploy:** depois do `reset --hard`, `php artisan catalogo:importar-planilha
--dry-run`, conferir, e rodar sem `--dry-run`.

**Pendência — admin apaga imagens:** resolvida na leva 08 (§8.3). As regras
de ativação e de foto antiga desta seção foram substituídas pela §8.2 e
depois pela §10 (o importador não ativa nem mexe em galeria).

---

## 8. Leva 08 — frete calculado, catálogo completo e admin (11/10/2026)

Três commits, um por bloco (`f8acf17` frete, `8d2c270` placeholder/import,
`99c3175` admin + Microvix oculto). 186 testes passando.

### 8.1 Frete estimado (substitui a casca de R$ 10)

- `FreteService` + `config/frete.php`. Envio próprio a partir de SP capital:
  tabela de referência dos Correios por região + **20%**, arredondado pra cima
  no centavo; prazo = tabela + `dias_manuseio` (2). Opções: PAC e SEDEX.
- Peso por item = ml do volume / 1000 + 1 kg de embalagem
  (`embalagem_por` item|pedido), total arredondado pra cima ao kg inteiro.
  Volume sem ml legível usa `peso_padrao_kg` — **hoje nenhum produto cai
  nisso** (todos os volumes são "N ml"; o Ice4Pros não tem produto no banco).
- Região pela faixa de CEP da UF. SP: 01000–09999 = capital (inclui a Grande
  SP, decisão da gestão), 10000–19999 = interior.
- O checkout cota em `/frete/cotar` (mesmo formato `{opcoes: [...]}`, com
  `codigo`) e mostra as opções, o resumo e as parcelas recalculadas. No submit
  só o código da opção vem do front: o `OrderService` recalcula valor e prazo
  do CEP. Grava `shipping_cost`, `shipping_method` (pac|sedex) e
  `shipping_days` (migration nova, nullable). A Appmax já recebia
  `shipping_value`.
- Frete com opção e prazo na tela de sucesso, em meus pedidos e no admin
  (detalhe e listagem). **Não existe e-mail de pedido** (o único mailable é o
  do Custom).
- Quando a SuperFrete entrar: trocar só a implementação do `FreteService`.

### 8.2 Catálogo completo com placeholder

> Substituído pela §10: o importador não decide mais `active` nem galeria, e
> produto só com placeholder fica inativo.

- `catalogo:importar-planilha`: **ativo = ao menos 1 volume com preço**; foto
  deixou de ser condição. A foto antiga do site (`products/{slug}.jpg` e
  `-serve.jpg`) sai da galeria sempre (só a linha, o arquivo fica), inclusive
  das cachaças. Upload do admin (nome hash de 40 caracteres) nunca é tocado e
  fica na frente do pacote em reexecução.
- **Cacau Show e Borelli:** preço apagado no CSV E na constante
  `PRECO_DESCARTADO` do comando — só apagar no CSV não bastava, porque a regra
  "nunca remove o que está à venda" manteria o 42,79/41,07 do banco. Ficam
  inativos "sem preço (descartado, confirmar com a gestão)"; quando a gestão
  confirmar, voltar o preço ao CSV e tirar o grupo da constante.
- **Resultado local:** 45 do CSV, **26 ativos** (17 deles com placeholder), 19
  inativos (17 sem preço + os 2 descartados), mais as 2 cachaças desativadas.
- `<x-product.image>` (foto ou placeholder) e `<x-product.placeholder>`
  (moldura 2:3 com `<x-brand.symbol>`, "Produto ainda sem foto"). CSS em
  `public/css/aptk-produto.css`, linkado nos layouts public, app e admin.
  Usado em: card do catálogo, página do produto (principal + miniaturas),
  relacionados, carrossel da home, carrinho, checkout, meus pedidos e
  listagem/editar do admin.
- **Carrossel da home só leva produto com foto** (destaque, depois nome).

### 8.3 Admin

- Listagem com categoria, SKU, menor preço, placeholder e filtro
  Todos/Ativos/Inativos.
- Editar: salvar sem mudar nada deixa o registro idêntico (teste lê o form
  renderizado e reenvia). Preço por volume descasado agora é erro (antes zerava
  calado). Produto sem preço não pode ser ativado.
- `handleImage`: o upload **adiciona** como principal (pendência da §6
  resolvida). Remover por imagem: apaga a linha sempre e o arquivo só se for
  upload do admin.

### 8.4 Microvix fora de operação

- Com `MICROVIX_ENVIA_PEDIDO=false` (ou ausente), checkout, webhook e
  cancelamento **nem enfileiram** o `EnviarPedidoMicrovix`
  (`EnviarPedidoMicrovix::integracaoLigada()`). Antes enfileiravam e o sender
  validava os pré-requisitos mesmo com a trava fechada: sem tabela de preço e
  sem plano Pix/boleto no ERP, todo pedido real cairia em `erro` e acenderia o
  banner. A simulação de XML (`simulated=true`) agora só acontece por reenvio
  manual no painel.
- Banner, badge e item "Microvix (ERP)" do menu só aparecem com a integração
  ligada. As rotas `/admin/microvix/*` seguem abertas por URL direta.
- Código e testes do Microvix intactos (fases 1, 1.5 e 2).
- **Pendência para quando a integração voltar:** `tipo_frete` do
  `B2CCadastraPedido` hoje é fixo (`MICROVIX_ID_TIPO_ENCOMENDA`). Com PAC/SEDEX
  no pedido, mapear `orders.shipping_method` → tipo de encomenda do ERP
  (`pac` → 41106, `sedex` → 40010, que já existem, §1.4).

### 8.5 Deploy da leva 08

Depois do `reset --hard`: `php artisan migrate --force` (cria
`orders.shipping_days`), `php artisan catalogo:importar-planilha --dry-run`,
conferir os 26 ativos, rodar sem `--dry-run`, e `config:cache`
(`config/frete.php` novo).

---

## 9. Appmax — credenciais pelo painel, loja própria (18/10/2026)

- A APTK deixou o fluxo de instalação de app e usa as credenciais da própria
  loja geradas no painel (menu **API**, doc `guides/meus-tokens`). Uma chamada
  em `/oauth2/token` com o par do `.env` e pronto.
- `.env`: `APPMAX_CLIENT_ID`, `APPMAX_CLIENT_SECRET`, `APPMAX_AUTH_URL`,
  `APPMAX_API_URL`, `APPMAX_EXTERNAL_ID` têm prioridade. Vazios, vale o
  legado (URLs por `APPMAX_ENV`, credenciais e external_id em `settings`).
  O script do Appmax JS segue o ambiente da `APPMAX_API_URL`.
- **Rotas do fluxo de instalação, fora de uso:** `/appmax/health`
  (`AppmaxInstallController@health`, recebia as credenciais do merchant no
  health check) e `/appmax/callback` (closure que loga a query e o corpo).
  Ficam no código, mas nada nesse modelo chama essas rotas. Candidatas a
  remoção numa faxina — o callback ainda loga payload inteiro.
- Local: o `APPMAX_CLIENT_ID/SECRET` do `.env` de desenvolvimento são as
  credenciais do APP (fluxo antigo). Com a prioridade nova elas passam a ser
  usadas como merchant e a autenticação falha — trocar pelo par de uma loja
  do sandbox (painel `breakingcode.sandboxappmax.com.br` → API) ou apagar.
- **Webhook do painel** (painel → API → Webhooks, doc `guides/webhooks-painel`):
  `{environment, event, data}`, `event` em PascalCase, `data.id` = pedido
  Appmax, `User-Agent: apphook`. **Sem assinatura**: a defesa é o token no
  caminho da URL + confirmação na API — o controller consulta
  `GET /v1/orders/{id}` e só muda status se a situação bater com o evento.
  Consulta falhou → responde 503 e a Appmax retenta (4 entregas: +10s, +100s,
  +~17 min). `OrderAuthorized` (antifraude) não marca pago. Estorno parcial e
  chargeback ganho só viram incidente no pedido. Formato Appstore segue aceito.

---

## 10. Ativação só com foto no padrão (19/10/2026)

> **Substituída em parte pela §12:** o padrão agora é fundo branco (rótulo
> atual); o pacote fundo preto não conta mais.

A gestão quis na loja só produto com foto. Chegou um acervo novo em
`public/img/fotos_produtos/375ML|750ML` com `manifesto.json` (SKU → frente/verso),
mas é outra sessão (Canon 7D, dez/2023–jan/2024), **fundo branco**, quase tudo
quadrado — decisão: **não vai pro site** (dark fixo). O padrão segue o pacote
**FUNDO PRETO** (3000x4500) da leva 07, que já está na galeria.

- **Divisão de responsabilidade:**
  - `catalogo:importar-planilha`: só nome, volumes, preços, SKU e categoria.
    Saíram `--fotos`, geração de imagem, galeria, `active` e a desativação das
    cachaças. Produto novo nasce inativo. Preço que some de produto ativo só
    gera aviso ("Ativos e sem preço"). Colunas `foto_*` do CSV ignoradas.
    **Fora do deploy**: rodar só quando a planilha mudar.
  - `produtos:aplicar-fotos`: dono da ativação e da galeria. Ativo =
    `Product::podeAtivar()` = `temPreco()` E `temFotoNoPadrao()`. Foto no
    padrão: pacote fundo preto `{grupo}-{N}ml-{lado}.jpg`,
    `foto-preto-{sku}-{lado}.jpg` (manifesto fundo preto) ou upload do admin.
    `--sem-galeria` só ativa/desativa. Sem a opção, monta galeria do manifesto
    (recorte central 2:3, 800x1200, JPG 82) e **recusa manifesto que não seja
    `fundo=preto`** (campo `fundo` da entrada ou `--fundo=preto`). SKU casa
    com `products.sku` ou com o SKU do volume na planilha; `G750.`/`G2750.`
    são aliases (ponto final ignorado). Produto `teste` intocado. Backup de
    ativo + galeria em `storage/app/fotos-manifesto/backup-*.json` (fora do
    git) antes de gravar; `--reverter=ultimo` (ou nome do arquivo). Relatório:
    SKU sem produto, entradas sem SKU (só lista, não cria), com foto e sem preço.
  - Admin: mesma regra. Sem preço → mensagem de sempre; sem foto no padrão →
    "Envie uma foto do produto antes de ativar." A foto enviada no mesmo
    salvar conta (`hashName()` do upload).
- **Resultado local** (`--sem-galeria`): **9 ativos** (boulevardier,
  cosmopolitan, dry-martini, fitzgerald, negroni-classico, neutral-gin,
  old-fashioned, sur-lorange, vermute-circollo-rosso); **17 desativados** por
  falta de foto (vodka-vdk, caipirinha-arazul, caju-amigo, cajuzaco-barin,
  canarinha, coffee-negroni, espresso-martini, squadra-amendoas, squadra-coco,
  limoncello, limoncello-barin, manhattan, moscow-mule, negroni-aged,
  negroni-barin, negroni-piselli, penicillin). Com foto e sem preço, seguem
  inativos: aptk-gin-1, negroni-jerez, scarlatti, vermute-circollo-bianco,
  ale-tonic, gallo-tropicalle, vermute-circollo-dry, voglio-apperitivo.
  Galeria intocada (60 imagens). `--reverter=ultimo` testado (26 → 9 → 26 → 9).
- **Manifesto (pasta fora do git):** marcado `fundo: "branco"` em todas as
  entradas, com `obs` nas que existem só em FUNDO PRETO: Gin #1 375
  (`Fundo Preto_028/029`), Gallo 750 (`037/038`), Ale Tonic 750 (`039/040`);
  Drink Perfeito 375 (`034`) existe, mas fundo azul, fora do padrão. NC750 só
  existe no manifesto branco. Detalhes no `CLAUDE.md` da pasta.
- **Pendência — foto fundo preto nova:** com o importador fora da galeria,
  nada gera foto do pacote sozinho. Foto fundo preto nova precisa de um
  manifesto (`fundo=preto`) apontando pro `FUNDO PRETO/` e
  `produtos:aplicar-fotos` sem `--sem-galeria` (os arquivos saem como
  `foto-preto-{sku}-{lado}.jpg`). Atenção: nesse modo a galeria do produto
  passa a ser só a do manifesto (+ upload do admin) — as fotos do pacote antigo
  daquele produto saem da galeria (só a linha).

**Deploy:** depois do `reset --hard`, `php artisan produtos:aplicar-fotos
--sem-galeria --dry-run`, conferir 9 ativos / 17 desativados, rodar sem
`--dry-run`. Não rodar o importador.

---

## 11. Leva 10 — texto e layout pelo briefing da gestão (19/10/2026)

Briefing: `docs/briefing/2026-10-site-copy.pdf` (fora do git). As imagens do PDF
são só referência de composição — nenhuma foi usada no site. Um commit por página
(0 base, 1 Quem Somos, 2 Home, 3 Loja, 4 Custom, 5 Clube, 6 Eventos).

- **Base:** componentes editoriais em `aptk.css` e `<x-photo-slot>` (ver
  CLAUDE.md). Banda antes do rodapé com o texto novo ("Os melhores encontros se
  transformam em histórias."), sem CTA. Rodapé intocado.
- **Quem Somos:** texto da Small Batches (veio em caixa alta, publicado em caixa
  normal), hero com a citação, trajetória 2016/2021 com foto, "No que
  acreditamos", ecossistema com cards de foto. Âncora `#onde-encontrar`.
- **Home:** vídeo do Negroni vira o fundo do hero (poster =
  `img/aptk/hero-negroni-poster.jpg`, frame do próprio vídeo); faixa 2016 /
  Brasil / Autoral / Feito para compartilhar no lugar dos números provisórios;
  "Um universo de possibilidades" (Explore a APTK + Nosso ecossistema); vitrine
  editorial no lugar do carrossel promocional; saiu o modal do Clube. Setas do
  carrossel de produtos no meio da foto no celular.
- **Loja:** capa nova; migration renomeia só `name` das categorias (Clássicos,
  Criações Autorais, Bases & Ingredientes, Barín, Ice4Pros); slugs iguais.
- **Custom:** hero "Sua história. Nossa alquimia.", jornada 01/02/03, seção
  "Crie seu APTK" com o formulário reordenado (mesmos campos e validação), Full
  Custom com texto + foto. Rótulos em Espumante/Cuba Libre (tokens).
- **Clube:** hero novo com `clube-hero.jpg`, CTA → `#planos`, 01/02/03.
- **Eventos:** view própria `shop.eventos` (ocasiões, soluções, processo,
  fechamento sem "Ver a carta"); portfólio vazio, não aparece.
- **Imagem nova:** `img/aptk/ice4pros-cubo.jpg` (frame de 4,6 s do
  `video/ice4pros-cubo.mp4`, liberado pelo Paulo).

### 11.1 Provisórios (voltar quando a gestão mandar)

**Fotos** (comentário `{{-- FOTO: ... --}}` em cada lugar):

| página / bloco | foto provisória | o que o briefing pede |
|---|---|---|
| Quem Somos · 2016 | `featured-negroni.jpg` (rótulo antigo) | garrafa/balcão da era Apothek |
| Quem Somos · card APTK Spirits | `franquias-hero.jpg` (§12) | produto ou loja APTK |
| Quem Somos · card Barín | `cat-cosmopolitan.jpg` (rótulo antigo) | garrafa ou serviço da Barín |
| Quem Somos · galeria | `clube-hero.jpg`, `cat-evento.jpg` (§12) | — |
| Home · vitrine Collabs | `cat-evento.jpg` | fotos das collabs / vídeo Montblanc |
| Home · vitrine Clube | `cat-pessoas.jpg` | encontro real do Clube |
| Home · vitrine Lojas | `loja-aptk.jpg` | foto arquitetônica autoral |
| Home · vitrine Custom | `cat-dry-martini.jpg` | close de garrafa personalizada + embalagem |
| Custom · hero (2 fotos) | `cat-pessoas.jpg` (§12), `about-loja-3.jpg` | Presentes & Celebrações / Marcas & Projetos Especiais |
| Custom · Full Custom | `cat-dry-martini.jpg` | garrafa com rótulo de marca parceira + embalagem premium |
| Eventos · Bar APTK | `clube-hero.jpg` (§12) | — |
| Eventos · Gifting | `featured-negroni.jpg` (rótulo antigo) | kit/presente de evento |
| Eventos · Brand Experiences | `cat-evento.jpg` | ativação de marca em evento |
| Eventos · Customização | `cat-fitzgerald.jpg` (rótulo antigo) | garrafa com rótulo personalizado de evento |

Nenhuma foto aparece duas vezes na mesma página (incluindo os arquivos
duplicados com nome diferente: `loja-aptk`=`sobre-loja`,
`cat-pessoas`=`eventos-hero`=`sobre-pessoas`, `hero-bartender`=`sobre-barman`,
`about-loja-2`=`parceiros-hero`, `about-loja-3`=`franquias-hero`,
`cat-loja`=`sobre-interior`). As views `shop.about` e `shop.sobre` não têm rota.

**Textos que não vieram no texto do briefing:**
- Da imagem de referência (aprovados pelo Paulo): banda do rodapé, título da
  trajetória, CTAs do hero da Home, faixa 2016/Brasil/Autoral, os 4 cards da
  vitrine editorial, CTA "Faça parte do clube", legendas "Presentes &
  Celebrações" / "Marcas & Projetos Especiais" do Custom.
- Inventados (confirmar): link "Explorar" dos cards da Home; "Conhecer a Barín" /
  "Conhecer a Ice4Pros"; título do portfólio "Eventos que já levaram a nossa
  assinatura."; eyebrows "Eventos", "Portfólio", "Full Custom".
- Caixa de frase onde o briefing tinha Title Case: "Feito para o ritmo do bar",
  "O gelo faz a diferença", "Alquimia de histórias". Nomes de card ficaram como
  no briefing.
- Confirmação pendente: papel de "Descubra a APTK" (p.10) e "Clássicos Autorais"
  (p.16 — hoje a categoria se chama "Clássicos"; mudar é uma migration de uma
  linha).

**Links das marcas** (`config/marcas.php` → `links`): APTK → Instagram
@aptkspirits; Barín → `/barin` e Ice4Pros → `ice4pros.com.br` até chegarem os
Instagrams.

**Clube — pontos "em breve":** o sistema não tem pontos. Onde o briefing (e a
página antiga) prometia pontos/10%, texto "em breve" sem número: passos 01 e 02
da página e perks do Explorer e do Collector (seeder + migration
`2026_10_19_100001`, com down). Voltar quando a gestão decidir.

**Eventos — portfólio:** `PageController::PORTFOLIO_EVENTOS` vazio. Falta da
gestão: nome, cliente, data, local, foto e autorização de uso.

**Local:** os planos antigos do Clube (Admirador, Bronze, Prata, Ouro) seguem
ativos no banco local porque o `ClubePlanos2026Seeder` não rodou aqui.

**Deploy da leva 10:** `migrate --force` (nomes das categorias + perks do
Clube), `config:cache` (`config/marcas.php`), `view:clear`.

---

## 12. Fotos de produto em fundo branco, rótulo atual (20/10/2026)

O pacote FUNDO PRETO (leva 07) mostra o **rótulo antigo** (ex.: Cosmopolitan
rosa). As fotos de `375ML/` e `750ML/` (fundo branco) têm o rótulo atual: o site
passa a usar essas. **Substitui a decisão da §10 de recusar fundo branco.**

- **Regra:** `Product::ehFotoNoPadrao` = `products/foto-branco-{chave}-{lado}.jpg`
  ou upload do admin. Pacote `{grupo}-{N}ml-{lado}.jpg` e `foto-preto-*` não
  contam mais. Chave = SKU em minúsculas, ou `{grupo}-{N}ml` em entrada sem SKU.
- **`produtos:aplicar-fotos`:** galeria só de manifesto `fundo=branco`
  (`--fundo=branco`); entrada sem SKU casa pelo campo `grupo` (grupo da planilha
  / slug do produto, com `SLUG_ANTIGO`). Pacote fundo preto sai da galeria (só
  a linha; arquivo fica), inclusive de produto sem foto nova. Upload do admin
  segue no fim. Recorte 2:3, 800x1200, JPG 82.
- **Pasta das fotos (fora do git):** 26 arquivos renomeados pro padrão
  `{SKU}_{lado}.jpg` (375: CAIP, CA, MM, PCL, LIM, LA, LCCS, WCS + 6 versos
  tirados de `375ML VERSO/`; 750: CAIP, CA, MM, LIM, VDK, GAKJ, MHA, LCC + 3
  versos de `750ML CONTRA RÓTULO/`). `Produtos_375_e_750_fotos_e_SKUs.csv` ganhou
  a coluna `GRUPO` e 13 produtos; manifesto regenerado por
  `gerar-manifesto.php` (57 entradas). `ANTIGOS/` intocada. Lista do que ficou
  com nome original no `CLAUDE.md` da pasta.
- **Manifesto no git:** só `public/img/fotos_produtos/manifesto.json` (exceção
  no `.gitignore`). Em produção as 69 imagens `foto-branco-*` sobem pelo git e o
  comando reaproveita (não precisa de GD nem da pasta de origem).
- **Resultado local:** 8 ativam (vodka-vdk, caipirinha-arazul, caju-amigo,
  squadra-amendoas, squadra-coco, limoncello, moscow-mule, penicillin), 0 saem,
  29 trocam galeria → **17 ativos**. Backup
  `storage/app/fotos-manifesto/backup-20261020-132131.json`.
- **Visual:** `--color-bg-media` = `#FEFEFE` (amostrado nos cantos de 8 fotos:
  FEFEFE/FFFFFF), nos dois temas. Card do catálogo e relacionados com a foto
  recuada e `--radius-md`; placeholder no mesmo fundo com texto e símbolo em
  `--color-text-on-media` (`#6F6353`). Usos de fundo de mídia que não são foto
  de produto (`.mc-media`, hero do Custom, timeline) voltaram a `--color-ink`.
- **Fotos de ambiente com rótulo antigo** (varredura em views/config/controllers):
  `cat-cosmopolitan`, `cat-fitzgerald`, `featured-negroni`,
  `hero-bartender`/`sobre-barman`, `cat-loja`/`sobre-interior` (prateleira com
  Negroni legível) e `products/negroni-classico-375ml-frente.jpg` (Quem Somos).
  **Regra (Paulo, 20/10):** rótulo antigo só bloqueia na galeria de produto. Em
  foto de ambiente, onde a garrafa não é o foco, a foto antiga fica até chegar
  a nova — nada de `<x-photo-slot>` no lugar dela. Trocadas só onde havia foto
  de ambiente sem rótulo pra pôr (sem repetir foto na página): Home Coquetéis
  Prontos → `about-loja-2`; Quem Somos card APTK → `franquias-hero`, galeria →
  `clube-hero` + `cat-evento`; Custom hero → `cat-pessoas`; Eventos Bar APTK →
  `clube-hero`. Ver tabela §11.1.
- **Bug corrigido:** o comentário do `<x-photo-slot>` tinha um `--}}` aninhado
  e vazava texto na página (não aparecia porque o componente não renderizava
  em lugar nenhum).

### 12.1 Pendências de foto

- **SLO375** (Sur L'Orange 375) sem foto própria: `SLO375_frente` é rótulo
  antigo e `SUR-L_ORANGE.jpg` é baixa resolução (720x538). Precisa de foto em alta.
- **NGA750** (Negroni Aged): só existe em fundo cinza, fora do padrão.
- **Dry Martini:** `DM750` (branco) tem rótulo creme; `DM375` (branco) tem
  rótulo azul-marinho. Confirmar qual é o atual. `cat-dry-martini.jpg` (vitrine
  Custom da Home e Full Custom) tem o creme — ficou, até confirmar.
- **Fotos de ambiente com rótulo antigo — trocar quando houver foto nova:**
  - `hero-negroni-poster.jpg` + vídeo do hero da Home (Negroni).
  - `cat-fitzgerald.jpg`: Eventos · Customização.
  - `featured-negroni.jpg`: Quem Somos · 2016; Eventos · Gifting.
  - `cat-cosmopolitan.jpg`: Quem Somos · card Barín.
  - Fotos da leva 13 que a gestão mandou com garrafa de rótulo antigo (ficam,
    pela regra acima): `home-clube.jpg` (Negroni Clássico de rótulo branco) e
    `home-arte-de-presentear.jpg` (Old Fashioned).
  - `cat-dry-martini.jpg` (se o creme for o antigo): Home · vitrine Custom;
    Custom · Full Custom.
  - Fora de uso hoje: `sobre-barman.jpg`/`hero-bartender.jpg`,
    `sobre-interior.jpg`/`cat-loja.jpg`.
- Bordas: `bou375-frente`, `dm750-verso` e `slo750-frente` têm canto um pouco
  mais escuro que o fundo do card (foto original); quase invisível.
- Sem foto branca do 750: Gallo Tropicalle, Ale Tonic, Penicillin, Licor de
  Amêndoas Squadra. Gin #1 375 e O Drink Perfeito sem foto.

**Deploy:** `reset --hard`, `migrate --force`, `view:clear`, depois
`php artisan produtos:aplicar-fotos --dry-run` (conferir 8 ativam, 0 saem, 29
trocam) e sem `--dry-run`.

---

## 13. Leva 11 — Custom vira compra direta (20/10/2026)

Configurador em `/custom` no lugar do formulário "Crie seu APTK" (como no site
antigo): bebida, texto e fonte → carrinho (texto sempre preto). Garrafa 375 ml a **R$ 211,00**
fixo, pronta entrega, sem caixa de presente (aviso na tela). Lead do Custom
Simples deixou de ser criado (leads antigos ficam no admin); o mailable
`CustomOrderRequested` ficou sem uso. Full Custom segue no WhatsApp.

- **Config** (`config/custom.php`): preço, volume, limite 30 caracteres em até
  2 linhas (quebra automática, sem Enter), fontes PP Rader (Editorial) e
  Amithen (Autoral), Blacksword (Clássico), cor fixa
  preto, 9 bebidas na ordem do comercial (7 desde 25/10, ver abaixo). Elegível = estar na lista e ter
  produto no banco (ativo ou não, com ou sem preço no 375).
- **Imagens** `public/img/custom/{slug}.jpeg`: 5 estavam com nome trocado em
  ciclo (o rótulo dizia outra bebida) e foram renomeadas com nome temporário.
  São pequenas (399–459 × 408–425 px, ~16 KB): servem no card, mas ficam um
  pouco moles em tela retina — **pedir versões maiores**. Área do rótulo
  calibrada por imagem (detecção do rótulo + logo APTK + nome da bebida).
- **Estoque:** a garrafa custom consome a 375 da bebida; carrinho e checkout
  somam todas as linhas do mesmo produto + volume. Hoje `size_stock` é null
  nas 9 (não trava).
- **Pedido:** `order_items.personalizacao` (json) `{bebida, texto, fonte, cor}`
  como o cliente enviou. Checkout revalida (config pode ter mudado) e devolve
  ao carrinho com o motivo. Cartão recusado devolve a garrafa ao carrinho com
  a personalização.
- **Appmax:** nome com " (Custom)", SKU `{sku}-375-CUSTOM`, `-2`/`-3` quando
  repete no mesmo pedido. O SKU base é o `products.sku` (o Negroni local tem
  `NC750`, então sai `NC750-375-CUSTOM`; o mesmo vale pra 375 normal).
- **Admin:** selo "Custom" na listagem; no detalhe, bloco "Personalização" por
  item (texto grande, fonte, cor — sempre Preto — com hex de impressão, prévia).
- **Frete:** sem mudança — a linha custom tem `size` "375 ml" e pesa igual.
- **Cupom:** o checkout não aplica cupom hoje; quando entrar, decidir se vale
  pro custom.
- **Microvix (travado):** o item custom iria com o código do 375 a R$ 211.
- **Celular:** miniatura da prévia (sticky, no topo da área do formulário)
  aparece quando a prévia grande sai da tela e atualiza ao vivo. Desktop
  sem mudança (a prévia grande já é sticky).
- **Ajuste da gestão (mesmo dia):**
  - **Cor fixa:** texto sempre preto (Cuba Libre), sem seletor. Saíram a
    paleta e as cores por bebida (a calibração por ΔE ficou no histórico do
    git, commit `e899e04`). `config custom.cor`; o pedido grava
    `cor: "preto"` (explícito no admin). Linha antiga no carrinho com outra
    cor é recusada no checkout.
  - **Três fontes**, como no site antigo: PP Rader (Editorial), Amithen
    (Autoral) e **Blacksword (Clássico)**. `@font-face` em `aptk-tokens.css`
    lê `public/fonts/blacksword.woff2` (ou `.ttf`), e `CustomService::fontes()`
    só oferece/aceita a fonte se um dos arquivos existir.
  - **Blacksword: licenciada pela APTK.** Arquivo
    `public/fonts/blacksword.woff2` versionado no repositório (chegou em
    20/10/2026). Escala **0.9**, calibrada nas 9 bebidas com "Feliz
    aniversário, João" e seis textos de 30 caracteres: é o maior valor em
    que todos cabem em 2 linhas (0.95 já quebra "Ana, Pedro & Lu,
    20.09.2026 :)" em 3). Nela a largura do texto fica igual à da PP Rader
    e a maiúscula entre PP Rader e Amithen; o traço é mais fino, então
    parece um pouco mais leve que a Amithen.
  - **Ordem da página:** o configurador é a primeira dobra (h1 "Um coquetel.
    A sua assinatura."; no celular: título, prévia, formulário). A
    apresentação "APTK Custom / Sua história. Nossa alquimia." vem depois,
    seguida da jornada 01/02/03 e do Full Custom.
  - "Crie seu APTK" → **"Crie seu Drink"** (eyebrow do configurador) e
    "Personalize seu APTK" → **"Personalize seu drink"** (CTA da
    apresentação em `/custom` e card do Custom na Home; o menu não tinha).
- **Ajuste de 25/10/2026:**
  - **7 bebidas**, nesta ordem: negroni-classico, caipirinha-arazul,
    dry-martini, caju-amigo, fitzgerald, moscow-mule, cosmopolitan.
    Boulevardier e Sur L'Orange saíram para `custom.retiradas`: recusadas no
    add-to-cart e no checkout ("Essa bebida não está mais disponível para
    personalização."); linha que já estava no carrinho mostra o aviso na
    própria linha. A calibração e as imagens ficam, só pra prévia de pedido
    antigo.
  - **Nome de exibição** (`nome` por bebida): caipirinha-arazul aparece como
    "Caipirinha" no seletor, carrinho, checkout, Meus pedidos, sucesso e
    bloco "Personalização" do admin. Loja e página do produto seguem com
    "Caipirinha Arazul"; `order_items.product_name` e o nome na Appmax
    também (snapshot do produto). O pedido grava
    `personalizacao.bebida_nome` junto do slug, e esse nome vence a config
    na exibição. Pedido anterior sem o campo usa a config.

### 13.1 Pendências da leva 11 (não feitas)

- **Prévias em resolução maior:** pedir à APTK (hoje ~420 px, 16 KB).
- **SKU por volume na Appmax:** a 375 vai como `{sku do produto}-375`, e o
  `products.sku` é o do 750 (ex. `NC750-375`). A planilha tem o SKU de cada
  volume (`NC375` etc.). Proposta: o importador grava o mapa volume → SKU
  (ex. `size_skus`) e o payload da Appmax usa o SKU do volume (custom:
  `NC375-CUSTOM`).

**Deploy:** `reset --hard`, `migrate --force` (coluna nova em `order_items`),
`route:clear && route:cache`, `view:clear && config:cache` (config nova).

---

## 14. Leva 13 — fotos da Home, seções largas, BARIN (25/10/2026)

- **Fotos da Home.** Originais da gestão em `public/img/fotos_site/` (nomes do
  Drive, **fora do git** pelo `.gitignore`); versões otimizadas (lado maior ≤
  1920 px, JPG 80, PNG sem transparência vira JPG) em `public/img/aptk/home-*.jpg`:

  | bloco | original | otimizada |
  |---|---|---|
  | Coquetéis Prontos | `coqueteis prontos  - home.jpg` 1370 KB | `home-coqueteis-prontos.jpg` 150 KB |
  | Crie Seu Drink | `CUSTOMIZADO.png` 2488 KB | `home-crie-seu-drink.jpg` 240 KB |
  | Arte de Presentear | `arte de presentear - home.JPG` 9982 KB | `home-arte-de-presentear.jpg` 221 KB |
  | Clube APTK | `club - home.jpg` 4027 KB | `home-clube.jpg` 258 KB |
  | Ecossistema BARIN | `feito para o ritmo do bar - home.jpg` 4669 KB | `home-barin.jpg` 221 KB |
  | Ecossistema Ice4Pros | `gelo - home.jpg` 184 KB | `home-ice4pros.jpg` 130 KB |

  Enquadramento por `object-position` (no `$explore` da view e nas classes
  `.hx-eco-img--*`), conferido em desktop e 390 px.
- **Links dos cards:** Crie Seu Drink → `/custom`, texto **provisório** "Um
  coquetel. A sua assinatura." (aprovação da gestão); Arte de Presentear →
  `/eventos#gifting` (âncora no card "Gifting & Presentes").

---

## 7. Histórico — varredura de 2026-08-20 (mantida como referência)

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
