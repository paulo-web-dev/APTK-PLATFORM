<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Preço por volume: {"375 ml": 109.90, "750 ml": 189.90}.
            // Volumes sem preço aqui usam o `price` base do produto.
            $table->json('size_prices')->nullable()->after('sizes');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('size_prices');
        });
    }
};
