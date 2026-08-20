<?php

namespace App\Console\Commands\Microvix;

use Illuminate\Console\Command;

class MicrovixSyncProdutos extends Command
{
    protected $signature = 'microvix:sync-produtos';

    protected $description = 'Espelha o catálogo do ERP em microvix_products (cadastro + saldo + preço)';

    public function handle(): int
    {
        // Cadastro → saldo → preço, na ordem; tudo em microvix_products.
        // A tabela products NÃO é tocada (fase 1 só espelha o ERP).
        return $this->call('microvix:sync');
    }
}
