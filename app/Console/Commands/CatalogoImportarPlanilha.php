<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Leva 07: catálogo no ar pela planilha da gestão, sem esperar o Microvix.
 *
 * Lê o CSV normalizado (uma linha por produto + volume), agrupa por "grupo"
 * e cria/atualiza os produtos. Idempotente: rodar de novo não duplica
 * produto nem imagem.
 *
 * Regras (aprovadas na Etapa 1 da leva):
 * - O CSV atualiza o que tem valor e NUNCA remove o que já está à venda:
 *   volume existente sem preço na planilha fica com volume e preço atuais
 *   (e sai listado à parte para a gestão) — exceto PRECO_DESCARTADO.
 * - Produto existente mantém slug e categoria. Novo nasce com slug = grupo.
 * - Leva 08: ativo = ao menos 1 volume com preço. Foto não é mais condição:
 *   sem foto o site mostra o placeholder (<x-product.placeholder>).
 * - A foto antiga do site (products/{slug}.jpg e products/{slug}-serve.jpg)
 *   sai da galeria SEMPRE — só a linha, o arquivo fica no disco. Upload do
 *   admin (nome hash) nunca é tocado e fica na frente do pacote.
 * - Produto managedByErp() é pulado. Produto fora do CSV não é tocado, exceto
 *   a desativação explícita de DESATIVAR_FORA_DA_PLANILHA.
 * - Imagem: gerada em storage/app/public/products a partir das fotos de
 *   origem (fora do git). Se o destino já existe, é reaproveitado — por isso
 *   em produção o comando roda sem as fotos de origem e sem GD.
 */
class CatalogoImportarPlanilha extends Command
{
    protected $signature = 'catalogo:importar-planilha
        {--dry-run : Mostra o que mudaria, sem gravar nada}
        {--csv= : CSV de origem (padrão: database/data/catalogo-planilha-2026-10.csv)}
        {--fotos= : Pasta das fotos de origem (padrão: FUNDO PRETO/BAIXA)}';

    protected $description = 'Importa o catálogo (volumes, preços, imagens) da planilha da gestão';

    private const CSV_PADRAO = 'database/data/catalogo-planilha-2026-10.csv';

    private const FOTOS_PADRAO = 'public/img/fotos_produtos/FUNDO PRETO/BAIXA';

    private const COLUNAS = ['grupo', 'nome', 'marca', 'volume', 'sku', 'preco', 'foto_frente', 'foto_verso'];

    /** Lado maior da imagem gerada (px) e qualidade do JPG. */
    private const LADO_MAX = 1200;

    private const QUALIDADE = 82;

    /** Placeholder de estoque de produto novo (stock_qty 0 = esgotado na vitrine). */
    private const ESTOQUE_PLACEHOLDER = 30;

    /** Slug atual no site => grupo da planilha. */
    private const SLUG_ANTIGO = [
        'squadra-amendoas' => 'licor-de-amendoas-squadra',
        'squadra-coco'     => 'licor-de-coco-squadra',
        'neutral-gin'      => 'aptk-gin-2',
        'vodka-vdk'        => 'aptk-vodka',
    ];

    /** Categoria (slug) de produto NOVO. Produto existente mantém a sua. */
    private const CATEGORIA = [
        'cajuzaco-barin'   => 'barin',
        'negroni-barin'    => 'barin',
        'limoncello-barin' => 'barin',
        'gin-barin'        => 'barin',
        'vodka-barin'      => 'barin',

        'boulevardier'     => 'classicos',
        'dry-martini'      => 'classicos',
        'old-fashioned'    => 'classicos',
        'manhattan'        => 'classicos',
        'manhattan-aged'   => 'classicos',
        'espresso-martini' => 'classicos',
        'negroni-piselli'  => 'classicos',
        'negroni-aged'     => 'classicos',
        'negroni-jerez'    => 'classicos',
        'negroni-revoara'  => 'classicos',

        'caipirinha-arazul' => 'autorais',
        'canarinha'         => 'autorais',
        'coffee-negroni'    => 'autorais',
        'gallo-tropicalle'  => 'autorais',
        'ale-tonic'         => 'autorais',
        'o-drink-perfeito'  => 'autorais',

        'aptk-gin-1'                  => 'bases',
        'aptk-gin-2'                  => 'bases',
        'asuka-jin'                   => 'bases',
        'vermute-circollo-bianco'     => 'bases',
        'vermute-circollo-rosso'      => 'bases',
        'vermute-circollo-dry'        => 'bases',
        'scarlatti'                   => 'bases',
        'voglio-apperitivo'           => 'bases',
        'aptk-vodka'                  => 'bases',
        'licor-chocolate-cacau-show'  => 'bases',
        'licor-chocolate-squadra'     => 'bases',
        'licor-chocolate'             => 'bases',
        'licor-doce-de-leite-borelli' => 'bases',
        'licor-de-amendoas-squadra'   => 'bases',
        'licor-de-coco-squadra'       => 'bases',
        'whisky-cacau-show'           => 'bases',
    ];

    /**
     * Fora da planilha, só com a foto antiga do site (227x520, padrão
     * pré-leva 07): saem do ar e a foto antiga sai da galeria (leva 08; só a
     * linha). Preço e volumes não mudam.
     */
    private const DESATIVAR_FORA_DA_PLANILHA = ['cachaca-prata', 'cachaca-ouro'];

    /**
     * Preço da planilha com cara de custo (leva 08): o preço que já está no
     * banco é descartado em vez de mantido, e o produto fica "sem preço"
     * (inativo) até a gestão confirmar e o preço voltar ao CSV.
     */
    private const PRECO_DESCARTADO = ['licor-chocolate-cacau-show', 'licor-doce-de-leite-borelli'];

    /** Fotos citadas no CSV que não entram (034: Drink Perfeito em fundo azul). */
    private const FOTOS_PULADAS = ['Fundo Preto_034.jpg'];

    private bool $dryRun;

    private string $dirFotos;

    /** slug => ProductCategory */
    private $categorias;

    private array $avisos = [];

    private array $mantidos = [];

    private array $resumo = [];

    public function handle(): int
    {
        // A instância do comando pode ser reaproveitada (testes): zera o estado.
        $this->avisos = [];
        $this->mantidos = [];
        $this->resumo = [
            'criados' => [], 'atualizados' => [], 'sem_mudanca' => [], 'pulados_erp' => [],
            'ativos' => [], 'placeholder' => [], 'inativos' => [], 'sairam' => [], 'fora_desativados' => [],
        ];

        $this->dryRun = (bool) $this->option('dry-run');
        $csv = $this->option('csv') ?: base_path(self::CSV_PADRAO);
        $this->dirFotos = rtrim($this->option('fotos') ?: base_path(self::FOTOS_PADRAO), '/\\');

        if (! is_file($csv)) {
            $this->error("CSV não encontrado: {$csv}");

            return self::FAILURE;
        }

        try {
            $grupos = $this->lerCsv($csv);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->categorias = ProductCategory::all()->keyBy('slug');

        if ($this->dryRun) {
            $this->warn('DRY-RUN: nada será gravado.');
        }
        $this->newLine();

        foreach ($grupos as $grupo => $linhas) {
            $plano = $this->planejar($grupo, $linhas);
            $this->imprimir($plano);

            if (! $this->dryRun && $plano['acao'] !== 'pulado') {
                $this->aplicar($plano);
            }
        }

        $this->desativarForaDaPlanilha();

        $this->imprimirResumo();

        if (! $this->dryRun) {
            Log::info('catalogo:importar-planilha concluído', [
                'criados'     => count($this->resumo['criados']),
                'atualizados' => count($this->resumo['atualizados']),
                'sem_mudanca' => count($this->resumo['sem_mudanca']),
                'pulados_erp' => $this->resumo['pulados_erp'],
            ]);
        }

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------ //
    // Leitura

    /** @return array<string, array<int, array>> grupo => linhas (ordem do CSV) */
    private function lerCsv(string $caminho): array
    {
        $f = fopen($caminho, 'r');
        $cabecalho = fgetcsv($f);
        if ($cabecalho === false) {
            throw new \RuntimeException('CSV vazio.');
        }
        $cabecalho[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cabecalho[0]); // BOM
        $cabecalho = array_map('trim', $cabecalho);

        $faltando = array_diff(self::COLUNAS, $cabecalho);
        if ($faltando) {
            throw new \RuntimeException('CSV sem as colunas: '.implode(', ', $faltando));
        }

        $grupos = [];
        while (($valores = fgetcsv($f)) !== false) {
            if ($valores === [null] || count($valores) !== count($cabecalho)) {
                continue;
            }
            $l = array_map('trim', array_combine($cabecalho, $valores));
            if ($l['grupo'] === '') {
                continue;
            }

            $grupos[$l['grupo']][] = [
                'nome'        => $l['nome'],
                'marca'       => $l['marca'],
                'volume'      => $this->normalizarVolume($l['volume']),
                'sku'         => $l['sku'] !== '' ? $l['sku'] : null,
                'preco'       => $l['preco'] !== '' ? round((float) str_replace(',', '.', $l['preco']), 2) : null,
                'foto_frente' => $l['foto_frente'],
                'foto_verso'  => $l['foto_verso'],
            ];
        }
        fclose($f);

        return $grupos;
    }

    /** "375ml" → "375 ml" (formato do site, do carrinho e do Microvix). */
    private function normalizarVolume(string $volume): string
    {
        return preg_replace('/^(\d+)\s*ml$/i', '$1 ml', $volume);
    }

    private function ml(string $volume): int
    {
        return (int) $volume;
    }

    // ------------------------------------------------------------------ //
    // Plano (o que mudaria) — calculado igual no dry-run e na execução

    private function planejar(string $grupo, array $linhas): array
    {
        usort($linhas, fn ($a, $b) => $this->ml($a['volume']) <=> $this->ml($b['volume']));

        $product = $this->acharProduto($grupo);
        $nome = $linhas[0]['nome'];

        if ($product && $product->managedByErp()) {
            Log::warning('catalogo:importar-planilha — produto gerenciado pelo ERP, pulado', [
                'grupo' => $grupo, 'product_id' => $product->id,
            ]);
            $this->resumo['pulados_erp'][] = $grupo;

            return ['acao' => 'pulado', 'grupo' => $grupo, 'product' => $product];
        }

        // Preços: começa do que está à venda e sobrepõe o que a planilha tem.
        $descarta = in_array($grupo, self::PRECO_DESCARTADO, true);
        $antigos = $product && ! $descarta ? $product->sizeOptions() : [];
        $daPlanilha = [];
        foreach ($linhas as $l) {
            if ($l['preco'] !== null) {
                $daPlanilha[$l['volume']] = $l['preco'];
            }
        }

        $precos = $antigos;
        foreach ($daPlanilha as $volume => $preco) {
            $precos[$volume] = $preco;
        }
        uksort($precos, fn ($a, $b) => $this->ml($a) <=> $this->ml($b));

        $volumesNaPlanilha = array_column($linhas, 'volume');
        foreach (array_diff_key($antigos, $daPlanilha) as $volume => $preco) {
            $this->mantidos[] = [
                'grupo'  => $grupo,
                'volume' => $volume,
                'preco'  => $preco,
                'motivo' => in_array($volume, $volumesNaPlanilha, true) ? 'sem preço na planilha' : 'fora da planilha',
            ];
        }

        $sizes = array_keys($precos);
        $base = $precos ? end($precos) : ($product && ! $descarta ? (float) $product->price : 0.0);

        $attrs = [
            'name'        => $nome,
            'sizes'       => $sizes ?: null,
            'size_prices' => $precos ?: null,
            'price'       => $base,
            'sku'         => $this->escolherSku($linhas, $product, $sizes ? end($sizes) : null),
        ];

        $categoria = null;
        if (! $product) {
            $categoria = $this->categoriaDoGrupo($grupo, $linhas[0]['marca']);
        }

        $imagens = $this->planejarImagens($grupo, $linhas, $product, $sizes);

        // Leva 08: ativo = tem preço. Sem foto entra no ar com placeholder.
        $attrs['active'] = $precos !== [];
        $motivo = $attrs['active'] ? null : ($descarta ? 'sem preço (descartado, confirmar com a gestão)' : 'sem preço');

        $mudancas = $product ? $this->diferencas($product, $attrs) : [];
        $imagensMudam = $imagens['gerar'] > 0 || $imagens['linhas_novas'] > 0
            || $imagens['removidas'] !== [] || $imagens['reordena'];

        return [
            'acao'      => ! $product ? 'criar' : (($mudancas || $imagensMudam) ? 'atualizar' : 'sem_mudanca'),
            'grupo'     => $grupo,
            'product'   => $product,
            'attrs'     => $attrs,
            'antigos'   => $antigos,
            'categoria' => $categoria,
            'imagens'   => $imagens,
            'mudancas'  => $mudancas,
            'motivo'    => $motivo,
        ];
    }

    private function acharProduto(string $grupo): ?Product
    {
        $slugAntigo = array_search($grupo, self::SLUG_ANTIGO, true);

        $query = fn (string $slug) => Product::with(['images', 'category'])
            ->withCount('microvixProducts')
            ->where('slug', $slug)
            ->first();

        return ($slugAntigo !== false ? $query($slugAntigo) : null) ?? $query($grupo);
    }

    /**
     * SKU do maior volume com preço — o volume padrão da vitrine, contando os
     * mantidos do site — se a planilha tiver SKU para ele. Senão, o do maior
     * volume com preço na planilha; sem preço nenhum, mantém o atual ou pega
     * o do maior volume com SKU.
     */
    private function escolherSku(array $linhas, ?Product $product, ?string $volumePadrao): ?string
    {
        foreach ($linhas as $l) {
            if ($volumePadrao !== null && $l['volume'] === $volumePadrao && $l['sku'] !== null) {
                return $l['sku'];
            }
        }

        $comPreco = array_values(array_filter($linhas, fn ($l) => $l['preco'] !== null && $l['sku'] !== null));
        if ($comPreco) {
            return end($comPreco)['sku'];
        }
        if ($product) {
            return $product->sku;
        }
        $comSku = array_values(array_filter($linhas, fn ($l) => $l['sku'] !== null));

        return $comSku ? end($comSku)['sku'] : null;
    }

    private function categoriaDoGrupo(string $grupo, string $marca): ?ProductCategory
    {
        $slug = self::CATEGORIA[$grupo] ?? ($marca === 'barin' ? 'barin' : null);
        $categoria = $slug ? $this->categorias->get($slug) : null;

        if (! $categoria) {
            $this->avisos[] = "{$grupo}: sem categoria mapeada".($slug ? " (categoria '{$slug}' não existe)" : '').', criado sem categoria';
        }

        return $categoria;
    }

    /**
     * Com volume à venda: só fotos dos volumes à venda, do maior (o
     * defaultSize(), volume padrão da vitrine) para o menor, frente antes do
     * verso — a principal é a frente do volume padrão, ou a do maior volume à
     * venda que tenha foto. Sem nenhum volume à venda: todas as fotos, do
     * menor para o maior, prontas para quando o preço chegar.
     *
     * Galeria final = uploads do admin (na ordem atual, na frente) + pacote
     * novo. Saem (só a linha; o arquivo fica no disco): a foto antiga do site
     * e foto do pacote de volume que não está mais à venda. Sem nada, o site
     * mostra o placeholder.
     */
    private function planejarImagens(string $grupo, array $linhas, ?Product $product, array $sizes): array
    {
        $disco = Storage::disk('public');
        $temGd = function_exists('imagecreatefromjpeg');
        $novas = [];

        if ($sizes !== []) {
            $linhas = array_values(array_filter($linhas, fn ($l) => in_array($l['volume'], $sizes, true)));
            $linhas = array_reverse($linhas); // chegam do menor para o maior
        }

        foreach ($linhas as $l) {
            foreach (['frente' => $l['foto_frente'], 'verso' => $l['foto_verso']] as $lado => $arquivo) {
                if ($arquivo === '') {
                    continue;
                }
                if (in_array($arquivo, self::FOTOS_PULADAS, true)) {
                    $this->avisos[] = "{$grupo} {$l['volume']} {$lado}: {$arquivo} pulada de propósito";

                    continue;
                }

                $destino = $this->caminhoPacote($grupo, $l['volume'], $lado);
                $origem = $this->dirFotos.DIRECTORY_SEPARATOR.$arquivo;

                if ($disco->exists($destino)) {
                    $status = 'reaproveita';
                } elseif (is_file($origem) && $temGd) {
                    $status = 'gera';
                } else {
                    $this->avisos[] = "{$grupo} {$l['volume']} {$lado}: "
                        .(is_file($origem) ? "GD indisponível para gerar {$destino}" : "nem origem ({$arquivo}) nem destino ({$destino})")
                        .', seguiu sem a imagem';

                    continue;
                }

                $novas[] = ['destino' => $destino, 'origem' => $origem, 'lado' => $lado, 'status' => $status];
            }
        }

        // A primeira frente vira a principal do pacote: com volume à venda, a
        // do maior volume com foto (o padrão, quando ele tem); sem, a do menor.
        $iFrente = array_search('frente', array_column($novas, 'lado'), true);
        if ($iFrente !== false && $iFrente > 0) {
            array_unshift($novas, array_splice($novas, $iFrente, 1)[0]);
        }

        $existentes = $product ? $product->images : collect();
        $caminhosNovos = array_column($novas, 'destino');

        $antigas = $product ? $this->caminhosFotoAntiga($product) : [];
        $removidas = $existentes->filter(fn ($i) => in_array($i->path, $antigas, true)
            || ($this->ehDoPacote($grupo, $i->path) && ! in_array($i->path, $caminhosNovos, true)))->values();
        $doAdmin = $existentes
            ->reject(fn ($i) => in_array($i->path, $antigas, true) || $this->ehDoPacote($grupo, $i->path))
            ->pluck('path')
            ->all();

        $desejada = array_merge($doAdmin, $caminhosNovos);
        $reordena = false;
        foreach ($desejada as $ordem => $path) {
            $img = $existentes->firstWhere('path', $path);
            if ($img && ($img->sort_order !== $ordem || $img->is_primary !== ($ordem === 0))) {
                $reordena = true;
            }
        }

        return [
            'novas'        => $novas,
            'do_admin'     => $doAdmin,
            'desejada'     => $desejada,
            'removidas'    => $removidas->all(),
            'reordena'     => $reordena,
            'gerar'        => count(array_filter($novas, fn ($n) => $n['status'] === 'gera')),
            'linhas_novas' => count(array_filter($caminhosNovos, fn ($p) => ! $existentes->contains('path', $p))),
            'total_depois' => count($desejada),
        ];
    }

    /** storage/app/public/products/{grupo}-{vol}-{frente|verso}.jpg */
    private function caminhoPacote(string $grupo, string $volume, string $lado): string
    {
        return 'products/'.$grupo.'-'.str_replace(' ', '', $volume).'-'.$lado.'.jpg';
    }

    private function ehDoPacote(string $grupo, string $path): bool
    {
        return (bool) preg_match('#^products/'.preg_quote($grupo, '#').'-\d+ml-(frente|verso)\.jpg$#', $path);
    }

    /** Foto antiga do site (pré-leva 07): garrafa e "-serve", pelo slug. */
    private function caminhosFotoAntiga(Product $product): array
    {
        return ["products/{$product->slug}.jpg", "products/{$product->slug}-serve.jpg"];
    }

    private function diferencas(Product $product, array $attrs): array
    {
        $mudou = [];

        if ($product->name !== $attrs['name']) {
            $mudou[] = 'name';
        }
        if (($product->sizes ?? null) !== $attrs['sizes']) {
            $mudou[] = 'sizes';
        }
        $atuais = array_map(fn ($v) => round((float) $v, 2), $product->size_prices ?? []);
        $novos = array_map(fn ($v) => round((float) $v, 2), $attrs['size_prices'] ?? []);
        if ($atuais != $novos || array_keys($atuais) !== array_keys($novos)) {
            $mudou[] = 'size_prices';
        }
        if (round((float) $product->price, 2) !== round((float) $attrs['price'], 2)) {
            $mudou[] = 'price';
        }
        if ($product->sku !== $attrs['sku']) {
            $mudou[] = 'sku';
        }
        if ($product->active !== $attrs['active']) {
            $mudou[] = 'active';
        }

        return $mudou;
    }

    // ------------------------------------------------------------------ //
    // Execução

    private function aplicar(array $plano): void
    {
        $disco = Storage::disk('public');

        // Arquivos primeiro (fora da transação): gerar de novo é inofensivo.
        foreach ($plano['imagens']['novas'] as $nova) {
            if ($nova['status'] === 'gera') {
                $disco->put($nova['destino'], $this->redimensionar($nova['origem']));
            }
        }

        DB::transaction(function () use ($plano) {
            $attrs = $plano['attrs'];

            if ($plano['acao'] === 'criar') {
                $product = Product::create($attrs + [
                    'slug'        => $plano['grupo'],
                    'category_id' => $plano['categoria']?->id,
                    'stock_qty'   => self::ESTOQUE_PLACEHOLDER,
                    'featured'    => false,
                ]);
            } else {
                $product = $plano['product'];
                $product->update($attrs);
            }

            $img = $plano['imagens'];

            // Só a linha: o arquivo antigo fica no disco.
            foreach ($img['removidas'] as $removida) {
                $removida->delete();
            }

            // Upload do admin só muda de ordem; foto do pacote é criada se faltar.
            foreach ($img['desejada'] as $ordem => $path) {
                $posicao = ['sort_order' => $ordem, 'is_primary' => $ordem === 0];

                if (in_array($path, $img['do_admin'], true)) {
                    ProductImage::where('product_id', $product->id)->where('path', $path)->update($posicao);
                } else {
                    ProductImage::updateOrCreate(
                        ['product_id' => $product->id, 'path' => $path],
                        ['alt' => $attrs['name']] + $posicao,
                    );
                }
            }
        });

        if ($plano['acao'] === 'atualizar') {
            Log::info('catalogo:importar-planilha — produto atualizado', [
                'grupo' => $plano['grupo'], 'product_id' => $plano['product']->id, 'campos' => $plano['mudancas'],
            ]);
        }
    }

    /** JPG com no máximo LADO_MAX px no lado maior, qualidade QUALIDADE. */
    private function redimensionar(string $origem): string
    {
        $src = imagecreatefromjpeg($origem);
        $w = imagesx($src);
        $h = imagesy($src);
        $escala = min(1, self::LADO_MAX / max($w, $h));
        $nw = (int) round($w * $escala);
        $nh = (int) round($h * $escala);

        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        ob_start();
        imagejpeg($dst, null, self::QUALIDADE);
        $binario = ob_get_clean();
        imagedestroy($dst);

        return $binario;
    }

    // ------------------------------------------------------------------ //
    // Saída

    private function imprimir(array $plano): void
    {
        $grupo = $plano['grupo'];

        if ($plano['acao'] === 'pulado') {
            $this->line("<fg=yellow>[PULADO ERP]</> {$grupo} (#{$plano['product']->id}): gerenciado pelo ERP, intocado");
            $this->newLine();

            return;
        }

        $p = $plano['product'];
        $attrs = $plano['attrs'];
        $rotulo = match ($plano['acao']) {
            'criar'       => '<fg=green>[CRIA]</>',
            'atualizar'   => '<fg=cyan>[ATUALIZA]</>',
            'sem_mudanca' => '<fg=gray>[SEM MUDANÇA]</>',
        };
        $categoria = $p ? ($p->category?->name ?? 'sem categoria') : ($plano['categoria']?->name ?? 'sem categoria');
        $alvo = $p ? "#{$p->id} {$p->slug}" : "novo, slug {$grupo}";

        $this->line("{$rotulo} {$grupo} ({$alvo}) · {$categoria}");

        if ($p && $p->name !== $attrs['name']) {
            $this->line("  nome: {$p->name} → {$attrs['name']}");
        } elseif (! $p) {
            $this->line("  nome: {$attrs['name']}");
        }

        if ($attrs['size_prices']) {
            $partes = [];
            foreach ($attrs['size_prices'] as $volume => $preco) {
                $antes = $plano['antigos'][$volume] ?? null;
                $partes[] = $antes === null
                    ? "{$volume}: — → {$this->rs($preco)}"
                    : (round($antes, 2) === round($preco, 2) ? "{$volume}: {$this->rs($preco)}" : "{$volume}: {$this->rs($antes)} → {$this->rs($preco)}");
            }
            $this->line('  preços: '.implode(' | ', $partes));
        } else {
            $this->line('  preços: nenhum volume com preço');
        }

        if ($p && $p->sku !== $attrs['sku']) {
            $this->line('  sku: '.($p->sku ?? '—').' → '.($attrs['sku'] ?? '—'));
        } elseif (! $p) {
            $this->line('  sku: '.($attrs['sku'] ?? '—'));
        }

        $img = $plano['imagens'];
        $desc = $img['novas'] === []
            ? 'sem foto do pacote'
            : count($img['novas']).' da planilha (gera '.$img['gerar'].', reaproveita '.(count($img['novas']) - $img['gerar']).')';
        if ($img['do_admin']) {
            $desc .= ' · '.count($img['do_admin']).' do admin (mantidas, na frente)';
        }
        if ($img['removidas']) {
            $desc .= ' · sai(em) da galeria: '.implode(', ', array_map(fn ($i) => basename($i->path), $img['removidas']));
        }
        $this->line("  imagens: {$desc}");
        $this->line('  principal: '.($img['desejada'] ? basename($img['desejada'][0]) : '— (placeholder)'));

        $ativoAntes = $p ? ($p->active ? 'sim' : 'não').' → ' : '';
        $this->line('  ativo: '.$ativoAntes.($attrs['active'] ? 'sim' : 'não ('.$plano['motivo'].')'));
        $this->newLine();

        $chave = ['criar' => 'criados', 'atualizar' => 'atualizados', 'sem_mudanca' => 'sem_mudanca'][$plano['acao']];
        $this->resumo[$chave][] = $grupo;
        if ($attrs['active']) {
            $this->resumo['ativos'][] = $grupo;
            if ($img['desejada'] === []) {
                $this->resumo['placeholder'][] = $grupo;
            }
        } else {
            $this->resumo['inativos'][$plano['motivo']][] = $grupo;
            if ($p?->active) {
                $this->resumo['sairam'][] = "{$grupo} ({$plano['motivo']})";
            }
        }
    }

    private function imprimirResumo(): void
    {
        $this->line('<options=bold>Volumes mantidos sem preço na planilha</> (seguem à venda com o preço atual)');
        if ($this->mantidos === []) {
            $this->line('  nenhum');
        }
        foreach ($this->mantidos as $m) {
            $this->line("  {$m['grupo']} {$m['volume']}: {$this->rs($m['preco'])} ({$m['motivo']})");
        }
        $this->newLine();

        if ($this->avisos) {
            $this->line('<options=bold>Avisos</>');
            foreach ($this->avisos as $aviso) {
                $this->line("  {$aviso}");
            }
            $this->newLine();
        }

        $r = $this->resumo;
        $inativos = array_sum(array_map('count', $r['inativos']));
        $this->line('<options=bold>Resumo'.($this->dryRun ? ' (dry-run, nada gravado)' : '').'</>');
        $this->line('  criados: '.count($r['criados']));
        $this->line('  atualizados: '.count($r['atualizados']));
        $this->line('  sem mudança: '.count($r['sem_mudanca']));
        $this->line('  pulados (ERP): '.count($r['pulados_erp']).($r['pulados_erp'] ? ' ('.implode(', ', $r['pulados_erp']).')' : ''));
        $this->line('  ativos ('.count($r['ativos']).'): '.implode(', ', $r['ativos']));
        $this->line('    com placeholder, sem foto ('.count($r['placeholder']).'): '.($r['placeholder'] ? implode(', ', $r['placeholder']) : 'nenhum'));
        $this->line("  inativos: {$inativos}");
        foreach ($r['inativos'] as $motivo => $grupos) {
            $this->line('    '.$motivo.' ('.count($grupos).'): '.implode(', ', $grupos));
        }
        $this->line('  saíram do ar ('.count($r['sairam']).'): '.($r['sairam'] ? implode(', ', $r['sairam']) : 'nenhum'));
        $this->line('  fora da planilha desativados ('.count($r['fora_desativados']).'): '
            .($r['fora_desativados'] ? implode(', ', $r['fora_desativados']) : 'nenhum'));
    }

    /**
     * Produtos fora da planilha que não podem seguir no ar só com foto antiga
     * (leva 07). Desativa e tira a foto antiga da galeria (leva 08; o arquivo
     * fica no disco) — preço e volumes ficam como estão.
     * Passo do próprio comando para rodar igual em produção.
     */
    private function desativarForaDaPlanilha(): void
    {
        foreach (self::DESATIVAR_FORA_DA_PLANILHA as $slug) {
            $product = Product::withCount('microvixProducts')->where('slug', $slug)->first();

            if (! $product) {
                $this->avisos[] = "{$slug}: não existe, nada a desativar";

                continue;
            }
            if ($product->managedByErp()) {
                $this->line("<fg=yellow>[PULADO ERP]</> {$slug} (#{$product->id}): gerenciado pelo ERP, intocado");
                $this->resumo['pulados_erp'][] = $slug;

                continue;
            }
            $antigas = $product->images()->whereIn('path', $this->caminhosFotoAntiga($product))->get();

            if (! $product->active && $antigas->isEmpty()) {
                continue;
            }

            $this->line("<fg=cyan>[DESATIVA]</> {$slug} (#{$product->id}): fora da planilha, só foto antiga"
                .($antigas->isNotEmpty() ? ' · sai(em) da galeria: '.$antigas->map(fn ($i) => basename($i->path))->implode(', ') : ''));
            $this->resumo['fora_desativados'][] = $slug;

            if (! $this->dryRun) {
                // Só a linha da foto antiga; o arquivo fica no disco.
                $antigas->each->delete();
                $product->update(['active' => false]);
                Log::info('catalogo:importar-planilha — produto fora da planilha desativado', [
                    'slug' => $slug, 'product_id' => $product->id,
                ]);
            }
        }
        $this->newLine();
    }

    private function rs(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }
}
