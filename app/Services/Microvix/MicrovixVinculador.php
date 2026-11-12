<?php

namespace App\Services\Microvix;

use App\Console\Commands\CatalogoImportarPlanilha;
use App\Console\Commands\CatalogoProdutoTeste;
use App\Models\MicrovixProduct;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Vínculo assistido em massa: sugestão automática, gravação só com revisão
 * (prévia + --aplicar). Calcula o plano de TODAS as linhas de uma vez,
 * porque ambiguidade de SKU e disputa por produto + tamanho só aparecem
 * olhando o conjunto. Usado pelo microvix:vincular; a sugestão unitária da
 * tela do admin continua no MicrovixMatcher.
 *
 * Ordem: volume desativado → SKU (exato) → nome (alto/médio). O SKU da
 * planilha é só leitura, para casar — nunca é gravado.
 */
class MicrovixVinculador
{
    public const NIVEIS = ['exato' => 3, 'alto' => 2, 'medio' => 1];

    public const ROTULOS = [
        'exato'        => 'exato',
        'alto'         => 'alto',
        'medio'        => 'médio',
        'sem_par'      => 'sem par',
        'ambiguo'      => 'ambíguo',
        'conflito'     => 'conflito',
        'ignorado'     => 'ignorado',
        'sem_volume'   => 'sem volume',
        'sem_tamanho'  => 'sem tamanho no site',
        'ja_vinculado' => 'já vinculado',
    ];

    /** Palavras de marca: saem do nome para o score, mas filtram candidatos. */
    protected const MARCAS = ['aptk', 'barin', 'ice4pros'];

    protected const LIMIAR_ALTO = 90.0;
    protected const LIMIAR_MEDIO = 75.0;

    /**
     * @return array<int, array{mp: MicrovixProduct, volume: ?string, product: ?Product, size: ?string,
     *                          criterio: ?string, score: ?float, status: string, obs: string}>
     */
    public function plano(bool $forcar = false, ?string $planilha = null): array
    {
        $products = Product::with('category')
            ->where('slug', '!=', CatalogoProdutoTeste::SLUG)
            ->get();

        $indiceSku = $this->indiceSku($products, $planilha ?? base_path(CatalogoImportarPlanilha::CSV_PADRAO));
        $linhas = MicrovixProduct::orderBy('codigoproduto')->get();

        // SKU normalizado repetido entre codigoproduto diferentes (G2750 × G2750.)
        $skuRepetido = $linhas
            ->filter(fn ($mp) => $this->normalizarSku($mp->codauxiliar1) !== '')
            ->groupBy(fn ($mp) => $this->normalizarSku($mp->codauxiliar1))
            ->filter(fn ($g) => $g->count() > 1)
            ->keys()
            ->flip();

        $plano = [];
        foreach ($linhas as $mp) {
            $plano[] = $this->avaliar($mp, $products, $indiceSku, $skuRepetido, $forcar);
        }

        return $this->resolverConflitos($plano);
    }

    /** Grava os itens com nível ≥ mínimo. Devolve quantos foram gravados. */
    public function aplicar(array $plano, string $minimo = 'alto', bool $forcar = false): int
    {
        $corte = self::NIVEIS[$minimo] ?? self::NIVEIS['alto'];
        $gravar = array_filter($plano, fn ($l) => (self::NIVEIS[$l['status']] ?? 0) >= $corte);

        return DB::transaction(function () use ($gravar, $forcar) {
            $n = 0;
            foreach ($gravar as $l) {
                $q = MicrovixProduct::whereKey($l['mp']->id);
                if (! $forcar) {
                    $q->whereNull('product_id');
                }
                $n += $q->update(['product_id' => $l['product']->id, 'size' => $l['size']]);
            }

            return $n;
        });
    }

    // ------------------------------------------------------------------ //

    protected function avaliar(MicrovixProduct $mp, Collection $products, array $indiceSku, Collection $skuRepetido, bool $forcar): array
    {
        $volume = $this->extrairVolume($mp->nome_produto);
        $linha = [
            'mp' => $mp, 'volume' => $volume, 'product' => null, 'size' => null,
            'criterio' => null, 'score' => null, 'status' => 'sem_par', 'obs' => '',
        ];

        if ($mp->product_id && ! $forcar) {
            return ['product' => $products->firstWhere('id', $mp->product_id), 'size' => $mp->size, 'status' => 'ja_vinculado'] + $linha;
        }

        if ($volume !== null && in_array($volume, config('catalogo.volumes_desativados', []), true)) {
            return ['status' => 'ignorado', 'obs' => "volume fora da venda ({$volume})"] + $linha;
        }

        $sku = $this->normalizarSku($mp->codauxiliar1);
        $porSku = $sku !== '' ? $this->porSku($sku, $volume, $indiceSku) : null;

        if ($sku !== '' && $skuRepetido->has($sku)) {
            return [
                'product' => $porSku['product'] ?? null, 'size' => $porSku['size'] ?? null,
                'criterio' => 'sku', 'status' => 'ambiguo',
                'obs' => "SKU {$sku} repetido em mais de um código da Linx",
            ] + $linha;
        }

        if ($porSku) {
            return ['product' => $porSku['product'], 'size' => $porSku['size'], 'criterio' => 'sku', 'score' => 100.0, 'status' => 'exato'] + $linha;
        }

        if ($volume === null) {
            return ['status' => 'sem_volume', 'obs' => 'nome sem volume em ml'] + $linha;
        }

        return $this->porNome($mp, $volume, $products) + $linha;
    }

    /** @return array{product: Product, size: string}|null */
    protected function porSku(string $sku, ?string $volume, array $indiceSku): ?array
    {
        $alvos = [];
        foreach ($indiceSku[$sku] ?? [] as [$product, $sizeDaPlanilha]) {
            $size = $sizeDaPlanilha ?? $volume;
            // planilha e nome da Linx discordando do volume: SKU não decide
            if ($size === null || ($volume !== null && $size !== $volume)) {
                continue;
            }
            if (in_array($size, $product->sizes ?? [], true)) {
                $alvos[$product->id.'|'.$size] = ['product' => $product, 'size' => $size];
            }
        }

        return count($alvos) === 1 ? reset($alvos) : null;
    }

    protected function porNome(MicrovixProduct $mp, string $volume, Collection $products): array
    {
        $nomeLinx = $this->normalizarNome($mp->nome_produto);
        $ehBarin = $this->temBarin($mp->nome_produto);

        if ($nomeLinx === '') {
            return ['status' => 'sem_par', 'obs' => 'nome vazio depois de normalizar'];
        }

        $melhorComTamanho = null;
        $melhorGeral = null;

        foreach ($products as $p) {
            // marca é filtro: Barín só casa com Barín, e vice-versa
            if ($this->produtoBarin($p) !== $ehBarin) {
                continue;
            }
            $nomeSite = $this->normalizarNome($p->name);
            if ($nomeSite === '') {
                continue;
            }
            similar_text($nomeLinx, $nomeSite, $pct);
            $c = ['product' => $p, 'score' => round($pct, 1), 'curto' => $this->umaPalavra($nomeLinx) || $this->umaPalavra($nomeSite)];

            if (! $melhorGeral || $c['score'] > $melhorGeral['score']) {
                $melhorGeral = $c;
            }
            if (in_array($volume, $p->sizes ?? [], true) && (! $melhorComTamanho || $c['score'] > $melhorComTamanho['score'])) {
                $melhorComTamanho = $c;
            }
        }

        if ($melhorComTamanho && $melhorComTamanho['score'] >= self::LIMIAR_MEDIO) {
            $nivel = $melhorComTamanho['score'] >= self::LIMIAR_ALTO ? 'alto' : 'medio';
            $obs = '';
            if ($nivel === 'alto' && $melhorComTamanho['curto']) {
                $nivel = 'medio';
                $obs = 'nome de uma palavra: teto médio';
            }

            return [
                'product' => $melhorComTamanho['product'], 'size' => $volume, 'criterio' => 'nome',
                'score' => $melhorComTamanho['score'], 'status' => $nivel, 'obs' => $obs,
            ];
        }

        if ($melhorGeral && $melhorGeral['score'] >= self::LIMIAR_MEDIO) {
            return [
                'product' => $melhorGeral['product'], 'criterio' => 'nome', 'score' => $melhorGeral['score'],
                'status' => 'sem_tamanho', 'obs' => "produto do site não tem {$volume}",
            ];
        }

        return [
            'product' => $melhorComTamanho['product'] ?? null, 'criterio' => 'nome',
            'score' => $melhorComTamanho['score'] ?? null, 'status' => 'sem_par',
        ];
    }

    /**
     * Um produto + tamanho do site só fica com um codigoproduto: maior nível,
     * depois maior score, depois menor código (determinístico). Par já gravado
     * no banco (linha não reavaliada) está ocupado.
     */
    protected function resolverConflitos(array $plano): array
    {
        $ocupado = [];
        foreach ($plano as $l) {
            if ($l['status'] === 'ja_vinculado' && $l['mp']->product_id) {
                $ocupado[$l['mp']->product_id.'|'.$l['mp']->size] = $l['mp']->codigoproduto;
            }
        }

        $disputa = [];
        foreach ($plano as $i => $l) {
            if (isset(self::NIVEIS[$l['status']])) {
                $disputa[$l['product']->id.'|'.$l['size']][] = $i;
            }
        }

        foreach ($disputa as $par => $indices) {
            usort($indices, fn ($a, $b) => [self::NIVEIS[$plano[$b]['status']], $plano[$b]['score'], $plano[$a]['mp']->codigoproduto]
                <=> [self::NIVEIS[$plano[$a]['status']], $plano[$a]['score'], $plano[$b]['mp']->codigoproduto]);

            foreach ($indices as $pos => $i) {
                if (isset($ocupado[$par])) {
                    $plano[$i]['obs'] = "par já vinculado ao código {$ocupado[$par]}";
                } elseif ($pos > 0) {
                    $plano[$i]['obs'] = 'perdeu para o código '.$plano[$indices[0]]['mp']->codigoproduto;
                } else {
                    continue;
                }
                $plano[$i]['status'] = 'conflito';
            }
        }

        return $plano;
    }

    /**
     * SKU normalizado → [[Product, size|null], ...]. products.sku entra sem
     * tamanho (o volume vem do nome da Linx); a planilha entra com o volume
     * da linha. Só leitura: nada daqui é gravado.
     */
    protected function indiceSku(Collection $products, string $planilha): array
    {
        $indice = [];
        foreach ($products as $p) {
            if (($s = $this->normalizarSku($p->sku)) !== '') {
                $indice[$s][] = [$p, null];
            }
        }

        if (! is_file($planilha) || ! ($f = fopen($planilha, 'r'))) {
            return $indice;
        }

        $cab = fgetcsv($f) ?: [];
        $cab = array_map(fn ($c) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $c)), $cab);
        $porSlug = $products->keyBy('slug');

        while (($v = fgetcsv($f)) !== false) {
            if (count($v) !== count($cab)) {
                continue;
            }
            $l = array_map('trim', array_combine($cab, $v));
            $sku = $this->normalizarSku($l['sku'] ?? null);
            if ($sku === '' || ($l['grupo'] ?? '') === '') {
                continue;
            }
            $slugAntigo = array_search($l['grupo'], CatalogoImportarPlanilha::SLUG_ANTIGO, true);
            $p = ($slugAntigo !== false ? $porSlug->get($slugAntigo) : null) ?? $porSlug->get($l['grupo']);
            if ($p) {
                $indice[$sku][] = [$p, $this->formatarVolume($l['volume'] ?? '')];
            }
        }
        fclose($f);

        return $indice;
    }

    // ------------------------------------------------------------------ //
    // Normalização

    public function extrairVolume(?string $nome): ?string
    {
        return preg_match('/(\d{2,4})\s*ML\b/iu', (string) $nome, $m) ? ((int) $m[1]).' ml' : null;
    }

    /** Maiúsculo, sem espaços, sem pontuação no final: "g2750." → "G2750". */
    public function normalizarSku(?string $sku): string
    {
        $s = preg_replace('/\s+/u', '', mb_strtoupper((string) $sku));

        return preg_replace('/[\p{P}\p{S}]+$/u', '', $s);
    }

    /** Sem acento, minúsculo, sem pontuação, sem volume e sem palavra de marca. */
    public function normalizarNome(?string $nome): string
    {
        $s = Str::lower(Str::ascii((string) $nome));
        $s = preg_replace('/\d+\s*ml\b/', ' ', $s);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        $palavras = array_diff(preg_split('/\s+/', trim($s), -1, PREG_SPLIT_NO_EMPTY), self::MARCAS);

        return implode(' ', $palavras);
    }

    protected function temBarin(?string $nome): bool
    {
        return in_array('barin', preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii((string) $nome))), true);
    }

    /** Marca do produto do site = categoria "barin" (é como o importador classifica). */
    protected function produtoBarin(Product $p): bool
    {
        return $p->category?->slug === 'barin' || $this->temBarin($p->name);
    }

    protected function umaPalavra(string $nomeNormalizado): bool
    {
        return ! str_contains($nomeNormalizado, ' ');
    }

    protected function formatarVolume(string $volume): ?string
    {
        return preg_match('/^(\d+)\s*ml$/i', trim($volume), $m) ? ((int) $m[1]).' ml' : null;
    }
}
