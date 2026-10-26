<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Leva 14: "Caipirinha Arazul" passa a ser "Caipirinha" no site todo. Só
 * `name`; o slug caipirinha-arazul não muda. Pedidos antigos
 * (order_items.product_name) ficam como foram vendidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->where('slug', 'caipirinha-arazul')->where('name', 'Caipirinha Arazul')
            ->update(['name' => 'Caipirinha']);
    }

    public function down(): void
    {
        DB::table('products')->where('slug', 'caipirinha-arazul')->where('name', 'Caipirinha')
            ->update(['name' => 'Caipirinha Arazul']);
    }
};
