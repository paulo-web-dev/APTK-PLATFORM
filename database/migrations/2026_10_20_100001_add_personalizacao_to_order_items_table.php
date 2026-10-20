<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Garrafa custom (leva 11): {bebida, texto, fonte, cor} exatamente
            // como o cliente enviou. Nulo em item normal.
            $table->json('personalizacao')->nullable()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('personalizacao');
        });
    }
};
