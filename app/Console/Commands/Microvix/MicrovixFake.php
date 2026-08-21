<?php

namespace App\Console\Commands\Microvix;

use App\Models\MicrovixProduct;
use Database\Seeders\MicrovixFakeSeeder;
use Illuminate\Console\Command;

/**
 * FERRAMENTA DE DESENVOLVIMENTO — não é parte do fluxo de produção.
 * Existe porque o ERP ainda não expõe produto nenhum (b2c=0 em todos)
 * e sem dados no espelho nada da fase 1.5 é testável.
 */
class MicrovixFake extends Command
{
    protected $signature = 'microvix:fake
        {--limpar : Remove os registros fictícios (faixa codigoproduto >= 900000), sem tocar em dados do ERP real}';

    protected $description = '[DEV] Popula microvix_products com dados fictícios no formato do ERP, enquanto o B2C não é liberado na Linx';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('microvix:fake é ferramenta de desenvolvimento — bloqueado em produção.');

            return self::FAILURE;
        }

        $seeder = new MicrovixFakeSeeder();

        if ($this->option('limpar')) {
            $removidos = $seeder->limpar();
            $this->info("{$removidos} registro(s) fictício(s) removido(s).");

            return self::SUCCESS;
        }

        $seeder->run();

        $fakes = MicrovixProduct::where('codigoproduto', '>=', MicrovixFakeSeeder::CODIGO_BASE);
        $this->info(sprintf(
            '%d registro(s) fictício(s) em microvix_products: %d sem correspondência na loja, %d inativo(s), %d com saldo zero.',
            (clone $fakes)->count(),
            (clone $fakes)->where('codigoproduto', '>=', MicrovixFakeSeeder::CODIGO_SO_NO_ERP)->count(),
            (clone $fakes)->where('ativo', false)->count(),
            (clone $fakes)->where('saldo', 0)->count(),
        ));
        $this->line('Para remover: php artisan microvix:fake --limpar');

        return self::SUCCESS;
    }
}
