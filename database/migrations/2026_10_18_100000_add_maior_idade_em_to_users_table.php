<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leva 09: quando o cliente declarou ter 18 anos ou mais (checkbox no
 * cadastro e no checkout). Usuário antigo fica null e declara no próximo
 * checkout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('maior_idade_em')->nullable()->after('cpf');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('maior_idade_em');
        });
    }
};
