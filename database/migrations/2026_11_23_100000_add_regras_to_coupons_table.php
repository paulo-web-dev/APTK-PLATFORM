<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cupons de ponta a ponta: descrição interna, início da validade, só
 * primeira compra e limite por cliente. Datas em UTC (o admin lê e mostra
 * em America/Sao_Paulo). used_count fica, mas sai de uso: os usos são
 * contados nos pedidos (CouponService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->string('name', 120)->nullable()->after('code');
            $table->timestamp('starts_at')->nullable()->after('used_count');
            $table->boolean('first_purchase_only')->default(false)->after('expires_at');
            $table->unsignedInteger('max_per_customer')->nullable()->default(1)->after('max_uses');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['name', 'starts_at', 'first_purchase_only', 'max_per_customer']);
        });
    }
};
