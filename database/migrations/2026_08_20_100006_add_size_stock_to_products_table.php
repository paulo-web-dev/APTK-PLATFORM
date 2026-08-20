<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Estoque por volume (mesmo formato de size_prices: {"375ml": 3}).
            // No Microvix cada volume é um codigoproduto com saldo próprio;
            // o stock_qty agregado não sabe disso. A lógica de leitura de
            // estoque NÃO muda nesta fase — só a coluna existe.
            $table->json('size_stock')->nullable()->after('size_prices');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('size_stock');
        });
    }
};
