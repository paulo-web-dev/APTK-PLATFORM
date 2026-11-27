# Ice4Pros — alterações solicitadas pela marca (27/11/2026)

Fonte: documento "Alterações Site Ice4Pros" enviado pela APTK.
Imagens de referência (prints do site atual e layouts sugeridos) em `docs/ice4pros-ref/`.
Os textos entre aspas ou em bloco são **literais**: usar exatamente como estão.

## Regras gerais

- Em todo texto corrido, a marca se escreve **"Ice4Pros"** (nunca "ICE4PROS"). O logo não muda. Eyebrows e títulos que já são caixa alta por CSS podem continuar em caixa alta visual, mas o texto-fonte deve ser "Ice4Pros".
- WhatsApp comercial da Ice4Pros (número próprio, diferente da APTK): **+55 11 91724-8795**.
- Mensagem pré-preenchida em **todos** os CTAs comerciais das páginas da Ice4Pros (LP, portfólio, header, rodapé, botão flutuante):
  > Olá! Conheci a Ice4Pros através do site e gostaria de receber mais informações sobre o portfólio e as soluções disponíveis.

## Fotos (baixar do Google Drive e salvar com estes nomes)

Pasta: `public/img/marcas/ice4pros/fotos/`

| Uso | Arquivo |
|---|---|
| Fundo da página de produtos (BACKGROUND PRODUTOS I4P'S) | `bg-produtos.jpg` |
| Portfólio: SUPER 30 | `super30.jpg` |
| Portfólio: CUBO 50 (também usada no formato "Cubos" da home) | `cubo50.jpg` |
| Portfólio: CUBO 50X65 | `cubo50x65.jpg` |
| Portfólio: RETANGULAR (também usada no formato "Retangulares" da home) | `retangular.jpg` |
| Portfólio: ESFERA 2 | `esfera2.jpg` |
| Portfólio: DIAMANTE 2 | `diamante2.jpg` |
| Portfólio: ID NO COPO | `id-no-copo.jpg` |
| Portfólio: ELEMENTOS AFETIVOS | `elementos-afetivos.jpg` |
| Portfólio: ACABAMENTOS PREMIUM | `acabamentos-premium.jpg` |
| Home, formato Esfera (link "FORMATO ESFERA", diferente do Esfera 2) | `formato-esfera.jpg` |
| Home, formato Diamante (link "FORMATO: DIAMANTE", diferente do Diamante 2) | `formato-diamante.jpg` |
| Home, "Sobre a marca": produção do gelo cristal | `producao-gelo.jpg` |
| Clientes: Copacabana Palace | `clientes/copacabana-palace.jpg` |
| Clientes: Fasano | `clientes/fasano.jpg` |

Pendentes (ainda não enviados pela marca): fotos dos outros 8 clientes e o logo da Small Batches.
Enquanto não chegarem, usar placeholder no estilo da referência `06-clientes-layout-ref.png` (ícone de taça em traço + "Foto do drink"), e manter o logo atual no rodapé com um TODO.

---

## 1. Home — Hero

- Identificação institucional (eyebrow): "ICE4PROS · MARCA DA HOLDING SMALL BATCHES"
- Título: **Gelo em estado de arte.**
- Subtítulo:
  > Gelo cristal profissional, padronizado e personalizável para quem cria, serve e celebra. Desde 2017, unimos água, hiperfiltração, precisão técnica e logotipagem pioneira no país para ampliar possibilidades criativas e elevar a experiência da alta coquetelaria.
- CTAs: manter "CONHEÇA OS PRODUTOS" e "FALAR COM O COMERCIAL".
  - "Conheça os produtos" → página de portfólio Ice4Pros (seção 2).
  - "Falar com o comercial" → WhatsApp Ice4Pros com a mensagem padrão.

## 2. Página de produtos / portfólio (destino de "Conheça os produtos" e "Ver produtos")

Hoje o destino é `/loja?categoria=ice4pros`, com cores da APTK. Precisa ter a identidade da Ice4Pros.

- Cores e identidade visual da Ice4Pros (não da APTK).
- Trocar o título em texto "Ice4Pros" pelo **logo oficial** (wordmark).
- "Explore nossos coquetéis" → **"Explore nossos formatos"**
- Trocar "Gelo, insumos e linha para empresas — o B2B da holding" por:
  > Diferentes formatos, infinitas possibilidades. Conheça nosso portfólio de gelo cristal profissional, desenvolvido para valorizar cada criação, serviço e experiência.
- Imagem de fundo: `bg-produtos.jpg`.
- Mostrar **todos** os formatos do portfólio em cards, no layout das referências `02-portfolio-layout-ref-*.png` (foto grande com cantos arredondados, nome, linha de especificação em turquesa, drinks ideais e pack), na ordem:
  SUPER 30 · CUBO 50 · CUBO 50X65 · RETANGULAR · ESFERA 2 · DIAMANTE 2 · ID NO COPO · ELEMENTOS AFETIVOS · ACABAMENTOS PREMIUM
  - Dados por formato que aparecem nas referências: SUPER30 (30 × 30 mm · Volume e frescor · Ideais para Caipirinha · PACK: 150 CUBOS); CINQUENTA (50 × 50 mm · Presença no copo · Negroni · Old Fashioned · Penicillin · PACK: 35 CUBOS); 50 × 65 (50 × 65 mm · Baixa diluição · Godfather · Whiskies Premium · Fitzgerald · PACK: 26 CUBOS). Os demais: usar o que já existir no cadastro; o que faltar fica marcado como pendente para a marca preencher.
- Encerramento: o mesmo banner de encerramento da home (seção 8), especificando "PRODUTOS Ice4Pros".
- Rodapé desta página: trocar o logo da APTK pelo **logo da Small Batches** (arquivo pendente).

## 3. Home — Sobre a marca

- Título: **INOVAÇÃO EM ESTADO SÓLIDO** (antes: "Inovação em estado sólido, desde 2017")
- Texto (dois parágrafos):
  > Há quase uma década, a Ice4Pros contribui para transformar a percepção do gelo na coquetelaria brasileira: de um simples insumo a um elemento fundamental na construção de cada bebida.
  >
  > Com estrutura fabril própria e tecnologia especializada de corte, desenvolvemos soluções que combinam excelência técnica e liberdade criativa. Da diversidade de formatos às possibilidades de personalização, nosso compromisso é respeitar a intenção de cada receita e potencializar o trabalho dos profissionais da coquetelaria.
- Indicadores: substituir "2017 — Fundação" por **"FÁBRICA — PRÓPRIA"**, ficando: FÁBRICA / PRÓPRIA · 3× / GUIA MICHELIN · 4× / MAIS DURAÇÃO (ref. `03-indicadores-novo.png`).
- Onde hoje está "foto — produção do gelo cristal em breve": usar `producao-gelo.jpg`.

## 4. Home — Produtos (formatos)

- Título: **CADA FORMATO, UMA INTENÇÃO.** (antes: "Uma pedra pra cada drink")
- Subtítulo:
  > Formatos padronizados, transparência excepcional e precisão para elevar a experiência de cada coquetel.
- Cubos — imagem `cubo50.jpg` — texto: "Ideais para Negroni, Old Fashioned, Penicillin, Boulevardier e suas variações."
- Esfera — imagem `formato-esfera.jpg` — texto: "Ideais para Whisky on the Rocks, Godfather, Rusty Nail e coquetéis autorais que valorizam a apresentação e o controle da diluição."
- Diamantes — imagem `formato-diamante.jpg` — texto: "Ideais para releituras de clássicos servidos em taças coupe, como Manhattan, Daiquiri, Aviation e Sidecar, além de criações autorais."
- Retangulares — imagem `retangular.jpg` — texto: "Ideais para Paloma, Highball, Tom Collins e demais long drinks."

## 5. Home — Soluções

- Título: **SOLUÇÕES À ALTURA DE CADA EXPERIÊNCIA.** (antes: "Do balcão ao grande evento")
- Card Trade — título **BARES, RESTAURANTES & HOSPITALIDADE**:
  > Abastecimento recorrente com qualidade, precisão e consistência. Soluções desenvolvidas para atender às necessidades de cada operação, respeitando a identidade de suas criações e a excelência do serviço.
- Card Marcas — título **GELO PERSONALIZADO**:
  > Transformamos o gelo em uma extensão da identidade de cada marca. Personalizações, formatos exclusivos e soluções criativas para ativações, lançamentos e experiências que valorizam cada detalhe.
- Card Eventos — título **EVENTOS E CELEBRAÇÕES**:
  > Soluções em gelo profissional que aliam capacidade produtiva, planejamento e consistência para casamentos, celebrações, festivais e eventos corporativos.

## 6. Home — Principais clientes

- Título: **AO LADO DE QUEM ELEVA A COQUETELARIA.** (antes: "No copo de quem entende")
- Adicionar: Casa Camolese, Copacabana Palace, Fasano, Nosso Coquetelaria.
- Remover: Cacau Show, Braz Pizzaria, Hotel Pullman.
- Os 10 clientes, na ordem da referência `06-clientes-layout-ref.png`:
  Copacabana Palace · Fasano · Emiliano · Hotel Unique · Casa Camolese · Nosso Coquetelaria · Guilhotina · Astor · Gurumê · Pirajá
- Layout: duas fileiras de cinco; nome do estabelecimento numa caixa com contorno em cima; foto de coquetel do parceiro embaixo, vertical 3:4, todas do mesmo tamanho. Responsivo no mobile (2 colunas).

## 7. Home — Diferenciais

- Título: **A EXCELÊNCIA ESTÁ NOS DETALHES.** (antes: "Por que gelo cristal gourmet")
- Trocar a numeração 01/02/03 por ícones minimalistas de traço fino, na cor turquesa da marca (refs. `07-icone-card*.png`).
- Card 1 — **QUALIDADE E PRECISÃO**:
  > Produzidos com água hiperfiltrada e rigorosos processos de tratamento, corte e padronização, os gelos Ice4Pros unem pureza, consistência e desempenho em cada formato.
- Card 2 — **CONTROLE DA DILUIÇÃO**:
  > Estrutura compacta e processos especializados de produção proporcionam um derretimento mais lento e controlado, preservando o equilíbrio, a intensidade e o perfil sensorial de cada coquetel por mais tempo.
- Card 3 — **TRANSPARÊNCIA E ACABAMENTO**:
  > Técnicas especializadas de produção e lapidação resultam em gelos cristalinos, com geometria precisa e acabamento refinado, valorizando a apresentação e a experiência de cada coquetel.

## 8. Home — Encerramento e conversão comercial

(Mesmo banner também na página de portfólio.)

- Logo: a mesma versão usada no início da página (ref. `08-logo-encerramento-ref.png`).
- Título: **LEVE GELO EM ESTADO DE ARTE PARA O SEU NEGÓCIO.** (antes: "Leve o gelo cristal pro seu negócio")
- Subtítulo:
  > Conheça as soluções Ice4Pros para bares, restaurantes, hotéis, eventos e revendas. Nosso time está preparado para entender as necessidades do seu negócio e oferecer as melhores condições comerciais.
- Botões:
  - **FALAR COM O COMERCIAL**: hoje o texto só aparece no hover. Corrigir para o mesmo estilo de "Ver produtos": texto e contorno pretos sobre o fundo turquesa. Legível em todos os estados (normal, hover, foco, ativo), desktop e mobile. Destino: WhatsApp Ice4Pros com a mensagem padrão.
  - **VER PRODUTOS**: destino = página de portfólio Ice4Pros (seção 2).
