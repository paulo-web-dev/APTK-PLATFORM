<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Marca o volume das fotos que já existem (leva 18; as novas ganham no
 * produtos:aplicar-fotos e no admin). O volume da garrafa vem do manifesto
 * (foto-branco-{chave}-{lado}.jpg → `tamanho` da entrada), não do número no
 * nome. A regra é a de Product::marcarVolumes: só volume à venda que não é o
 * padrão; sem nenhuma geral sobrando, tudo fica geral. Upload do admin e
 * foto fora do manifesto ficam como estão.
 *
 * Só preenche foto ainda geral: nunca desfaz marcação feita no admin.
 */
class ProdutosMarcarVolume extends Command
{
    protected $signature = 'produtos:marcar-volume
        {--dry-run : Só mostra a tabela, não grava}
        {--manifesto= : Manifesto (padrão: public/img/fotos_produtos/manifesto.json)}';

    protected $description = 'Marca o volume (garrafa pequena) das fotos de produto existentes';

    public function handle(): int
    {
        $caminho = $this->option('manifesto') ?: base_path('public/img/fotos_produtos/manifesto.json');
        $manifesto = is_file($caminho) ? json_decode(file_get_contents($caminho), true) : null;
        if (! is_array($manifesto)) {
            $this->error("Manifesto não encontrado ou inválido: {$caminho}");

            return self::FAILURE;
        }

        $garrafaPorChave = $this->garrafaPorChave($manifesto);

        $produtos = Product::with('images')->has('images')->orderBy('name')->get();
        $linhas = [];
        $mudam = [];

        foreach ($produtos as $p) {
            $garrafa = $p->images->mapWithKeys(function ($img) use ($garrafaPorChave) {
                $chave = preg_match('#^products/foto-branco-(.+)-(frente|verso)\.jpg$#', $img->path, $m) ? $m[1] : null;

                // Fora do manifesto (upload do admin, foto antiga): fica o que tem.
                return [$img->path => $chave !== null && isset($garrafaPorChave[$chave]) ? $garrafaPorChave[$chave] : $img->volume];
            })->all();
            $marca = $p->marcarVolumes($garrafa);

            foreach ($p->images as $img) {
                $nova = $marca[$img->path];
                $muda = $img->volume === null && $nova !== null;
                if ($muda) {
                    $mudam[$p->id][$img->id] = $nova;
                }

                $linhas[] = [
                    $p->slug,
                    implode(' + ', $p->sizes ?: []) ?: '—',
                    basename($img->path).($img->is_primary ? ' *' : ''),
                    $garrafa[$img->path] ?? '?',
                    $img->volume ?? 'geral',
                    $muda ? $nova : ($img->volume ?? 'geral'),
                ];
            }
        }

        $this->table(['Produto', 'Vende', 'Foto', 'Garrafa na foto', 'Hoje', 'Fica'], $linhas);
        $total = array_sum(array_map('count', $mudam));
        $this->line("{$total} foto(s) ganham volume em ".count($mudam).' produto(s).');

        if ($this->option('dry-run') || $total === 0) {
            if ($this->option('dry-run')) {
                $this->warn('DRY-RUN: nada foi gravado.');
            }

            return self::SUCCESS;
        }

        DB::transaction(function () use ($mudam, $produtos) {
            foreach ($mudam as $productId => $imagens) {
                $p = $produtos->firstWhere('id', $productId);
                foreach ($imagens as $imageId => $volume) {
                    $p->images->firstWhere('id', $imageId)->update(['volume' => $volume]);
                }
                $p->renumerarGaleria();
            }
        });

        Log::info('produtos:marcar-volume', ['fotos' => $mudam]);
        $this->info('Gravado. Para desfazer uma foto: admin → editar produto → Garrafa "Geral".');

        return self::SUCCESS;
    }

    /** Chave do arquivo gerado pelo aplicar-fotos (sku, ou {grupo}-{N}ml) → "375 ml". */
    private function garrafaPorChave(array $manifesto): array
    {
        $mapa = [];
        foreach ($manifesto as $e) {
            $volume = preg_replace('/^(\d+)\s*ml$/i', '$1 ml', trim((string) ($e['tamanho'] ?? '')));
            $chave = ! empty($e['tem_sku']) && ! empty($e['sku'])
                ? strtolower(rtrim(trim($e['sku']), '.'))
                : strtolower(($e['grupo'] ?? '').'-'.str_replace(' ', '', $volume));
            $mapa[$chave] = $volume;
        }

        return $mapa;
    }
}
