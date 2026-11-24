<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido com cupom: referência (coupon_id, fraca: some se o cupom for
 * apagado) + snapshot do código. O valor já mora em orders.discount.
 * customer_email: e-mail do cliente congelado na compra (minúsculo), para
 * as regras de primeira compra / limite por cliente não dependerem do
 * e-mail atual da conta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('discount')->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 50)->nullable()->after('coupon_id')->index();
            $table->string('customer_email', 191)->nullable()->after('ship_cpf')->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropIndex(['coupon_code']);
            $table->dropIndex(['customer_email']);
            $table->dropColumn(['coupon_code', 'customer_email']);
        });
    }
};
