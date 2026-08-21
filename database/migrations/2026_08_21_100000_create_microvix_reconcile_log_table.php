<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trilha de auditoria da conciliação ERP → site. Guarda valor anterior
        // E novo: se o ERP mandar preço errado e sobrescrever a vitrine, este
        // log é o caminho de volta. Só gravação real loga — dry-run não.
        Schema::create('microvix_reconcile_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index(); // sem FK (padrão do projeto)
            $table->string('size', 30)->nullable();
            $table->unsignedBigInteger('codigoproduto');       // SKU do ERP que originou
            $table->string('campo', 10);                       // saldo | preco
            $table->decimal('de', 12, 3)->nullable();          // null = não havia valor
            $table->decimal('para', 12, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microvix_reconcile_log');
    }
};
