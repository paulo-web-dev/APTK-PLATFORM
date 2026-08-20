# APTK Spirits — Plataforma

E-commerce Laravel da holding **Small Batches**, que reúne três marcas: **APTK Spirits**
(drinks engarrafados), **Ice4Pros** (gelo de cristal premium) e **Barín** (destilados artesanais).

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
são `Route::view` diretas.

**Preço por volume.** `Product` tem `sizes` (array JSON de volumes, ex. `["375ml","750ml"]`)
e `size_prices` (mapa volume→preço). Use `priceForSize()`, `sizeOptions()`,
`defaultSize()`. O carrinho identifica linha por produto + volume (a "key" do CartService).

**Carrinho em sessão**, não em banco. `CartService`.

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

**Voz da marca:** leve, detalhista, inspiradora. "Alquimia de histórias".
"Feito por humanos inquietos." Narrativa e construção de valor acima de preço/desconto.
Evitar linguagem técnica ou promocional.

## Integrações

**Appmax** (pagamentos — Pix, cartão, boleto): API v4 com OAuth2. `AppmaxService`.
Webhook em `/webhooks/appmax/{token}`. Produção operacional; o sandbox estava fora do ar
(504 no endpoint de auth) — ticket de suporte aberto.

**SuperFrete**: sem conta ainda. `Shop\FreteController@cotar` existe como casca:
valida CEP e devolve opção fixa (Entrega padrão, R$ 10, 5 dias úteis) no formato
final `{opcoes: [{nome, valor, prazo_dias}]}` — trocar só a implementação depois.

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
`microvix:sync[-produtos]`, `microvix:status`. Fase 2 (pedidos, `somente_reserva`)
e fase 3 (ERP como fonte de estoque/preço) não iniciadas.

## Clube de assinatura

Quatro tiers: APTK Explorer (gratuito), APTK Collector (R$199/mês),
APTK Connoisseur (R$349/mês), Le Cercle (por convite — `price = null`, o que faz
`isSelfServe()` retornar `false`).

## Pendências conhecidas

1. Frete é casca fixa (R$ 10) — cotação real depende de conta na SuperFrete.
2. Portal B2B é só fachada: middleware + role + um `/ping`. Sem controllers nem views.
3. `resources/views/teste.blade.php` órfão; `public/css/` tem cinco arquivos herdados de
   outro projeto (`assessment`, `report`, `result`, `foundation`, `marketing`) sem uso.
4. Imagem NOVA em `storage/app/public/products|categories` é silenciosamente ignorada
   pelo git: o `*` de `storage/app/.gitignore` vence, e o `.gitignore` de
   `storage/app/public/` só contém `!.gitignore`. As 33 imagens versionadas foram
   adicionadas à força. Upload novo não chega ao servidor via deploy — usar
   `git add -f` até consertar o `.gitignore`.

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

Imagens de produto/categoria são versionadas em `storage/app/public/products|categories`,
mas o `.gitignore` atual ignora arquivos novos ali — ver pendência 5.

## Regras de trabalho

- Não commitar `.env` (contém credenciais Appmax e do banco).
- Não rodar `migrate:fresh` sem avisar — apaga dados locais.
- Depois de mexer em rota, rodar `php artisan route:clear`.
- Mudanças do cliente vêm agrupadas em "levas" numeradas, cada uma com escopo fechado
  antes da execução. As levas 01 a 06 já foram concluídas.
