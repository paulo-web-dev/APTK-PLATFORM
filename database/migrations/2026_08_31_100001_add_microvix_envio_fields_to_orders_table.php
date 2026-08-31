<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fase 2 (pedido → ERP): estado do envio ao Microvix. Complementa as
        // colunas microvix_* da fase 1 (pedido_id, cod_cliente, status,
        // synced_at, reserved) — não duplica nada.
        Schema::table('orders', function (Blueprint $table) {
            // null = pedido de antes da integração (nunca enfileirado).
            // pendente | enviando | reservado | confirmado | cancelado | erro
            $table->string('microvix_envio_estado', 20)->nullable()->index()->after('microvix_reserved');
            $table->unsignedTinyInteger('microvix_envio_tentativas')->default(0)->after('microvix_envio_estado');
            $table->text('microvix_envio_erro')->nullable()->after('microvix_envio_tentativas');
            $table->timestamp('microvix_envio_em')->nullable()->after('microvix_envio_erro');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'microvix_envio_estado',
                'microvix_envio_tentativas',
                'microvix_envio_erro',
                'microvix_envio_em',
            ]);
        });
    }
};
