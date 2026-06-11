<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();   // sem FK
            $table->string('label')->nullable();              // Casa, Trabalho
            $table->string('name');                           // destinatário
            $table->string('street');
            $table->string('number', 20);                     // aceita "123A", "S/N"
            $table->string('complement')->nullable();
            $table->string('neighborhood');
            $table->string('city');
            $table->string('state', 2);                       // UF
            $table->string('zipcode', 9);                     // CEP 00000-000
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
