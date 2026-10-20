# Contexto de design — site APTK Spirits

> Para colar no início de uma conversa no Claude chat, junto do arquivo de
> identidade visual. Descreve como o site está HOJE (19/10/2026): estilo,
> componentes, páginas e restrições técnicas. Você não tem acesso ao código:
> se precisar de um trecho, peça que eu colo. Não invente nome de classe,
> variável ou rota; se não estiver aqui, pergunte.

## Como trabalhar comigo

- Sou o Paulo, dev freelance e único responsável técnico. Cliente: time de gestão da APTK.
- Responder em **PT-BR**, casual, direto, **sem emojis**. Explicação curta.
- Mudança grande: plano primeiro, eu aprovo, depois o código.
- Ao propor código: arquivo/trecho exato, no estilo do código vizinho.

## O que é

E-commerce da holding **Small Batches**, com três marcas:

- **APTK Spirits**: drinks engarrafados (clássicos e autorais), bases e destilados. Marca principal do site.
- **Barín**: destilados artesanais. LP em `/barin` dentro do site da APTK.
- **Ice4Pros**: gelo cristal premium. LP em `/ice4pros` e domínio próprio (`ice4pros.com.br`, com layout isolado).

Produção: `aptk.unysystens.com.br`.

## Restrições técnicas (importante para qualquer proposta visual)

- Laravel 10 + **Blade** + **Bootstrap 5.3** (CDN) por baixo, tematizado por cima.
- **Sem build de CSS** (nada de Sass, Tailwind ou PostCSS). CSS puro, linkado direto de `public/css/`.
- Ordem no `<head>`: Bootstrap → `aptk.css` (que importa `aptk-tokens.css`) → `aptk-produto.css` → `<style>` da própria página.
- **Cada página tem o CSS específico num `<style>` dentro do próprio Blade.** Classes com prefixo por página (`cst-` Custom, `clb-` Clube, `brn-` Barín, `i4p-` Ice4Pros, `pdp` produto, `cko` checkout, `nv-` novidades, `pg-` páginas institucionais).
- **Regra de ouro: nenhuma cor hardcoded.** Tudo via `var(--token)` de `aptk-tokens.css`.
- Fontes locais em WOFF2 (`public/fonts/`), sem Google Fonts.
- Imagens estáticas em `public/img/aptk/`. Fotos de produto em `storage` (ver "Foto de produto").

## Tema

- **Dark fixo** desde a leva 03, por decisão do cliente: `<html data-theme="dark">` forçado.
- Os tokens do tema claro continuam definidos e o botão de troca existe no CSS, mas está **desativado**. Tudo novo precisa ficar bom no escuro.
- Seções "feature" podem usar `.on-dark` / `.section--dark`. Hoje dá no mesmo, porque o site todo é escuro.

## Paleta (`public/css/aptk-tokens.css`)

Cores de marca (Brandbook v1.0):

| token | valor | nome |
|---|---|---|
| `--color-cream` | `#E5DBC9` | Espumante |
| `--color-ink` | `#020203` | Cuba Libre |
| `--color-scotch` | `#BF6300` | Scotch (âmbar assinatura) |
| `--color-nude` | `#D0B29B` | Amarula |
| `--color-copper` | `#B87333` | Cobre (aproximação para tela) |
| `--copper-foil` | gradiente 135° `#7A4A22 → #C98A4E → #F0C79A → #B87333 → #8A5A2C` | cobre metálico |

Tokens semânticos no tema escuro (o que está no ar):

| token | valor no escuro |
|---|---|
| `--color-bg` | `#020203` (ink) |
| `--color-bg-card` | `#0F0E0C` |
| `--color-bg-elevated` | `#15130F` |
| `--color-bg-media` | `#020203` (fundo de foto de produto) |
| `--color-border` | `rgba(229,219,201,.16)` |
| `--color-primary` | `#D4720A` (âmbar um pouco mais quente que o scotch, para contraste) |
| `--color-primary-hover` | `#E78A1E` |
| `--color-primary-muted` | `#7A4A1E` |
| `--color-text` | `#E5DBC9` (cream) |
| `--color-text-muted` | `#B7AB95` |
| `--color-text-inverse` | `#020203` |
| `--color-success / warning / danger / info` | `#5BBE89` / `#D4720A` / `#E06A62` / `#6FA0D8` |
| `--gold-faint` / `--gold-line` | âmbar a 14% / 38% (fundos e fios) |
| `--shadow-card` | `0 18px 40px rgba(0,0,0,.5)` |
| `--overlay-dark` | `rgba(2,2,3,.55)` |

`--copper-foil` e `--color-nude` existem, mas quase não são usados no site hoje.

## Tipografia

| token | fonte | papel |
|---|---|---|
| `--font-display` | **PP Rader** (400, 700) | títulos, hero, nav, botões |
| `--font-mono` | PP Rader | eyebrows, preço, badges (rótulo pequeno, caixa alta, com tracking) |
| `--font-editorial` / `--font-script` | **Neue World** (200 Ultralight, 400) | texto editorial, frases de assinatura, nome de produto |
| `--font-body` / `--font-ui` | system-ui | UI funcional, formulários, texto corrido de interface |
| `--font-amithen` | Amithen | só nos rótulos do "Custom Simples" |

- Escala: `--text-xs` .75rem … `--text-5xl` 3rem (xs, sm, base, lg, xl, 2xl, 3xl, 4xl, 5xl).
- Raios: `--radius-sm` 4px, `md` 8px, `lg` 12px, `xl` 20px.
- Layout: `--container` 1200px (`.container-aptk`, padding lateral 24px), `--header-h` 76px.
- `h1`–`h4`: PP Rader 700, line-height 1.12. Body: system-ui, 1rem, line-height 1.6.

## Componentes visuais (`aptk.css`)

- **`.eyebrow`**: rótulo mono, caixa alta, tracking .22em, âmbar, com um fio de 26px antes.
- **`.section-title`**: título display com um fio âmbar de 64x2px embaixo, alinhado à esquerda.
- **`.section`**: padding vertical `clamp(64px, 9vw, 120px)`.
- **`.btn-aptk`**: sólido âmbar, texto escuro, caixa alta, tracking .08em, raio 8px. No hover sobe 1px e ganha brilho. **`.btn-aptk--outline`**: transparente, borda âmbar suave. `--block` = largura total.
- **`.card-aptk`**: fundo card, borda sutil, raio 12px. No hover a borda fica âmbar e ganha sombra.
- **`.badge-aptk`**: mono, caixa alta, borda e fundo âmbar fracos.
- **`.price-tag`**: preço em fonte mono.
- **`.product-name`**: nome do produto em Neue World 700, âmbar (a "assinatura" da marca).
- **Banda feature** (`.feature-band`): centralizada, com sunburst âmbar em cima, título grande, texto editorial e divisor ornamental.

Componentes Blade de marca (SVG em `currentColor`):
- `<x-brand.symbol>`: a coroa/símbolo APTK.
- `<x-brand.logo>`: símbolo + "APTK" (PP Rader 700, tracking .16em) + "Spirits" opcional.
- `<x-brand.sunburst>`: raios ornamentais (160px, âmbar).
- `<x-brand.divider>`: divisor ornamental (240px, âmbar).

## Foto de produto

- Sempre passa por `<x-product.image>`. Sem foto, mostra `<x-product.placeholder>`: moldura 2:3 com o símbolo da marca e "Produto ainda sem foto".
- **Padrão de foto: pacote FUNDO PRETO**, garrafa em retrato 2:3 (geradas em 800x1200), sobre `--color-bg-media` (preto), então a foto se funde com o card.
- Fotos de fundo branco foram **recusadas** (não combinam com o dark fixo).
- Na loja, produto só fica ativo com **preço + foto no padrão**. Hoje: **9 produtos ativos** (Boulevardier, Cosmopolitan, Dry Martini, Fitzgerald, Negroni Clássico, Neutral Gin, Old Fashioned, Sur L'Orange, Vermute Circollo Rosso).
- Galeria da página de produto: frente e verso do volume padrão, depois os outros volumes.

## Layouts

- **`layouts.public`**: loja e páginas institucionais.
  - Header sticky (76px, fundo com blur e 88% de opacidade, borda inferior). Logo à esquerda; nav à direita em PP Rader `--text-sm` cinza, com sublinhado âmbar animado no hover e na página ativa.
  - Ícones de busca (leva pra `/loja`), Entrar (ou avatar + dropdown quando logado) e carrinho com contador âmbar.
  - Antes do rodapé, banda feature "Feito por humanos inquietos" com o CTA "Conhecer os rótulos".
- **`layouts.marca`**: LP isolada no domínio da Ice4Pros.
- **`layouts.app` / `layouts.guest`**: área logada e telas de auth (Breeze, tematizadas).
- **`layouts.admin`**: painel, com CSS próprio (`admin.css`). Fora do escopo visual da loja.

**Nav principal**: Quem Somos · Produtos · Custom · Clube · Eventos · Franquias (LP externa) · Collabs.

**Rodapé** (4 colunas):
- Marca + frase "Drinks & histórias pra contar…" + Instagram, LinkedIn e WhatsApp (o WhatsApp ainda tem link `#`).
- Comprar: todos os itens apontam pra `/loja`, menos "Personalizar rótulo".
- Negócios: Collabs, Seja franqueado, Eventos & corporativo, Dicas e Novidades, Barín, Ice4Pros, Quem Somos.
- Newsletter (vira lead).

## Páginas (rota → seções)

**Home `/`**
1. Hero escuro: frase em script "Feito por humanos inquietos.", H1 "Criamos tendências, engarrafamos histórias e transformamos grandes momentos em rituais.", CTAs "Comprar agora" e "Seja membro", números em mono. À direita, vídeo 16:10 (`hero-negroni.mp4` ainda não existe; por enquanto aparece o poster `hero-bartender.jpg`).
2. Banda de produtos em destaque: card com foto, nome âmbar, descrição, Comprar / Ver detalhes.
3. "O que você procura": grade de categorias com foto.
4. Faixa de promos (Flagship / Collabs / Custom / Clube), cada uma com eyebrow, título, texto e CTA.
5. "Conteúdo para entusiastas": posts das Novidades.
6. Chamada do Clube.

**Loja `/loja`** (`?categoria=`): capa escura com título e grade de cards de produto. Categorias: Clássicos, Autorais, Bases, Barín, Ice4Pros.

**Produto `/produto/{slug}`**: galeria (principal + miniaturas), nome, seletor de volume (100/375/750 ml, cada um com preço próprio), adicionar ao carrinho. Abaixo, "Da mesma categoria".

**Custom `/custom`**: hero "Sua marca na nossa garrafa" → "Monte o seu rótulo em três escolhas" (Custom Simples, com fonte Amithen) → banda escura "Tem uma arte ou logo próprio?" (Full Custom, com formulário).

**Clube `/clube`**: hero "Um círculo privado para quem compartilha do mesmo repertório" → tiers ("Do primeiro gole ao colecionador") → Le Cercle ("Nem todos os produtos da APTK podem ser comprados.") → formulário de interesse.
- Tiers: Explorer (grátis), Collector (R$ 199/mês), Connoisseur (R$ 349/mês), Le Cercle (só por convite).

**Quem Somos `/quem-somos`**: "Small Batches" → hero "Da Apothek à APTK" → "Começou com uma inquietação" → "Três valores, um padrão" → "Uma casa, três marcas" → "Onde a alquimia acontece" (lojas) → "APTK pelo Brasil".

**Collabs / Eventos / Assinantes** (`/collabs`, `/eventos`, `/assinantes`): template único `shop.page`, preenchido por dados no controller.
- Estrutura: hero (dividido em duas colunas ou em banda com imagem) → chips → features → cases → passos → fechamento escuro.
- Collabs: "Tenha um coquetel para chamar de seu".
- Eventos: "Cada celebração merece um brinde à altura".

**Barín `/barin`**: "A alma artesanal da holding" → "Feita no ritmo do balcão" → "Artesanal de verdade, sem cerimônia" → "O que faz uma Barín ser Barín" → "A linha Barín" → "Onde a Barín entra" → "Quem já serve Barín". Alterna seções normais e `band-card`.

**Ice4Pros `/ice4pros`**: "O melhor gelo cristal do Brasil" → faixa Michelin → "Inovação em estado sólido, desde 2017" → "Uma pedra pra cada drink" → "Do balcão ao grande evento" → "No copo de quem entende" → "Por que gelo cristal gourmet" → fechamento.

**Novidades `/novidades`**: hero "Conteúdo para entusiastas", lista de posts, página do post.

**Compra**: Carrinho `/carrinho` → Checkout `/checkout` (aberto a visitante: card de Identificação, endereço, frete PAC/SEDEX, pagamento Pix/cartão/boleto) → Pedido confirmado → Meus pedidos `/meus-pedidos`.

**Conta**: login/cadastro (com declaração 18+), perfil, Minha assinatura.

## Imagens disponíveis (`public/img/aptk/`)

`hero-bartender`, `featured-negroni`, `cat-cosmopolitan`, `cat-dry-martini`, `cat-fitzgerald`, `cat-evento`, `cat-loja`, `cat-pessoas`, `clube-hero`, `eventos-hero`, `franquias-hero`, `parceiros-hero`, `loja-aptk`, `about-loja-2`, `about-loja-3`, `sobre-barman`, `sobre-interior`, `sobre-loja`, `sobre-pessoas` (todas .jpg).

## Voz da marca

Leve, detalhista, inspiradora. "Alquimia de histórias". "Feito por humanos inquietos". "Drinks & histórias pra contar". Narrativa e construção de valor acima de preço e desconto. Evitar tom técnico ou promocional.

## Inconsistências conhecidas (candidatas a ajuste)

- **Cor hardcoded** que sobrou:
  - `#0D0A06` no fundo do vídeo do hero (home), em `page` e na `ice4pros`;
  - `#111` e `#fff` no `custom`;
  - `#020203` no `clube`;
  - `#fff` no `checkout-success`.
- O comentário do `.product-name` diz "cursivo", mas a fonte é Neue World (editorial), não script.
- Links do rodapé "Kits & presentes" e "Edições especiais" vão pra `/loja` sem filtro; não existem essas categorias.
- WhatsApp do rodapé sem link.
- Vídeo do hero ainda não entregue.
- `--copper-foil` e `--color-nude` pouco aproveitados.
- Tokens do tema claro mantidos, mas sem uso.

## Como me entregar propostas

- Em CSS puro, usando os tokens acima (ou propondo token novo em `aptk-tokens.css`, com valor e papel).
- Dizer em qual arquivo vai: `aptk-tokens.css` (token), `aptk.css` (componente global) ou o `<style>` de qual página.
- HTML em Blade simples, reaproveitando `.section`, `.section-title`, `.eyebrow`, `.btn-aptk`, `.card-aptk` e os `<x-brand.*>`.
- Pensar primeiro no dark; mobile funcionando sem rolagem horizontal.
