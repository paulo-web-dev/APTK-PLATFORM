<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fase 2: vincula o log ao pedido (trilha por pedido no painel) e
        // marca envio simulado — com MICROVIX_ENVIA_PEDIDO desligado o
        // sender grava o envelope que TERIA sido enviado, sem chamar o WS.
        Schema::table('microvix_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->index()->after('id'); // sem FK, como o resto do schema
            $table->boolean('simulated')->default(false)->after('success');
        });
    }

    public function down(): void
    {
        Schema::table('microvix_logs', function (Blueprint $table) {
            $table->dropColumn(['order_id', 'simulated']);
        });
    }
};
