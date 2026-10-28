<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cor de fundo da foto (#RRGGBB, amostrada nas bordas por FundoFoto): a
 * moldura acompanha a foto — branca nas de fundo branco, preta nas de fundo
 * preto. Null = fallback --color-bg-media.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('fundo', 7)->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('fundo');
        });
    }
};
