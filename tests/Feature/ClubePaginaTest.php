<?php

namespace Tests\Feature;

use Database\Seeders\ClubePlanos2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clube (leva 10): pontos ainda não existem no sistema — a página e os
 * perks falam "em breve", sem prometer número. Leva 14: a página não lista
 * mais os planos; o CTA do hero leva ao formulário de interesse.
 */
class ClubePaginaTest extends TestCase
{
    use RefreshDatabase;

    public function test_clube_nao_promete_pontos_com_numero_e_cta_vai_pro_formulario(): void
    {
        $this->seed(ClubePlanos2026Seeder::class);

        $html = $this->get('/clube')->assertOk()->getContent();

        $this->assertStringContainsString('Mais do que benefícios. Um universo para pertencer.', $html);
        $this->assertStringContainsString('href="#clube-form"', $html);
        $this->assertStringContainsString('id="clube-form"', $html);
        $this->assertStringContainsString('action="'.route('clube.interesse').'"', $html);
        $this->assertStringContainsString('Em breve: acumule pontos nas suas compras', $html);
        $this->assertStringNotContainsString('10% do valor', $html); // "10%" sozinho aparece no CSS
        $this->assertStringNotContainsString('Ganhe pontos de boas-vindas', $html);
    }
}
