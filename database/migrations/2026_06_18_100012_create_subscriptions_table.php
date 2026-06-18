<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();               // sem FK (padrão do projeto)
            $table->unsignedBigInteger('subscription_plan_id')->index();
            $table->string('status', 20)->default('active')->index();     // active | paused | cancelled
            $table->decimal('price', 10, 2)->nullable();                  // snapshot do preço na assinatura
            $table->string('interval', 20)->default('monthly');
            $table->string('recipient_name')->nullable();
            $table->text('shipping_address')->nullable();                 // snapshot legível do endereço
            $table->string('payment_method', 30)->nullable();            // pix | cartao | boleto
            $table->timestamp('started_at')->nullable();
            $table->date('next_renewal_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
