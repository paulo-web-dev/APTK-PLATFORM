# Contexto do projeto — APTK Platform

> Cole este arquivo no início da conversa. Você (Claude, no chat) NÃO tem acesso
> ao repositório: quando precisar ver um arquivo, me peça o conteúdo que eu colo.
> Não invente nome de método, coluna ou rota — se não tiver certeza, pergunte.
> Atualizado em 11/10/2026 (depois da leva 07).

## Como trabalhar comigo

- Sou o Paulo, dev freelance e único responsável técnico. Cliente: time de gestão da APTK.
- Responder **sempre em PT-BR**, registro casual, direto, **sem emojis**.
- Explicações concisas. Nada de relatório longo pra mudança pequena.
- Antes de mudança grande: apresentar o plano e esperar eu aprovar.
- Ao propor código: mostrar o arquivo/trecho exato a trocar, no estilo do código vizinho
  (comentários em português, mesma densidade e idioma do arquivo).
- Pedidos do cliente chegam em **"levas" numeradas**, cada uma com escopo fechado antes
  de executar. Levas 01 a 07 concluídas.

## O que é

E-commerce Laravel da holding **Small Batches**, com três marcas:
- **APTK Spirits**: drinks engarrafados (marca principal do site)
- **Ice4Pros**: gelo de cristal premium (tem LP própria e domínio próprio)
- **Barín**: destilados artesanais (LP em `/barin`)

- Produção: `aptk.unysystens.com.br` (VPS Ubuntu 20.04, Apache2, MySQL 8), código em `/var/www/aptk`
- Local: Windows + XAMPP/Laragon, `localhost:8000`
- Repo: `paulo-web-dev/APTK-PLATFORM`, branch `main`

## Stack

Laravel 10 · PHP 8.1 · MySQL 8 · Bootstrap 5 · Blade · Breeze (auth) · Guzzle (Appmax via HTTP direto, sem SDK).

**Não tem build step de CSS.** Todo CSS é linkado direto de `public/css/`
(`aptk-tokens.css`, `aptk.css`, `checkout.css`, `admin.css`). Nunca sugerir `npm run build`
pra mudança de estilo.

Testes: PHPUnit em sqlite `:memory:` (`php artisan test`), ~155 testes. Feature tests em
`tests/Feature` (checkout com estoque por volume, frete, MarcaHost, Microvix etc.).

## Estrutura

```
app/Models/            Address, Coupon, Lead, Order, OrderItem, Post, Product, ProductCategory,
                       ProductImage, Setting, Subscription, SubscriptionPlan, User,
                       MicrovixLog, MicrovixProduct, MicrovixReconcileLog, MicrovixSyncState
app/Services/          AppmaxService, CartService, OrderService, SubscriptionService
app/Services/Microvix/ MicrovixClient, MicrovixSync, MicrovixProductMirror, MicrovixMatcher,
                       MicrovixReconciler, MicrovixPedidoSender, MicrovixRelogio
app/Http/Controllers/
  Shop/                Home, Product, Cart, Checkout, Custom, Frete, Novidades, Order, Page, Subscription
  Admin/               Dashboard, Order, Product, Category, Coupon, Plan, Subscription, Post, Lead,
                       Setting, Customer, Stock, MicrovixDashboard, MicrovixProduct
  Webhooks/            AppmaxWebhookController, AppmaxInstallController
  B2B/                 (vazio)
app/Http/Middleware/   AdminMiddleware, B2BMiddleware, MarcaHost
app/Jobs/              EnviarPedidoMicrovix (único job)
app/Mail/              CustomOrderRequested (único mailable)
app/Console/Commands/  CancelarCartaoPreso, CatalogoImportarPlanilha + Microvix/* (ping, descobrir, sync, sync-produtos,
                       status, conciliar, fake)
config/marcas.php      domínios próprios das marcas
config/microvix.php    config do ERP
routes/web.php         loja pública + área logada
routes/admin.php       prefixo /admin, nomes admin.*, AdminMiddleware
routes/b2b.php         prefixo /b2b, nomes b2b.*, B2BMiddleware (só /ping)
routes/auth.php        Breeze
resources/views/       layouts (app, public, home, marca, admin, guest), shop/*, admin/*,
                       account, subscription, components/brand, partials (theme-init/toggle)
```

Banco: **nenhuma FK de verdade**, os `*_id` são `unsignedBigInteger` com índice.

## Convenções

**Rotas em português, nomes em inglês.** `/loja` (catalog), `/produto/{slug}` (product),
`/carrinho` (cart.index), `/checkout`, `/meus-pedidos` (orders.index), `/minha-assinatura`,
`/novidades`, `/custom`, `/admin/produtos` (admin.products.index), `/admin/estoque` etc.

**Páginas institucionais**: catch-all no FIM do `web.php`:
`/{slug}` com whitelist `clube|assinantes|collabs|eventos|quem-somos` → `PageController@show`.
Rota literal nova tem que vir ANTES dele. `/barin` e `/ice4pros` são `Route::view`.
Redirects 301 antigos: `/customizacao`→`/custom`, `/parceiros`→`/collabs`,
`/sobre` e `/marcas`→`/quem-somos`, `/franquias`→ LP externa.

**Domínio de marca** (`MarcaHost` + `config/marcas.php`): em `ice4pros.com.br` a raiz serve a
LP com layout isolado (`layouts.marca`), qualquer outro caminho leva 301 pro domínio da APTK.
Em dev: `http://ice4pros.localhost:8000`. Barín entra depois só adicionando uma entrada.

**Preço e estoque por volume.** `Product` tem `sizes` (JSON, ex. `["375ml","750ml"]`),
`size_prices` (mapa volume→preço) e `size_stock` (mapa volume→qtd, nullable). Usar
`priceForSize()`, `sizeOptions()`, `defaultSize()`. `size_stock` null = comportamento antigo
(`stock_qty` placeholder, nada esgota). Checkout revalida estoque com `lockForUpdate` na transação.

**Carrinho em sessão** (`CartService`), linha identificada por produto + volume (a "key").

**Settings** chave/valor: `Setting::get()` / `Setting::set()`.

**Roles** em `User`: `admin`, `customer`, `b2b`. Helpers `isAdmin()`, `isB2b()`.

**Pedido**: endereço de entrega é snapshot em colunas `ship_*` de `orders`.
Cancelamento centralizado em `OrderService::cancelAndRestoreStock`.

## Design

- Sempre usar variáveis de `public/css/aptk-tokens.css`. **Nunca hardcodar cor.**
- Paleta: Espumante `--color-cream #E5DBC9`, Cuba Libre `--color-ink #020203`,
  Scotch âmbar `--color-scotch #BF6300` (primária), Amarula `--color-nude #D0B29B`,
  Cobre `--color-copper` / `--copper-foil` (gradiente).
  Semânticos: `--color-bg`, `--color-bg-card`, `--color-text`, `--color-text-muted`,
  `--color-primary`, `--color-border`, `--color-success/warning/danger/info`.
- Fontes (WOFF2 locais em `public/fonts/`): `--font-display` PP Rader (títulos, nav, botões),
  `--font-editorial` Neue World (texto editorial), `--font-body` system-ui (UI),
  `--font-amithen` Amithen (rótulos do Custom Simples).
- Escala: `--text-xs` … `--text-5xl`; raios `--radius-sm/md/lg/xl`.
- **Tema: dark FIXO desde a leva 03** (decisão do cliente). `partials/theme-init` força
  `data-theme="dark"`; tema claro e toggle desativados, mas os tokens claros continuam
  definidos caso mude. Tudo novo tem que funcionar bem no escuro.
- Componentes: `<x-brand.symbol>`, `<x-brand.logo>`, `<x-brand.sunburst>`, `<x-brand.divider>`.
- **Voz da marca**: leve, detalhista, inspiradora. "Alquimia de histórias". "Feito por humanos
  inquietos." Narrativa e valor acima de preço/desconto. Evitar tom técnico ou promocional.

## Integrações

**Appmax** (pagamento: Pix, cartão, boleto) — API v4, OAuth2, `AppmaxService`.
Webhook `POST /webhooks/appmax/{token}` (sem CSRF). `/appmax/health` = instalação do app
(responde `external_id` UUID, guardado em Setting). `/appmax/callback` é captura genérica
provisória que loga tudo. Produção ok; sandbox fora do ar (504), sem ambiente de teste.

**Frete**: `POST /frete/cotar` → `Shop\FreteController@cotar` é casca: valida CEP e devolve
opção fixa (Entrega padrão, R$ 10, 5 dias úteis) no formato final
`{opcoes: [{nome, valor, prazo_dias}]}`. Falta conta na SuperFrete pra implementar de verdade.

**Linx Microvix ERP** (WS B2C). Doc em `docs/linx-microvix-ws-b2c.md`; em divergência de
nome de campo vale o PDF original.
- **Fase 1 (feita)**: espelho somente-leitura em `microvix_products`. Toda chamada logada em
  `microvix_logs` com senha/chave mascaradas. `MicrovixClient`: retry 2x só em falha de rede
  e só em consulta, importação NUNCA retenta.
- **Fase 1.5 (feita, DESLIGADA)**: ERP como fonte de preço/estoque. `.env`
  `MICROVIX_FONTE_VERDADE=site` (padrão) ou `erp`. Vínculo ERP→produto+volume é manual em
  `/admin/microvix/produtos`. `MicrovixReconciler` grava `saldo`→`size_stock` e
  `precovenda`→`size_prices`. `Product::managedByErp()` trava preço/estoque no admin (guard no controller).
- **Fase 2 (feita, TRAVADA)**: pedido → ERP via job `EnviarPedidoMicrovix` (fila `microvix`,
  worker pelo scheduler). `.env` `MICROVIX_ENVIA_PEDIDO=false` só simula e grava o XML.
  Parse da resposta do importador é palpite, confirmar no primeiro envio real.
- **Bloqueios do lado do ERP** (dependem da Linx/gestão): nenhum dos 1237 produtos está
  marcado como B2C; empresa APTK não envia estoque; não existe tabela de preço; faltam planos
  APPMAX de Pix e boleto; Ice4Pros fatura em CNPJ separado e Barín não existe no ERP;
  não há portal de teste (aceitação = dados reais). Dev usa `microvix:fake` (dados fictícios).
- Painel: `/admin/microvix`.

## Clube de assinatura

Tiers: APTK Explorer (gratuito), APTK Collector (R$199/mês), APTK Connoisseur (R$349/mês),
Le Cercle (por convite: `price = null`, então `isSelfServe()` = false).
Assinatura vive só no banco, sem cobrança recorrente na Appmax. Captação pré-lançamento:
`POST /clube/interesse` gera Lead tipo "clube".

## Agendador

`app/Console/Kernel.php`: `pedidos:cancelar-cartao-preso` (a cada 15 min, cancela pedido
pending de cartão parado há mais de 60 min) e `queue:work --queue=microvix --stop-when-empty`
(a cada minuto). Depende do cron do servidor:
`* * * * * cd /var/www/aptk && php artisan schedule:run >> /dev/null 2>&1`.

## Pendências conhecidas

1. Frete fixo R$ 10 (sem conta SuperFrete).
2. Portal B2B é só fachada (middleware + role + `/b2b/ping`).
3. Microvix fases 1.5 e 2 esperando a Linx/gestão (ver acima).
4. Assinatura sem cobrança recorrente.
5. Catálogo da leva 07: 36 dos 47 produtos inativos esperando a gestão (sem foto nova,
   sem preço ou os dois); Licor Cacau Show e Borelli com preço com cara de custo.
6. `Admin\ProductController::handleImage` apaga TODAS as imagens do produto ao subir
   foto nova: num produto importado, leva junto frente/verso de todos os volumes.

## Catálogo (leva 07)

Fonte: `database/data/catalogo-planilha-2026-10.csv` (uma linha por produto + volume),
importada por `catalogo:importar-planilha [--dry-run]`, idempotente, sem Linx.
- Atualiza o que tem valor e nunca remove volume já à venda. Ativo só com preço E foto
  do pacote novo (FUNDO PRETO); foto antiga não conta. Produto existente mantém slug e
  categoria; novo nasce com slug = grupo e `stock_qty` 30. `managedByErp()` é pulado.
  `cachaca-prata` e `cachaca-ouro` (fora da planilha) são desativadas pelo comando.
- Fotos de origem em `public/img/fotos_produtos/` (fora do git). Geradas em
  `storage/app/public/products/{grupo}-{vol}-{frente|verso}.jpg` (1200 px, JPG 82, GD).
  Destino existente é reaproveitado, então em produção roda sem as fotos de origem e sem GD.
- Resultado local: 47 produtos, 9 ativos.
- Token `--color-bg-media` (= `--color-ink`) no fundo das fotos de produto.

## Deploy

```
# local
git add . && git commit -m "..." && git push

# servidor (nunca edito código lá, então reset --hard é seguro)
cd /var/www/aptk
git fetch origin && git reset --hard origin/main
php artisan migrate --force
php artisan route:clear && php artisan route:cache
php artisan view:clear && php artisan config:cache
```

Catálogo: depois do `reset --hard`, `php artisan catalogo:importar-planilha --dry-run`,
conferir e rodar sem `--dry-run`.

Imagens em `storage/app/public/products|categories` são versionadas (o `.gitignore` de lá
re-inclui as duas pastas; pasta de upload nova precisa do mesmo par de linhas
`!pasta/` + `!pasta/**`).

## Regras

- Nunca commitar `.env` (credenciais Appmax, banco, Microvix).
- Não rodar `migrate:fresh` sem avisar (apaga dados locais).
- Depois de mexer em rota: `php artisan route:clear`.
- Mudança de CSS: só editar o arquivo em `public/css/`, sem build.
- Mudança de config/.env em produção: `php artisan config:cache`.
