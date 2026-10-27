<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leva 16: aviso de pedido novo por e-mail para a equipe. notificado_em
 * torna o envio automático idempotente; o erro do último envio aparece no
 * detalhe do pedido no admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('notificado_em')->nullable();
            $table->text('notificacao_erro')->nullable();
            $table->timestamp('notificacao_erro_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['notificado_em', 'notificacao_erro', 'notificacao_erro_em']);
        });
    }
};
