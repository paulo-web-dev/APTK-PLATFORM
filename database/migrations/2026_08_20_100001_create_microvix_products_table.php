<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Espelho do catálogo do ERP + mapeamento para o nosso catálogo.
        // Um codigoproduto do Microvix aponta para um product_id + size
        // (cada volume é um SKU próprio no ERP). Vínculo MANUAL, nunca automático.
        Schema::create('microvix_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('codigoproduto')->unique();
            $table->unsignedBigInteger('product_id')->nullable()->index(); // sem FK (padrão do projeto)
            $table->string('size', 30)->nullable();
            $table->string('referencia', 20)->nullable();
            $table->string('codauxiliar1', 40)->nullable();
            $table->string('nome_produto', 250)->nullable();
            $table->decimal('saldo', 12, 3)->nullable();
            $table->decimal('preco', 10, 2)->nullable();
            $table->boolean('ativo')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microvix_products');
    }
};
