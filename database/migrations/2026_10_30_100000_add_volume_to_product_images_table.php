<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto por volume (leva 18): null = foto geral (garrafa grande, aparece em
 * todo volume); "375 ml" (ou outro volume do produto) = foto só daquela
 * garrafa. Sem foto do volume escolhido, o site cai nas gerais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('volume', 30)->nullable()->after('fundo');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('volume');
        });
    }
};
