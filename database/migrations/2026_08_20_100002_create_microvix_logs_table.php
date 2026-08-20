<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Toda chamada ao WS fica registrada (request com credenciais mascaradas).
        // Sem isso a homologação com a Linx vira adivinhação.
        Schema::create('microvix_logs', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 3); // out = consulta | in = importa
            $table->string('method', 60)->index();
            $table->longText('request')->nullable();
            $table->longText('response')->nullable();
            $table->integer('http_status')->nullable();
            $table->boolean('success')->default(false);
            $table->text('error')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microvix_logs');
    }
};
