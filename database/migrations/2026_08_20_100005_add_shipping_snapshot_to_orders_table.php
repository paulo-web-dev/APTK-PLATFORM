<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshot do endereço de entrega em colunas — mesma lógica do
        // order_items (congela product_name/unit_price). O cliente pode editar
        // ou apagar o endereço depois; o pedido continua sabendo para onde foi.
        // address_id é só referência fraca (sem FK): quem manda é o snapshot.
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('address_id')->nullable()->index()->after('user_id');
            $table->string('ship_name')->nullable()->after('notes');
            $table->string('ship_cpf', 14)->nullable()->after('ship_name');
            $table->string('ship_phone', 20)->nullable()->after('ship_cpf');
            $table->string('ship_zipcode', 9)->nullable()->after('ship_phone');
            $table->string('ship_street')->nullable()->after('ship_zipcode');
            $table->string('ship_number', 20)->nullable()->after('ship_street');
            $table->string('ship_complement')->nullable()->after('ship_number');
            $table->string('ship_neighborhood')->nullable()->after('ship_complement');
            $table->string('ship_city')->nullable()->after('ship_neighborhood');
            $table->string('ship_state', 2)->nullable()->after('ship_city');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['address_id']);
            $table->dropColumn([
                'address_id',
                'ship_name',
                'ship_cpf',
                'ship_phone',
                'ship_zipcode',
                'ship_street',
                'ship_number',
                'ship_complement',
                'ship_neighborhood',
                'ship_city',
                'ship_state',
            ]);
        });
    }
};
