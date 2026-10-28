<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Services\FundoFoto;
use Illuminate\Console\Command;

/**
 * Preenche product_images.fundo das fotos que já existem (as novas ganham a
 * cor ao gravar — ProductImage::booted). Recalcula todas e lista
 * produto → imagem → cor; só grava o que mudou.
 */
class ProdutosDetectarFundo extends Command
{
    protected $signature = 'produtos:detectar-fundo {--dry-run : Só mostra, não grava}';

    protected $description = 'Detecta a cor de fundo de cada foto de produto (moldura acompanha a foto)';

    public function handle(): int
    {
        $imagens = ProductImage::with('product:id,name')
            ->orderBy('product_id')->orderBy('sort_order')->orderBy('id')
            ->get();

        if ($imagens->isEmpty()) {
            $this->info('Nenhuma foto de produto cadastrada.');

            return self::SUCCESS;
        }

        $linhas = [];
        $mudam = 0;
        $falham = 0;

        foreach ($imagens as $img) {
            $cor = FundoFoto::doDisco($img->path);

            if ($cor === null) {
                $falham++;
                $situacao = 'arquivo ausente ou ilegível';
            } elseif ($cor === $img->fundo) {
                $situacao = 'igual';
            } else {
                $mudam++;
                $situacao = $img->fundo ? 'era '.$img->fundo : 'novo';
                if (! $this->option('dry-run')) {
                    $img->update(['fundo' => $cor]);
                }
            }

            $linhas[] = [
                $img->product?->name ?? '(produto #'.$img->product_id.')',
                basename($img->path).($img->is_primary ? ' *' : ''),
                $cor ?? '-',
                self::tom($cor),
                $situacao,
            ];
        }

        $this->table(['Produto', 'Imagem (* = principal)', 'Cor', 'Tom', 'Situação'], $linhas);

        $resumo = $imagens->count().' foto(s); '.$mudam.' '.($this->option('dry-run') ? 'mudariam' : 'gravada(s)');
        if ($falham) {
            $resumo .= '; '.$falham.' sem arquivo (ficam com o fundo padrão)';
        }
        $this->info($resumo.'.');
        if ($this->option('dry-run') && $mudam) {
            $this->line('Rode sem --dry-run para gravar.');
        }

        return self::SUCCESS;
    }

    /** Leitura rápida da lista: claro, escuro ou outro. */
    private static function tom(?string $cor): string
    {
        if ($cor === null) {
            return '-';
        }
        [$r, $g, $b] = sscanf($cor, '#%02x%02x%02x');
        $luz = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;

        return $luz >= 0.85 ? 'claro' : ($luz <= 0.15 ? 'escuro' : 'médio');
    }
}
