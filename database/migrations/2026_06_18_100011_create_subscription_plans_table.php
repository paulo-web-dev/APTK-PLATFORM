<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('kicker')->nullable();              // tagline curta
            $table->decimal('price', 10, 2)->nullable();       // null = sob consulta (não vende sozinho)
            $table->string('price_label')->nullable();         // ex.: "Sob consulta"
            $table->string('interval', 20)->default('monthly'); // monthly | quarterly | yearly
            $table->json('perks')->nullable();                 // lista de benefícios
            $table->boolean('featured')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
