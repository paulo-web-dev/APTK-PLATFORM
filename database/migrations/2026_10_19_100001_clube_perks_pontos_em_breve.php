<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Leva 10: o Clube prometia pontos (boas-vindas, 10% das compras, pontuação
 * em dobro) que o sistema não tem. Até a gestão decidir, os perks viram
 * "em breve", sem número. Mesmo texto do ClubePlanos2026Seeder. Troca só o
 * item exato; plano editado no admin depois disso não é sobrescrito.
 */
return new class extends Migration
{
    /** slug => [[texto antigo(s)], texto novo] */
    private const TROCAS = [
        'aptk-explorer' => [
            ['Ganhe pontos de boas-vindas ao entrar', '10% do valor das compras retorna em pontos'],
            'Em breve: acumule pontos nas suas compras e troque por produtos e experiências da casa.',
        ],
        'aptk-collector' => [
            ['Pontuação em dobro nas compras'],
            'Em breve: pontuação em dobro nas compras',
        ],
    ];

    public function up(): void
    {
        foreach (self::TROCAS as $slug => [$antigos, $novo]) {
            $this->troca($slug, function (array $perks) use ($antigos, $novo) {
                $pos = array_search($antigos[0], $perks, true);
                if ($pos === false) {
                    return $perks;
                }
                $perks = array_values(array_filter($perks, fn ($p) => ! in_array($p, $antigos, true)));
                array_splice($perks, $pos, 0, [$novo]);

                return $perks;
            });
        }
    }

    public function down(): void
    {
        foreach (self::TROCAS as $slug => [$antigos, $novo]) {
            $this->troca($slug, function (array $perks) use ($antigos, $novo) {
                $pos = array_search($novo, $perks, true);
                if ($pos === false) {
                    return $perks;
                }
                array_splice($perks, $pos, 1, $antigos);

                return $perks;
            });
        }
    }

    private function troca(string $slug, callable $fn): void
    {
        $plano = DB::table('subscription_plans')->where('slug', $slug)->first();
        if (! $plano) {
            return;
        }

        $perks = json_decode($plano->perks ?? '[]', true) ?: [];
        $novos = $fn($perks);
        if ($novos !== $perks) {
            DB::table('subscription_plans')->where('slug', $slug)
                ->update(['perks' => json_encode($novos, JSON_UNESCAPED_UNICODE)]);
        }
    }
};
