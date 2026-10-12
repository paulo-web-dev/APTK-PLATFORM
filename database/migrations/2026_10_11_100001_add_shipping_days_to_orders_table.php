<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prazo em dias úteis da opção de frete escolhida (leva 08), congelado
        // na compra junto com shipping_method/shipping_cost. Null nos pedidos
        // anteriores ao frete calculado.
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('shipping_days')->nullable()->after('shipping_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_days');
        });
    }
};
