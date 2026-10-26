<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Leva 14: os planos antigos do Clube (Admirador, Bronze, Prata, Ouro, Aged)
 * seguiam ativos em produção porque o ClubePlanos2026Seeder desativava pelos
 * nomes, não pelos slugs reais. Só `active`; registros e assinaturas ficam.
 */
return new class extends Migration
{
    private const SLUGS = ['descoberta', 'classicos', 'premium', 'small-batch', 'corporativo'];

    public function up(): void
    {
        DB::table('subscription_plans')->whereIn('slug', self::SLUGS)->update(['active' => false]);
    }

    public function down(): void
    {
        DB::table('subscription_plans')->whereIn('slug', self::SLUGS)->update(['active' => true]);
    }
};
