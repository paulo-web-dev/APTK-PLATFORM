<?php

namespace App\Console\Commands\Microvix;

use App\Services\Microvix\MicrovixVinculador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Vínculo assistido codigoproduto → produto + tamanho do site. Prévia por
 * padrão (tabela + CSV para revisão); --aplicar grava exato/alto, e médio
 * só com --minimo=medio. Nunca chama o webservice: lê só o espelho local.
 */
class MicrovixVincular extends Command
{
    protected $signature = 'microvix:vincular
        {--aplicar : Grava os vínculos de nível exato e alto}
        {--minimo=alto : Nível mínimo gravado com --aplicar (alto|medio)}
        {--forcar : Reavalia e sobrescreve linhas que já têm product_id}
        {--planilha= : CSV de SKUs por volume (padrão: planilha do catálogo)}';

    protected $description = 'Sugere (e com --aplicar grava) o vínculo dos códigos da Linx com produto + tamanho do site';

    public function handle(MicrovixVinculador $vinculador): int
    {
        $minimo = (string) $this->option('minimo');
        if (! in_array($minimo, ['alto', 'medio'], true)) {
            $this->error('--minimo aceita "alto" ou "medio".');

            return self::INVALID;
        }

        $forcar = (bool) $this->option('forcar');
        $plano = $vinculador->plano($forcar, $this->option('planilha') ?: null);

        $linhas = array_map(fn ($l) => [
            $l['mp']->codigoproduto,
            $l['mp']->nome_produto,
            $l['mp']->codauxiliar1,
            $l['product'] ? "#{$l['product']->id} {$l['product']->name}" : '—',
            $l['size'] ?? '—',
            $l['criterio'] ?? '—',
            $l['score'] === null ? '—' : number_format($l['score'], 1, ',', ''),
            $this->status($l),
        ], $plano);

        $this->table(['Código', 'Nome na Linx', 'codauxiliar1', 'Produto do site', 'Tamanho', 'Critério', 'Score', 'Status'], $linhas);

        $csv = $this->gravarCsv($plano);
        $this->line("CSV para revisão: {$csv}");

        $this->newLine();
        $this->info('== Resumo');
        $contagem = array_count_values(array_column($plano, 'status'));
        foreach (MicrovixVinculador::ROTULOS as $chave => $rotulo) {
            if (isset($contagem[$chave])) {
                $this->line(sprintf('  %-22s %d', $rotulo, $contagem[$chave]));
            }
        }
        $this->newLine();

        if (! $this->option('aplicar')) {
            $this->info('Prévia: nada foi gravado. Use --aplicar para gravar exato/alto'.($minimo === 'medio' ? '/médio' : '').'.');

            return self::SUCCESS;
        }

        $n = $vinculador->aplicar($plano, $minimo, $forcar);
        $this->info("{$n} vínculo(s) gravado(s) (nível mínimo: ".MicrovixVinculador::ROTULOS[$minimo].').');

        return self::SUCCESS;
    }

    protected function status(array $l): string
    {
        $s = MicrovixVinculador::ROTULOS[$l['status']];
        if ($l['status'] === 'ignorado' && $l['volume']) {
            return "ignorado ({$l['volume']})";
        }

        return $l['obs'] !== '' ? "{$s} — {$l['obs']}" : $s;
    }

    /** CSV com ";" e BOM: abre direto no Excel em PT-BR com acento certo. */
    protected function gravarCsv(array $plano): string
    {
        $arquivo = 'microvix/vinculos-'.now()->format('d-m-Y').'.csv';

        $f = fopen('php://temp', 'r+');
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, ['codigoproduto', 'nome_linx', 'codauxiliar1', 'product_id', 'produto_site', 'tamanho', 'criterio', 'score', 'status', 'obs'], ';');
        foreach ($plano as $l) {
            fputcsv($f, [
                $l['mp']->codigoproduto,
                $l['mp']->nome_produto,
                $l['mp']->codauxiliar1,
                $l['product']?->id,
                $l['product']?->name,
                $l['size'],
                $l['criterio'],
                $l['score'] === null ? '' : number_format($l['score'], 1, ',', ''),
                $l['status'] === 'ignorado' && $l['volume'] ? "ignorado ({$l['volume']})" : MicrovixVinculador::ROTULOS[$l['status']],
                $l['obs'],
            ], ';');
        }
        rewind($f);
        Storage::disk('local')->put($arquivo, stream_get_contents($f));
        fclose($f);

        return Storage::disk('local')->path($arquivo);
    }
}
