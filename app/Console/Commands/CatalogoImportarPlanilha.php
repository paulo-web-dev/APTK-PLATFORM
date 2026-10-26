<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Leva 07: catálogo no ar pela planilha da gestão, sem esperar o Microvix.
 *
 * Lê o CSV normalizado (uma linha por produto + volume), agrupa por "grupo"
 * e cria/atualiza os produtos. Idempotente: rodar de novo não duplica
 * produto.
 *
 * Cuida SÓ de nome, volumes, preços, SKU e categoria (de produto novo).
 * Ativação e galeria são do produtos:aplicar-fotos — este comando nunca
 * mexe em `active` nem em product_images. Produto novo nasce inativo.
 *
 * Regras:
 * - O CSV atualiza o que tem valor e NUNCA remove o que já está à venda:
 *   volume existente sem preço na planilha fica com volume e preço atuais
 *   (e sai listado à parte para a gestão) — exceto PRECO_DESCARTADO.
 * - Produto existente mantém slug e categoria. Novo nasce com slug = grupo.
 * - Produto managedByErp() é pulado. Produto fora do CSV não é tocado.
 * - As colunas foto_frente/foto_verso do CSV são ignoradas.
 * - Linha com volume em config('catalogo.volumes_desativados') é ignorada
 *   (leva 15: 100 ml fora da venda; o CSV continua com as linhas).
 */
class CatalogoImportarPlanilha extends Command
{
    protected $signature = 'catalogo:importar-planilha
        {--dry-run : Mostra o que mudaria, sem gravar nada}
        {--csv= : CSV de origem (padrão: database/data/catalogo-planilha-2026-10.csv)}';

    protected $description = 'Importa nome, volumes, preços, SKU e categoria da planilha da gestão';

    private const CSV_PADRAO = 'database/data/catalogo-planilha-2026-10.csv';

    private const COLUNAS = ['grupo', 'nome', 'marca', 'volume', 'sku', 'preco'];

    /** Placeholder de estoque de produto novo (stock_qty 0 = esgotado na vitrine). */
    private const ESTOQUE_PLACEHOLDER = 30;

    /** Slug atual no site => grupo da planilha. */
    public const SLUG_ANTIGO = [
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
     * Preço da planilha com cara de custo (leva 08): o preço que já está no
     * banco é descartado em vez de mantido, e o produto fica "sem preço" até
     * a gestão confirmar e o preço voltar ao CSV.
     */
    private const PRECO_DESCARTADO = ['licor-chocolate-cacau-show', 'licor-doce-de-leite-borelli'];

    private bool $dryRun;

    /** slug => ProductCategory */
    private $categorias;

    private array $avisos = [];

    /** Linhas do CSV puladas por volume desativado ("grupo volume"). */
    private array $desativados = [];

    private array $mantidos = [];

    private array $resumo = [];

    public function handle(): int
    {
        // A instância do comando pode ser reaproveitada (testes): zera o estado.
        $this->avisos = [];
        $this->desativados = [];
        $this->mantidos = [];
        $this->resumo = [
            'criados' => [], 'atualizados' => [], 'sem_mudanca' => [], 'pulados_erp' => [],
            'sem_preco' => [], 'ativos_sem_preco' => [],
        ];

        $this->dryRun = (bool) $this->option('dry-run');
        $csv = $this->option('csv') ?: base_path(self::CSV_PADRAO);

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
            // Produto de teste de pagamento (catalogo:produto-teste): nunca
            // é da planilha, mesmo que alguém crie um grupo com esse nome.
            if ($grupo === CatalogoProdutoTeste::SLUG) {
                $this->avisos[] = "{$grupo}: é o produto de teste de pagamento, ignorado";

                continue;
            }

            $plano = $this->planejar($grupo, $linhas);
            $this->imprimir($plano);

            if (! $this->dryRun && in_array($plano['acao'], ['criar', 'atualizar'], true)) {
                $this->aplicar($plano);
            }
        }

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
            // Volume fora da venda (config catalogo.volumes_desativados): a
            // linha fica no CSV, mas não entra no produto.
            if (in_array($this->normalizarVolume($l['volume']), config('catalogo.volumes_desativados', []), true)) {
                $this->desativados[] = $l['grupo'].' '.$this->normalizarVolume($l['volume']);

                continue;
            }

            $grupos[$l['grupo']][] = [
                'nome'   => $l['nome'],
                'marca'  => $l['marca'],
                'volume' => $this->normalizarVolume($l['volume']),
                'sku'    => $l['sku'] !== '' ? $l['sku'] : null,
                'preco'  => $l['preco'] !== '' ? round((float) str_replace(',', '.', $l['preco']), 2) : null,
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

        $motivo = $precos !== [] ? null : ($descarta ? 'sem preço (descartado, confirmar com a gestão)' : 'sem preço');
        $mudancas = $product ? $this->diferencas($product, $attrs) : [];

        return [
            'acao'      => ! $product ? 'criar' : ($mudancas ? 'atualizar' : 'sem_mudanca'),
            'grupo'     => $grupo,
            'product'   => $product,
            'attrs'     => $attrs,
            'antigos'   => $antigos,
            'categoria' => $categoria,
            'mudancas'  => $mudancas,
            'motivo'    => $motivo,
        ];
    }

    private function acharProduto(string $grupo): ?Product
    {
        $slugAntigo = array_search($grupo, self::SLUG_ANTIGO, true);

        $query = fn (string $slug) => Product::with('category')
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

        return $mudou;
    }

    // ------------------------------------------------------------------ //
    // Execução

    private function aplicar(array $plano): void
    {
        DB::transaction(function () use ($plano) {
            $attrs = $plano['attrs'];

            if ($plano['acao'] === 'criar') {
                // Nasce inativo: quem liga é o produtos:aplicar-fotos.
                Product::create($attrs + [
                    'slug'        => $plano['grupo'],
                    'category_id' => $plano['categoria']?->id,
                    'stock_qty'   => self::ESTOQUE_PLACEHOLDER,
                    'active'      => false,
                    'featured'    => false,
                ]);
            } else {
                $plano['product']->update($attrs);
            }
        });

        if ($plano['acao'] === 'atualizar') {
            Log::info('catalogo:importar-planilha — produto atualizado', [
                'grupo' => $plano['grupo'], 'product_id' => $plano['product']->id, 'campos' => $plano['mudancas'],
            ]);
        }
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
        $alvo = $p ? "#{$p->id} {$p->slug}" : "novo, slug {$grupo}, nasce inativo";

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
        $this->newLine();

        $chave = ['criar' => 'criados', 'atualizar' => 'atualizados', 'sem_mudanca' => 'sem_mudanca'][$plano['acao']];
        $this->resumo[$chave][] = $grupo;
        if ($plano['motivo']) {
            $this->resumo['sem_preco'][$plano['motivo']][] = $grupo;
            // Este comando não desativa: avisa pra rodar o aplicar-fotos.
            if ($p?->active) {
                $this->resumo['ativos_sem_preco'][] = $grupo;
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
        $this->line('<options=bold>Resumo'.($this->dryRun ? ' (dry-run, nada gravado)' : '').'</>');
        $this->line('  criados: '.count($r['criados']));
        $this->line('  atualizados: '.count($r['atualizados']));
        $this->line('  sem mudança: '.count($r['sem_mudanca']));
        $this->line('  pulados (ERP): '.count($r['pulados_erp']).($r['pulados_erp'] ? ' ('.implode(', ', $r['pulados_erp']).')' : ''));
        if ($this->desativados) {
            $this->line('  linhas ignoradas por volume desativado (catalogo.volumes_desativados): '.count($this->desativados));
        }
        foreach ($r['sem_preco'] as $motivo => $grupos) {
            $this->line('  '.$motivo.' ('.count($grupos).'): '.implode(', ', $grupos));
        }

        if ($r['ativos_sem_preco']) {
            $this->newLine();
            $this->warn('Ativos e sem preço ('.count($r['ativos_sem_preco']).'): '.implode(', ', $r['ativos_sem_preco'])
                .'. Este comando não mexe em ativação: rode produtos:aplicar-fotos.');
        }
        $this->line('  ativação e galeria: produtos:aplicar-fotos');
    }

    private function rs(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }
}
