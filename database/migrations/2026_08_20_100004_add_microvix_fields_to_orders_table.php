<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('microvix_pedido_id')->nullable()->index()->after('paid_at');
            $table->unsignedBigInteger('microvix_cod_cliente')->nullable()->after('microvix_pedido_id');
            $table->integer('microvix_status')->nullable()->after('microvix_cod_cliente');
            $table->timestamp('microvix_synced_at')->nullable()->after('microvix_status');
            // Fase 2: pedido enviado com somente_reserva=1 (marca d'água no ERP).
            $table->boolean('microvix_reserved')->default(false)->after('microvix_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'microvix_pedido_id',
                'microvix_cod_cliente',
                'microvix_status',
                'microvix_synced_at',
                'microvix_reserved',
            ]);
        });
    }
};
