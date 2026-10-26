# APTK Spirits — Plataforma

E-commerce Laravel da holding **Small Batches**, que reúne três marcas: **APTK Spirits**
(drinks engarrafados), **Ice4Pros** (gelo de cristal premium) e **BARIN** (destilados artesanais).

Responsável técnico: Paulo (dev freelance). Cliente: time de gestão da APTK.

- Produção: `aptk.unysystens.com.br` (VPS Ubuntu 20.04, Apache2, MySQL 8)
- Local: Windows + XAMPP/Laragon, `localhost:8000`
- Repositório: `paulo-web-dev/APTK-PLATFORM`, branch `main`

## Stack

Laravel 10 · PHP 8.1 · MySQL 8 · Bootstrap 5 · Blade · Breeze (auth)

**Sem build step para CSS.** Todo CSS é linkado direto de `public/css/`. Não rode
`npm run build` para mudanças de estilo — não adianta nada.

## Comunicação

- Responder **sempre em PT-BR**, registro casual, direto, **sem emojis**.
- Explicações concisas. Nada de relatório longo depois de uma mudança pequena.
- Antes de mexer em algo grande: apresentar o plano, esperar aprovação.

## Estrutura

```
app/Models/          13 models (Product, Order, Subscription, Post, Lead, Setting...)
app/Services/        AppmaxService, CartService, OrderService, SubscriptionService
app/Http/Controllers/
  Shop/              loja pública (Home, Product, Cart, Checkout, Custom, Page, Novidades)
  Admin/             12 módulos CRUD do painel
  Webhooks/          Appmax (webhook + install/health + callback genérico logado)
app/Mail/            CustomOrderRequested (único mailable; sem Jobs, Commands ou queue)
routes/
  web.php            loja pública + área logada
  admin.php          prefixo /admin, nomes admin.*, middleware AdminMiddleware
  b2b.php            prefixo /b2b, nomes b2b.*, middleware B2BMiddleware (só esqueleto)
  auth.php           Breeze
resources/views/     83 blades
public/css/          aptk-tokens.css é a fonte única de tokens de design
```

## Convenções do projeto

**Rotas em português.** `/loja`, `/carrinho`, `/produto/{slug}`, `/meus-pedidos`,
`/clube`, `/novidades`, `/admin/produtos`. Nomes de rota em inglês (`catalog`,
`cart.index`, `orders.show`). Manter esse padrão.

**Páginas institucionais** passam por uma whitelist de slug no final do `web.php`:
`clube|assinantes|collabs|eventos|quem-somos` → `PageController@show`. Rota nova
literal precisa vir ANTES desse catch-all. As LPs de marca `/barin` e `/ice4pros`
são `Route::view` diretas. Quem Somos, Clube e Eventos têm view própria
(`shop.eventos` desde a leva 10; portfólio = grade de collabs de
`config/collabs.php` via `<x-portfolio-grid>`, a mesma de `/collabs`, seção só
aparece com ≥ 1 item); Collabs e Assinantes usam o template `shop.page`.

**Preço por volume.** `Product` tem `sizes` (array JSON de volumes, ex. `["375ml","750ml"]`)
e `size_prices` (mapa volume→preço). Use `priceForSize()`, `sizeOptions()`,
`defaultSize()`. O carrinho identifica linha por produto + volume (a "key" do CartService).

**Carrinho em sessão**, não em banco. `CartService`. Linha custom leva
`personalizacao` e a chave ganha `:c{hash}` (texto diferente = outra linha); preço
da linha custom = `config('custom.preco')`. Estoque é por produto + volume somando
as linhas (`qtyDoVolume`): a garrafa custom consome a 375 da bebida. No pedido,
`order_items.personalizacao` (JSON como o cliente enviou); Appmax recebe
" (Custom)" no nome e SKU `-CUSTOM` (`-2`, `-3` se repetir no pedido).

**Checkout aberto a visitante** (leva 09): `GET /checkout` é público; sem login
mostra o card "Identificação" (e-mail → senha ou cadastro, AJAX em
`checkout.identificacao.*`, `CheckoutIdentificacaoController`) e o formulário
oculto; `POST /checkout` exige login. Rate limiters nomeados
(`checkout-email|entrar|cadastrar`) + latência fixa na verificação de e-mail.
Declaração 18+ em `users.maior_idade_em` (cadastro, `/register` e, para conta
antiga, checkbox no checkout). Pós-login de `/login` e `/register`:
`DestinoPosLogin` (checkout → checkout; carrinho cheio → carrinho; senão
intended/home — nunca `/dashboard`). Login migra a sessão: carrinho preservado.

**Configurações** chave/valor na tabela `settings` via `Setting::get()` / `Setting::set()`.
Usado inclusive para o UUID de instalação externa da Appmax.

**Roles** no `User`: `admin`, `customer`, `b2b`. Helpers `isAdmin()`, `isB2b()`.

**Design tokens.** Sempre usar as variáveis de `public/css/aptk-tokens.css`. Nunca
hardcodar cor. Paleta: Espumante `#E5DBC9`, Cuba Libre `#020203`, Scotch âmbar `#BF6300`,
Amarula nude `#D0B29B`, Cobre metálico. Tipografia: PP Rader (display/UI) e Neue World
(editorial), ambas WOFF2 locais em `public/fonts/`. **Dark mode é a identidade da marca**,
com toggle global — não tratar como opcional.

**Componentes de marca:** `<x-brand.symbol>`, `<x-brand.logo>`, `<x-brand.sunburst>`,
`<x-brand.divider>`.

**Componentes editoriais (leva 10, `aptk.css`):** `.title-editorial` (Neue World nos
títulos grandes — heroes e títulos das seções editoriais; nav, botões, eyebrows e
cards seguem em PP Rader), `.btn-arrow` (seta dentro do `.btn-aptk`), `.link-arrow`,
`.num-steps` ("01 — título / texto"; `--stack` em coluna), `.media-card` (foto +
eyebrow/título/texto/link, proporção via `--mc-ratio`) e `<x-photo-slot ratio="4/3">`
(bloco reservado pra foto que não chegou — só em bloco secundário, com
`{{-- FOTO: ... --}}` ao lado). Títulos de seção em caixa de frase.

**Links das marcas** nos cards do ecossistema (Quem Somos): `config/marcas.php`
→ `links` (url + label). Mudou → `config:cache` em produção.

**Custom Simples (leva 11): compra direta.** Configurador em `/custom` (primeira
dobra; bebida, texto, fonte — texto sempre preto) → `POST /custom/carrinho` (`custom.cart`); não cria mais lead.
Tudo em `config/custom.php` (preço fixo R$ 211, volume 375 ml, fontes PP Rader,
Amithen e Blacksword — licenciada pela APTK; só entra se
`public/fonts/blacksword.woff2|ttf` existir (`CustomService::fontes`) —, cor fixa `custom.cor`, 7 bebidas com imagem, área do
rótulo calibrada por imagem e `nome` de exibição — caipirinha-arazul = "Caipirinha"
só no Custom, a loja segue com o nome do produto; o pedido grava
`personalizacao.bebida_nome`; boulevardier e sur-lorange em `custom.retiradas`,
recusadas, calibração só pra prévia de pedido antigo) e validado por `CustomService::validar` no add-to-cart
E no checkout (bebida na lista com produto no banco, ativo ou não; fonte entre as
disponíveis; cor = a fixa; texto ≤ 30, sem HTML/emoji/Enter). Prévia `<x-custom.previa>` (imagem de
`public/img/custom/{slug}.jpeg` + texto sobreposto em `cqw`), mesma em carrinho,
checkout, pedidos e admin; `<x-custom.ficha>` ao lado do nome. Full Custom segue no WhatsApp.

**Imagem de produto:** sempre via `<x-product.image :image="..." :alt="...">` (foto ou
`<x-product.placeholder>`, moldura 2:3 "Produto ainda sem foto"). CSS em
`public/css/aptk-produto.css` (linkado também no layout admin, que não carrega o
`aptk.css`). Foto de produto é fundo branco (rótulo atual):
`products/foto-branco-{sku|grupo-Nml}-{frente|verso}.jpg`, sobre `--color-bg-media`
(`#FEFEFE`, o branco das fotos, nos dois temas) com `--radius-md`; placeholder no mesmo
fundo, texto/símbolo em `--color-text-on-media`. `--color-bg-media` é SÓ pra foto de
produto: foto de ambiente usa `--color-ink`. O pacote fundo preto
(`{grupo}-{vol}-{lado}.jpg`) tem rótulo antigo e não é mais usado. Rótulo antigo só
bloqueia na galeria de produto: foto de ambiente com garrafa antiga fica até chegar a
nova (não trocar por `<x-photo-slot>`); lista em ESTADO-ATUAL §12.1.
Upload do admin: nome hash de 40 caracteres (`ProductImage::isUploadDoAdmin()`) — só
esse arquivo o admin apaga do disco. Upload ADICIONA como principal.

**Catálogo: responsabilidade dividida em dois comandos.**
- `catalogo:importar-planilha` (CSV em `database/data/`) cuida SÓ de nome,
  volumes, preços, SKU e categoria. Nunca mexe em `active` nem em
  `product_images`; produto novo nasce inativo. Rodar só quando a planilha mudar
  (não faz parte do deploy).
- `produtos:aplicar-fotos` é o dono da ativação (e da galeria). Ativo =
  `Product::podeAtivar()`: tem preço E tem foto no padrão
  (`Product::ehFotoNoPadrao`: `foto-branco-*` do manifesto fundo branco, ou
  upload do admin). Pacote fundo preto e foto antiga do site não contam; só
  placeholder = inativo. `--sem-galeria` só aplica a ativação; sem ela, monta
  galeria a partir de `public/img/fotos_produtos/manifesto.json` (único arquivo
  da pasta no git; o resto é fonte local, regenerada por `gerar-manifesto.php`)
  e recusa manifesto que não seja `fundo=branco`. Entrada sem SKU casa pelo
  `grupo`. Pacote fundo preto sai da galeria (só a linha). Salva backup em
  `storage/app/fotos-manifesto/` antes de gravar; `--reverter=ultimo` volta. `--dry-run`.
- O admin usa a mesma regra: sem preço ou sem foto no padrão não ativa ("Envie
  uma foto do produto antes de ativar."). Foto enviada no mesmo salvar conta.

`catalogo:produto-teste [--remover]`: produto "Teste" (slug `teste`, 100 ml a
R$ 1,00, Bases) para teste de pagamento real; o importador nunca toca nele.

**Voz da marca:** leve, detalhista, inspiradora. "Alquimia de histórias".
"Feito por humanos inquietos." Narrativa e construção de valor acima de preço/desconto.
Evitar linguagem técnica ou promocional.

## Integrações

**Appmax** (pagamentos — Pix, cartão, boleto): API v4 com OAuth2. `AppmaxService`.
Credenciais da própria loja geradas no painel (menu API): `APPMAX_CLIENT_ID/SECRET`,
`APPMAX_AUTH_URL`, `APPMAX_API_URL`, `APPMAX_EXTERNAL_ID` no `.env`, com prioridade
sobre o legado do fluxo de instalação (settings + `APPMAX_ENV`). O script do
Appmax JS segue o ambiente da API URL. Cartão só com token do Appmax JS (dado cru
é recusado). Retry só no token e em GET — POST de pedido/pagamento nunca repete.
Webhook em `/webhooks/appmax/{token}`, formato do painel (sem assinatura): o
controller confirma o status em `GET /v1/orders/{id}` antes de agir e responde
503 pra Appmax retentar se a consulta falhar. Ver ESTADO-ATUAL §9.
Independente do webhook, `pagamentos:reconciliar` (scheduler, 5 min) consulta
Pix (até 2h) e boleto (até 10 dias) pendentes e aprova/cancela só com resposta
conclusiva (`ReconciliacaoPagamento`); fora da janela vira "pagamento a
conferir" no admin. O polling da tela de sucesso usa a mesma regra (1 consulta
a cada 30s por pedido). `appmax:diagnostico --pedido=N [--reconciliar]`.

**Frete** (leva 08): estimativa própria, sem transportadora. `FreteService` +
`config/frete.php`: origem SP capital, tabela de referência dos Correios por
região (faixa de CEP da UF) + 20%, PAC e SEDEX. Peso = ml do volume / 1000 +
1 kg de embalagem por item, arredondado pra cima ao kg. `/frete/cotar`
(`Shop\FreteController`) cota o carrinho da sessão no formato
`{opcoes: [{codigo, nome, valor, prazo_dias}]}`. **O servidor recalcula no
fechamento** (`OrderService::placeFromCart`, do CEP + `shipping_method`); o valor
do front nunca entra. Pedido grava `shipping_cost`, `shipping_method`
(pac|sedex) e `shipping_days`. SuperFrete (sem conta ainda): trocar só a
implementação do `FreteService`. Frete zero só com carrinho 100% de slugs em
`frete.isentos` (hoje só `teste`): opção única `teste`, R$ 0,00; carrinho misto
cobra frete normal sobre o peso de tudo e rejeita `shipping_method=teste`.

**Linx Microvix ERP** (WS B2C): fase 1 implementada — espelho somente-leitura.
Doc em `docs/linx-microvix-ws-b2c.md`; **em divergência de nome de campo, vale o
PDF original** (`docs/linx-microvix-ws-b2c-original.pdf`) — o .md é conversão e
pode ter corrompido nomes. `app/Services/Microvix/`: `MicrovixClient` (transporte:
consulta XML puro + importa SOAP; retry 2x só em falha de rede e SÓ em consulta —
importa NUNCA retenta, obs. 5 da doc 6.2), `MicrovixSync` (timestamp incremental
por método, só avança com lote inteiro OK), `MicrovixProductMirror` (→
`microvix_products`; vínculo com `products` é manual). Toda chamada logada em
`microvix_logs` com password/chave mascaradas. Comandos: `microvix:ping`,
`microvix:descobrir` (acha os parâmetros de faturamento que faltam no `.env`),
`microvix:sync[-produtos]`, `microvix:status`, `microvix:conciliar[ --dry-run]`.

**Fase 1.5 (ERP como fonte de estoque/preço) implementada, mas DESLIGADA:**
`MICROVIX_FONTE_VERDADE` no `.env` (`site` = padrão; `erp` = a virada). Vínculo
`microvix_products` → `products` + volume é manual, em `/admin/microvix/produtos`
(`MicrovixMatcher` só sugere). `MicrovixReconciler` grava `saldo` → `size_stock`
e `precovenda` → `size_prices` só de vínculo confirmado, nunca editorial/`active`;
com fonte `site` recusa gravar (dry-run forçado). Trilha de/para em
`microvix_reconcile_log`. `Product::managedByErp()` (fonte `erp` E vínculo) trava
preço/estoque no admin — o guard real é no controller, não só no form. Loja:
`size_stock` null se comporta como antes (`stock_qty` placeholder, nada esgota);
checkout revalida com `lockForUpdate` dentro da transação. Painel de saúde em
`/admin/microvix` (sync e conciliação manuais, síncronos — sem queue).

**Fase 2 (pedido → ERP) implementada, mas TRAVADA:** `MICROVIX_ENVIA_PEDIDO`
no `.env` (`false` = monta o XML, grava em `microvix_logs` com `simulated=true`
e não envia; trava vive no `MicrovixPedidoSender`, não no job). Fluxo da doc
5.1: `B2CConsultaPedidos` (idempotência; `id_pedido` = `orders.id`) →
CadastraClientes (`atualizar_por_cpf=1`) → EnderecosEntrega (id = `orders.id`,
`descricao` "Pedido #N") → CadastraPedido (`somente_reserva=1` na criação, `0`
no webhook de pagamento) → PedidoItens; expirado/recusado/cancelado →
`B2CCancelaPedido` (dispatch centralizado no `cancelAndRestoreStock`). Fila
database (`EnviarPedidoMicrovix`, fila `microvix`), worker via scheduler
(`queue:work --stop-when-empty` a cada minuto) — **e só enfileira com a trava
aberta** (`EnviarPedidoMicrovix::integracaoLigada()`; com ela fechada nem o XML
simulado sai, só por reenvio manual no painel), concorrência 1
(WithoutOverlapping) + guarda de segundo por método (obs. 5 da doc). Erro de
negócio (plano pix/boleto inexistente etc.) para em `erro` sem retry; rede
retenta 3x com backoff. Estado em `orders.microvix_envio_*`; painel de pedidos,
trilha de XML (selo simulado/real) e reenvio em `/admin/microvix`; pedido em
erro gera banner global no admin. Com `MICROVIX_ENVIA_PEDIDO=false` o banner, o
badge e o item do menu somem (rotas seguem por URL direta). Parse da resposta do Importador é PALPITE —
confirmar no primeiro envio real (`avaliaRespostaImporta`).

## Clube de assinatura

Quatro tiers: APTK Explorer (gratuito), APTK Collector (R$199/mês),
APTK Connoisseur (R$349/mês), Le Cercle (por convite — `price = null`, o que faz
`isSelfServe()` retornar `false`).

## Pendências conhecidas

1. Frete é estimativa por tabela (+20%) — cotação real depende de conta na SuperFrete.
2. Portal B2B é só fachada: middleware + role + um `/ping`. Sem controllers nem views.
3. Microvix fora de operação (`MICROVIX_ENVIA_PEDIDO=false`); ver ESTADO-ATUAL §8.4.
4. Fotos de produto: SLO375 precisa de foto em alta; NGA750 só em fundo cinza;
   rótulo atual do Dry Martini a confirmar (DM750 creme × DM375 azul); fotos de
   ambiente com rótulo antigo (hero da Home, cards) a trocar quando houver nova. Foto nova: renomear na pasta, rodar
   `gerar-manifesto.php` e `produtos:aplicar-fotos`. Ver ESTADO-ATUAL §12.1.
5. Leva 10: fotos e textos provisórios, links das marcas, pontos do Clube "em breve"
   — lista em ESTADO-ATUAL §11.
6. Custom (leva 11): imagens da prévia pequenas (~420 px), pedir maiores. SKU
   por volume na Appmax (hoje `{sku do 750}-375`; a planilha tem o SKU de cada
   volume). Ver ESTADO-ATUAL §13.1.

## Deploy

```
# local
git add . && git commit -m "..." && git push

# servidor (Paulo NUNCA edita código direto lá, então reset --hard é seguro)
cd /var/www/aptk
git fetch origin && git reset --hard origin/main
php artisan migrate --force
php artisan route:clear && php artisan route:cache
php artisan view:clear && php artisan config:cache
```

Se `git pull` disser "Already up to date" mas o servidor estiver desatualizado, é merge
abortado antigo: usar `git fetch origin` + `git reset --hard origin/main`.

Imagens de produto/categoria são versionadas em `storage/app/public/products|categories`
e upload novo entra no `git add .` normalmente: o `.gitignore` de `storage/app/public/`
re-inclui as duas pastas (o `*` de `storage/app/.gitignore` vale recursivamente, então
qualquer pasta de upload nova ali precisa da mesma re-inclusão explícita).

## Regras de trabalho

- Não commitar `.env` (contém credenciais Appmax e do banco).
- Não rodar `migrate:fresh` sem avisar — apaga dados locais.
- Depois de mexer em rota, rodar `php artisan route:clear`.
- Mudanças do cliente vêm agrupadas em "levas" numeradas, cada uma com escopo fechado
  antes da execução. As levas 01 a 11 já foram concluídas.
