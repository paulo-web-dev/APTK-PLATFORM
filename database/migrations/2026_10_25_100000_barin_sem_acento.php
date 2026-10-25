<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Leva 13: a marca passa a ser escrita BARIN (sem acento). Só `name` da
 * categoria `barin` e dos produtos com "Barín" no nome: slugs e rotas
 * (/barin) não mudam. Pedidos antigos (order_items.product_name) ficam como
 * foram vendidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->trocar('Barín', 'BARIN');
    }

    public function down(): void
    {
        $this->trocar('BARIN', 'Barín');
    }

    private function trocar(string $de, string $para): void
    {
        DB::table('product_categories')->where('slug', 'barin')->update(['name' => $para]);

        DB::table('products')->where('name', 'like', '%'.$de.'%')->get(['id', 'name'])
            ->each(function ($p) use ($de, $para) {
                $novo = str_replace($de, $para, $p->name);
                if ($novo !== $p->name) {
                    DB::table('products')->where('id', $p->id)->update(['name' => $novo]);
                }
            });
    }
};
