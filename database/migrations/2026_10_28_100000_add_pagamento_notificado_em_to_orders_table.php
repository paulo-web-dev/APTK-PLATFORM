<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leva 17: trava do segundo aviso ("pagamento confirmado"). O primeiro
 * (pedido criado) segue em notificado_em; o erro de qualquer dos dois fica
 * em notificacao_erro / notificacao_erro_em.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('pagamento_notificado_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('pagamento_notificado_em');
        });
    }
};
