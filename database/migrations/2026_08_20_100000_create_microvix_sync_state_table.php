<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('microvix_sync_state', function (Blueprint $table) {
            $table->id();
            $table->string('method', 60)->unique();
            // TIMESTAMP do SQL Server (contador incremental, por método) —
            // NÃO é tempo. Guardar sempre o MAIOR valor retornado.
            $table->unsignedBigInteger('last_timestamp')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('records_synced')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microvix_sync_state');
    }
};
