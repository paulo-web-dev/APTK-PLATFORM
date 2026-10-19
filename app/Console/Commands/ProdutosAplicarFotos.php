<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Dono da ativação e da galeria dos produtos (o catalogo:importar-planilha
 * não mexe em nenhuma das duas).
 *
 * Ativo = Product::podeAtivar() (a mesma regra do admin): tem preço E tem
 * foto no padrão na galeria — pacote fundo preto, foto de manifesto fundo
 * preto ou upload do admin. Só placeholder, foto antiga do site ou fundo
 * branco = inativo.
 *
 * --sem-galeria: só aplica a regra de ativação sobre a galeria atual, sem
 * tocar em product_images. O manifesto entra só no relatório.
 *
 * Sem a opção, a galeria vem do manifesto (public/img/fotos_produtos/
 * manifesto.json, uma entrada por produto × tamanho), e SÓ de manifesto
 * fundo preto: campo `fundo` da entrada ou --fundo=preto. Fundo branco (o
 * manifesto de 375ML/750ML) é recusado — não combina com o tema escuro.
 * - SKU do manifesto casa com `products.sku` OU com o SKU do volume na
 *   planilha do catálogo (o produto guarda um SKU só; os dos outros volumes
 *   vivem na planilha). "G750." e "G2750." (com ponto) são aliases dos SKUs
 *   sem ponto: o ponto final é ignorado na comparação.
 * - Produto com frente: galeria = frente e verso do volume padrão primeiro,
 *   depois os outros volumes; upload do admin fica atrás. Foto de volume que
 *   não está à venda não entra (sai no relatório).
 * - Produto sem frente no manifesto fica com a galeria que tem. Nada é
 *   apagado: imagem que sai da galeria perde só a linha, o arquivo fica.
 * - Produto de teste de pagamento (catalogo:produto-teste) nunca é tocado.
 * - Antes de gravar, o estado (ativo + galeria) de todos os produtos vai pra
 *   storage/app/fotos-manifesto/backup-*.json; --reverter volta a partir dele.
 * - Versão web: recorte central 2:3 em 800x1200, JPG 82, em
 *   storage/app/public/products/foto-preto-{sku}-{frente|verso}.jpg. O original não
 *   é tocado; destino existente é reaproveitado (produção roda sem GD e sem
 *   as fotos de origem, que ficam fora do git).
 */
class ProdutosAplicarFotos extends Command
{
    protected $signature = 'produtos:aplicar-fotos
        {--dry-run : Só lista quem ativa, quem desativa e quem troca de foto}
        {--sem-galeria : Só aplica a regra de ativação, sem tocar em product_images}
        {--reverter= : Volta o estado salvo (caminho do backup, ou "ultimo")}
        {--manifesto= : Manifesto de origem (padrão: public/img/fotos_produtos/manifesto.json)}
        {--fundo= : Fundo das fotos do manifesto quando a entrada não diz (só "preto" monta galeria)}';

    protected $description = 'Vincula as fotos do manifesto e deixa ativos só os produtos com foto de frente';

    private const MANIFESTO_PADRAO = 'public/img/fotos_produtos/manifesto.json';

    private const PLANILHA = 'database/data/catalogo-planilha-2026-10.csv';

    private const DIR_BACKUP = 'fotos-manifesto';

    /** Versão web: recorte 2:3 (a moldura da vitrine) neste tamanho. */
    private const LARGURA = 800;

    private const ALTURA = 1200;

    private const QUALIDADE = 82;

    private bool $dryRun;

    private bool $semGaleria;

    private string $dirFotos;

    private array $relatorio = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->semGaleria = (bool) $this->option('sem-galeria');

        if ($this->dryRun) {
            $this->warn('DRY-RUN: nada será gravado.');
        }
        if ($this->semGaleria) {
            $this->warn('SEM GALERIA: product_images não é tocada, só a ativação.');
        }
        $this->newLine();

        if ($this->option('reverter') !== null) {
            return $this->reverter($this->option('reverter'));
        }

        $caminho = $this->option('manifesto') ?: base_path(self::MANIFESTO_PADRAO);
        $this->dirFotos = dirname($caminho);

        // --sem-galeria usa o manifesto só no relatório: em produção a pasta
        // das fotos não existe (fora do git) e a ativação roda sem ele.
        if (! is_file($caminho) && $this->semGaleria) {
            $this->warn("Manifesto não encontrado ({$caminho}): relatório do manifesto fica de fora.");
            $this->newLine();
        } elseif (! is_file($caminho)) {
            $this->error("Manifesto não encontrado: {$caminho}");

            return self::FAILURE;
        }

        $manifesto = is_file($caminho) ? json_decode(file_get_contents($caminho), true) : [];
        if (! is_array($manifesto)) {
            $this->error('Manifesto não é um JSON válido.');

            return self::FAILURE;
        }

        if (! $this->semGaleria) {
            $foraDoPadrao = array_filter($manifesto, fn ($e) => ! empty($e['frente'])
                && ($e['fundo'] ?? $this->option('fundo')) !== 'preto');
            if ($foraDoPadrao) {
                $this->error(count($foraDoPadrao).' foto(s) do manifesto sem fundo=preto (campo fundo ou --fundo=preto): '
                    .'galeria só com fundo preto. Use --sem-galeria para aplicar só a ativação.');

                return self::FAILURE;
            }
        }

        $this->relatorio = [
            'sem_produto' => [], 'sem_sku' => [], 'sem_frente' => [], 'sem_preco' => [],
            'fora_de_venda' => [], 'conflito' => [], 'aviso' => [],
        ];

        $produtos = Product::with('images')
            ->where('slug', '!=', CatalogoProdutoTeste::SLUG)
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $fotosPorProduto = $this->casarManifesto($manifesto, $produtos);
        $planos = $produtos->map(fn (Product $p) => $this->planejar($p, $fotosPorProduto[$p->id] ?? []));

        $this->imprimir($planos);
        $this->imprimirRelatorio();

        if ($this->dryRun) {
            return self::SUCCESS;
        }

        $mudam = $planos->filter(fn ($pl) => $pl['muda_ativo'] || $pl['muda_galeria']);
        if ($mudam->isEmpty()) {
            $this->info('Nada a mudar.');

            return self::SUCCESS;
        }

        $backup = $this->salvarBackup($produtos);
        $this->info("Estado anterior salvo em storage/app/{$backup}");

        $this->aplicar($mudam);

        Log::info('produtos:aplicar-fotos concluído', [
            'backup'  => $backup,
            'ativos'  => $planos->where('ativo', true)->pluck('slug')->values()->all(),
            'mudaram' => $mudam->pluck('slug')->values()->all(),
        ]);
        $this->info($mudam->count().' produto(s) alterado(s). Para desfazer: php artisan produtos:aplicar-fotos --reverter=ultimo');

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------ //
    // Casamento manifesto → produto + volume

    /** @return array<int, array> product_id => [volume => [sku, frente, verso]] */
    private function casarManifesto(array $manifesto, $produtos): array
    {
        $porSkuProduto = [];
        foreach ($produtos as $p) {
            if ($p->sku) {
                $porSkuProduto[$this->skuCanonico($p->sku)] = $p;
            }
        }
        $porSkuPlanilha = $this->skusDaPlanilha($produtos);

        $resultado = [];
        $vistos = [];

        foreach ($manifesto as $e) {
            $volume = $this->normalizarVolume((string) ($e['tamanho'] ?? ''));
            $rotulo = ($e['produto'] ?? '?')." {$volume}";

            if (empty($e['tem_sku']) || empty($e['sku'])) {
                $this->relatorio['sem_sku'][] = $rotulo.($e['frente'] ? " · frente: {$e['frente']}" : ' · sem foto');

                continue;
            }

            $sku = $this->skuCanonico($e['sku']);

            // Alias (G750. → G750): mesma foto, conta uma vez só.
            if (isset($vistos[$sku])) {
                continue;
            }
            $vistos[$sku] = true;

            if (empty($e['frente'])) {
                $this->relatorio['sem_frente'][] = "{$e['sku']} ({$rotulo})";

                continue;
            }

            $doProduto = $porSkuProduto[$sku] ?? null;
            $daPlanilha = $porSkuPlanilha[$sku] ?? null;
            if ($doProduto && $daPlanilha && $doProduto->id !== $daPlanilha->id) {
                $this->relatorio['conflito'][] = "{$e['sku']}: products.sku é de {$doProduto->slug}, a planilha diz {$daPlanilha->slug} (usado {$doProduto->slug})";
            }
            $produto = $doProduto ?? $daPlanilha;

            if (! $produto) {
                $this->relatorio['sem_produto'][] = "{$e['sku']} ({$rotulo})";

                continue;
            }

            if (isset($resultado[$produto->id][$volume])) {
                $this->relatorio['aviso'][] = "{$produto->slug} {$volume}: mais de um SKU com foto ({$resultado[$produto->id][$volume]['sku']} e {$e['sku']}), ficou o primeiro";

                continue;
            }

            $resultado[$produto->id][$volume] = [
                'sku'    => $sku,
                'frente' => $e['frente'],
                'verso'  => $e['verso'] ?: null,
            ];
        }

        return $resultado;
    }

    /** SKU de volume da planilha do catálogo → produto (pelo grupo/slug). */
    private function skusDaPlanilha($produtos): array
    {
        $arquivo = base_path(self::PLANILHA);
        if (! is_file($arquivo)) {
            $this->relatorio['aviso'][] = 'planilha do catálogo não encontrada: casando só por products.sku';

            return [];
        }

        $porSlug = $produtos->keyBy('slug');
        $mapa = [];

        $f = fopen($arquivo, 'r');
        $cabecalho = array_map('trim', fgetcsv($f));
        $cabecalho[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cabecalho[0]);
        while (($valores = fgetcsv($f)) !== false) {
            if (count($valores) !== count($cabecalho)) {
                continue;
            }
            $l = array_map('trim', array_combine($cabecalho, $valores));
            if (($l['sku'] ?? '') === '') {
                continue;
            }

            $slugAntigo = array_search($l['grupo'], CatalogoImportarPlanilha::SLUG_ANTIGO, true);
            $produto = ($slugAntigo !== false ? $porSlug->get($slugAntigo) : null) ?? $porSlug->get($l['grupo']);
            if ($produto) {
                $mapa[$this->skuCanonico($l['sku'])] = $produto;
            }
        }
        fclose($f);

        return $mapa;
    }

    /** Caixa alta, sem espaço nem ponto final ("G750." é alias de "G750"). */
    private function skuCanonico(string $sku): string
    {
        return rtrim(strtoupper(trim($sku)), '.');
    }

    /** "375ml" → "375 ml" (formato do site). */
    private function normalizarVolume(string $volume): string
    {
        return preg_replace('/^(\d+)\s*ml$/i', '$1 ml', trim($volume));
    }

    // ------------------------------------------------------------------ //
    // Plano

    private function planejar(Product $p, array $fotos): array
    {
        $atual = $p->images->pluck('path')->all();
        $novas = $this->semGaleria ? [] : $this->fotosNovas($p, $fotos);

        // Sem foto no manifesto (ou --sem-galeria), a galeria fica como está.
        if ($novas !== []) {
            $doAdmin = $p->images->filter(fn ($i) => $i->isUploadDoAdmin())->pluck('path')->all();
            $desejada = array_values(array_unique(array_merge(array_column($novas, 'destino'), $doAdmin)));
        } else {
            $desejada = $atual;
        }

        $temFoto = $p->temFotoNoPadrao($desejada);
        $ativo = $p->podeAtivar($desejada);
        if ($temFoto && ! $ativo) {
            $this->relatorio['sem_preco'][] = "{$p->slug} (#{$p->id})";
        }

        return [
            'product'      => $p,
            'slug'         => $p->slug,
            'ativo'        => $ativo,
            'motivo'       => $ativo ? null : ($temFoto ? 'tem foto, sem preço' : 'sem foto no padrão'),
            'muda_ativo'   => $p->active !== $ativo,
            'novas'        => $novas,
            'desejada'     => $desejada,
            'saem'         => array_values(array_diff($atual, $desejada)),
            'muda_galeria' => $atual !== $desejada || $this->ordemMudou($p, $desejada),
            'principal'    => [$p->primaryImage?->path ?? $p->images->first()?->path, $desejada[0] ?? null],
        ];
    }

    /** Arquivos do manifesto que entram na galeria, volume padrão primeiro. */
    private function fotosNovas(Product $p, array $fotos): array
    {
        // Só volume à venda; produto sem volumes cadastrados leva todas.
        if ($p->hasSizes()) {
            foreach (array_keys($fotos) as $volume) {
                if (! in_array($volume, $p->sizes, true)) {
                    $this->relatorio['fora_de_venda'][] = "{$p->slug} {$volume} ({$fotos[$volume]['sku']}): volume não está à venda, foto não entra";
                    unset($fotos[$volume]);
                }
            }
        }

        // Volume padrão primeiro (o maior à venda), depois do maior pro menor.
        uksort($fotos, fn ($a, $b) => (int) $b <=> (int) $a);

        $novas = [];
        foreach ($fotos as $f) {
            foreach (['frente', 'verso'] as $lado) {
                if (! $f[$lado]) {
                    continue;
                }
                $destino = 'products/foto-preto-'.strtolower($f['sku'])."-{$lado}.jpg";
                $novas[] = [
                    'destino' => $destino,
                    'origem'  => $this->dirFotos.DIRECTORY_SEPARATOR.$f[$lado],
                    'status'  => Storage::disk('public')->exists($destino) ? 'reaproveita' : 'gera',
                ];
            }
        }

        return $novas;
    }

    private function ordemMudou(Product $p, array $desejada): bool
    {
        foreach ($desejada as $ordem => $path) {
            $img = $p->images->firstWhere('path', $path);
            if (! $img || $img->sort_order !== $ordem || $img->is_primary !== ($ordem === 0)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------ //
    // Execução

    private function aplicar($planos): void
    {
        $disco = Storage::disk('public');
        $temGd = function_exists('imagecreatefromjpeg');

        // Arquivos primeiro (fora da transação): gerar de novo é inofensivo.
        foreach ($planos as $plano) {
            foreach ($plano['novas'] as $nova) {
                if ($nova['status'] !== 'gera') {
                    continue;
                }
                if (! $temGd || ! is_file($nova['origem'])) {
                    throw new \RuntimeException("Não dá pra gerar {$nova['destino']}: ".($temGd ? "origem {$nova['origem']} não existe" : 'GD indisponível'));
                }
                $disco->put($nova['destino'], $this->versaoWeb($nova['origem']));
            }
        }

        DB::transaction(function () use ($planos) {
            foreach ($planos as $plano) {
                $p = $plano['product'];

                if ($plano['muda_ativo']) {
                    $p->update(['active' => $plano['ativo']]);
                }

                if (! $plano['muda_galeria']) {
                    continue;
                }

                // Só a linha: o arquivo fica no disco.
                ProductImage::where('product_id', $p->id)->whereIn('path', $plano['saem'])->delete();

                foreach ($plano['desejada'] as $ordem => $path) {
                    ProductImage::updateOrCreate(
                        ['product_id' => $p->id, 'path' => $path],
                        ['alt' => $p->name, 'sort_order' => $ordem, 'is_primary' => $ordem === 0],
                    );
                }
            }
        });
    }

    /** Recorte central 2:3 redimensionado para LARGURA x ALTURA. */
    private function versaoWeb(string $origem): string
    {
        $src = imagecreatefromjpeg($origem);
        $w = imagesx($src);
        $h = imagesy($src);

        // Maior retângulo 2:3 que cabe na foto, centralizado.
        $cw = min($w, (int) round($h * self::LARGURA / self::ALTURA));
        $ch = (int) round($cw * self::ALTURA / self::LARGURA);
        $x = (int) (($w - $cw) / 2);
        $y = (int) (($h - $ch) / 2);

        $dst = imagecreatetruecolor(self::LARGURA, self::ALTURA);
        imagecopyresampled($dst, $src, 0, 0, $x, $y, self::LARGURA, self::ALTURA, $cw, $ch);
        imagedestroy($src);

        ob_start();
        imagejpeg($dst, null, self::QUALIDADE);
        $binario = ob_get_clean();
        imagedestroy($dst);

        return $binario;
    }

    private function salvarBackup($produtos): string
    {
        $estado = $produtos->map(fn (Product $p) => [
            'id'     => $p->id,
            'slug'   => $p->slug,
            'active' => $p->active,
            'images' => $p->images->map(fn ($i) => $i->only(['path', 'alt', 'sort_order', 'is_primary']))->values()->all(),
        ])->values()->all();

        $arquivo = self::DIR_BACKUP.'/backup-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($arquivo, json_encode([
            'gerado_em' => now()->toIso8601String(),
            'produtos'  => $estado,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $arquivo;
    }

    // ------------------------------------------------------------------ //
    // Reverter

    private function reverter(string $qual): int
    {
        $disco = Storage::disk('local');

        if ($qual === 'ultimo' || $qual === '') {
            $backups = collect($disco->files(self::DIR_BACKUP))->filter(fn ($f) => str_ends_with($f, '.json'))->sort()->values();
            if ($backups->isEmpty()) {
                $this->error('Nenhum backup em storage/app/'.self::DIR_BACKUP);

                return self::FAILURE;
            }
            $arquivo = $backups->last();
        } else {
            $arquivo = str_starts_with($qual, self::DIR_BACKUP.'/') ? $qual : self::DIR_BACKUP.'/'.basename($qual);
        }

        if (! $disco->exists($arquivo)) {
            $this->error("Backup não encontrado: storage/app/{$arquivo}");

            return self::FAILURE;
        }

        $estado = json_decode($disco->get($arquivo), true)['produtos'] ?? null;
        if (! is_array($estado)) {
            $this->error('Backup ilegível.');

            return self::FAILURE;
        }

        $this->info("Revertendo para storage/app/{$arquivo}");
        $produtos = Product::with('images')->whereIn('id', array_column($estado, 'id'))->get()->keyBy('id');
        $mudam = [];

        foreach ($estado as $e) {
            $p = $produtos->get($e['id']);
            if (! $p) {
                $this->warn("  #{$e['id']} {$e['slug']}: não existe mais, pulado");

                continue;
            }

            $paths = array_column($e['images'], 'path');
            $mudaAtivo = $p->active !== (bool) $e['active'];
            $mudaGaleria = $p->images->pluck('path')->all() !== $paths || $this->ordemMudou($p, $paths);

            if ($mudaAtivo || $mudaGaleria) {
                $mudam[] = [$p, $e];
                $this->line("  {$p->slug}: ".implode(', ', array_filter([
                    $mudaAtivo ? 'ativo '.($p->active ? 'sim' : 'não').' → '.($e['active'] ? 'sim' : 'não') : null,
                    $mudaGaleria ? 'galeria volta a '.($paths ? implode(', ', array_map('basename', $paths)) : 'vazia (placeholder)') : null,
                ])));
            }
        }

        if ($mudam === []) {
            $this->info('Já está no estado do backup.');

            return self::SUCCESS;
        }

        if ($this->dryRun) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($mudam) {
            foreach ($mudam as [$p, $e]) {
                $p->update(['active' => (bool) $e['active']]);

                $paths = array_column($e['images'], 'path');
                ProductImage::where('product_id', $p->id)->whereNotIn('path', $paths)->delete();
                foreach ($e['images'] as $img) {
                    ProductImage::updateOrCreate(
                        ['product_id' => $p->id, 'path' => $img['path']],
                        ['alt' => $img['alt'], 'sort_order' => $img['sort_order'], 'is_primary' => $img['is_primary']],
                    );
                }
            }
        });

        Log::info('produtos:aplicar-fotos revertido', ['backup' => $arquivo, 'produtos' => count($mudam)]);
        $this->info(count($mudam).' produto(s) revertido(s).');

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------ //
    // Saída

    private function imprimir($planos): void
    {
        $grupos = [
            'ATIVA'      => $planos->filter(fn ($pl) => $pl['muda_ativo'] && $pl['ativo']),
            'DESATIVA'   => $planos->filter(fn ($pl) => $pl['muda_ativo'] && ! $pl['ativo']),
            'TROCA FOTO' => $planos->filter(fn ($pl) => $pl['muda_galeria']),
        ];

        foreach ($grupos as $titulo => $lista) {
            $this->line("<options=bold>{$titulo}</> (".$lista->count().')');
            foreach ($lista as $pl) {
                $p = $pl['product'];
                $linha = "  #{$p->id} {$p->slug}";

                if ($titulo === 'TROCA FOTO') {
                    [$antes, $depois] = $pl['principal'];
                    $gera = count(array_filter($pl['novas'], fn ($n) => $n['status'] === 'gera'));
                    $linha .= ': principal '.($antes ? basename($antes) : '—').' → '.($depois ? basename($depois) : '—')
                        .' · galeria '.count($pl['desejada'])." (gera {$gera})"
                        .($pl['saem'] ? ' · saem: '.implode(', ', array_map('basename', $pl['saem'])) : '');
                } elseif ($titulo === 'DESATIVA') {
                    $linha .= " ({$pl['motivo']})";
                }

                $this->line($linha);
            }
            $this->newLine();
        }

        $ficam = $planos->where('ativo', true)->pluck('slug');
        $this->line('<options=bold>Ativos depois</> ('.$ficam->count().'): '.$ficam->implode(', '));
        $this->newLine();
    }

    private function imprimirRelatorio(): void
    {
        $secoes = [
            'sem_produto'   => 'Manifesto com SKU que não existe no banco',
            'sem_sku'       => 'Manifesto sem SKU (tem_sku=false) — decidir à mão, nada foi criado',
            'sem_frente'    => 'Manifesto com SKU mas sem foto de frente',
            'sem_preco'     => 'Com foto no padrão mas sem preço — ficam inativos (admin não ativa sem preço)',
            'fora_de_venda' => 'Foto de volume que não está à venda',
            'conflito'      => 'SKU com dono diferente no produto e na planilha',
            'aviso'         => 'Avisos',
        ];

        foreach ($secoes as $chave => $titulo) {
            $itens = $this->relatorio[$chave];
            if ($itens === [] && in_array($chave, ['conflito', 'aviso', 'sem_frente', 'fora_de_venda'], true)) {
                continue;
            }
            $this->line("<options=bold>{$titulo}</> (".count($itens).')');
            foreach ($itens ?: ['—'] as $item) {
                $this->line("  {$item}");
            }
            $this->newLine();
        }
    }
}
