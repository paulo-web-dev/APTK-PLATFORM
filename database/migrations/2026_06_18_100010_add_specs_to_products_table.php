<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('base')->nullable()->after('short_description');  // destilado base (ex.: APTK Gin)
            $table->unsignedTinyInteger('abv')->nullable()->after('base');   // teor alcoólico em % (inteiro)
            $table->json('sizes')->nullable()->after('abv');                 // tamanhos disponíveis (array de strings)
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['base', 'abv', 'sizes']);
        });
    }
};
