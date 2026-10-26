<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use Database\Seeders\ClubePlanos2026Seeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Leva 14: fotos novas do Clube, seção de planos fora e planos antigos
 * desativados (pelo slug real).
 */
class ClubeLeva14Test extends TestCase
{
    use RefreshDatabase;

    public function test_usa_as_fotos_novas_e_nao_lista_planos(): void
    {
        $this->seed(ClubePlanos2026Seeder::class);

        $html = $this->get('/clube')->assertOk()->getContent();

        foreach (['clube-hero-2', 'clube-faca-parte'] as $foto) {
            $this->assertStringContainsString("img/aptk/{$foto}.jpg", $html);
            $this->assertLessThan(500 * 1024, filesize(public_path("img/aptk/{$foto}.jpg")));
        }
        $this->assertStringNotContainsString('img/aptk/clube-hero.jpg', $html);
        $this->assertStringNotContainsString('Do primeiro gole ao colecionador', $html);
        $this->assertStringNotContainsString('id="planos"', $html);
        $this->assertStringNotContainsString('/clube/assinar/', $html);
        $this->assertStringContainsString('Le Cercle APTK', $html);
    }

    public function test_seeder_desativa_os_planos_antigos_pelo_slug(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(ClubePlanos2026Seeder::class);

        $this->assertEqualsCanonicalizing(
            ['aptk-explorer', 'aptk-collector', 'aptk-connoisseur', 'le-cercle-aptk'],
            SubscriptionPlan::where('active', true)->pluck('slug')->all(),
        );
    }

    public function test_migration_desativa_so_os_planos_antigos(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(ClubePlanos2026Seeder::class);
        SubscriptionPlan::query()->update(['active' => true]); // como estava em produção

        $migration = require database_path('migrations/2026_10_26_100000_desativar_planos_antigos_do_clube.php');
        $migration->up();

        $this->assertEqualsCanonicalizing(
            ['aptk-explorer', 'aptk-collector', 'aptk-connoisseur', 'le-cercle-aptk'],
            SubscriptionPlan::where('active', true)->pluck('slug')->all(),
        );
        $this->assertSame(9, SubscriptionPlan::count());

        $migration->down();
        $this->assertSame(9, SubscriptionPlan::where('active', true)->count());
    }

    public function test_planos_pagos_seguem_por_url_direta(): void
    {
        $this->seed(ClubePlanos2026Seeder::class);
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)->get(route('subscription.checkout', SubscriptionPlan::where('slug', 'aptk-collector')->first()))
            ->assertOk();
    }
}
