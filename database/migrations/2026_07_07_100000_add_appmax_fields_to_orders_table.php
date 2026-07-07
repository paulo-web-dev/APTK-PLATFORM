<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Vínculo com a Appmax (id do pedido/cliente lá) + dados do pagamento.
            $table->unsignedBigInteger('appmax_order_id')->nullable()->index()->after('payment_status');
            $table->unsignedBigInteger('appmax_customer_id')->nullable()->after('appmax_order_id');
            $table->string('appmax_pay_reference')->nullable()->after('appmax_customer_id');
            // Pix: emv/qrcode/expiração · Boleto: linha/pdf/vencimento · Cartão: parcelas.
            $table->json('payment_details')->nullable()->after('appmax_pay_reference');
            $table->timestamp('paid_at')->nullable()->after('payment_details');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['appmax_order_id', 'appmax_customer_id', 'appmax_pay_reference', 'payment_details', 'paid_at']);
        });
    }
};
