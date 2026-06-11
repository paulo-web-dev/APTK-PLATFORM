<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();   // franchise | event | partner
            $table->string('name');
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('new');   // new | contacted | won | lost
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
