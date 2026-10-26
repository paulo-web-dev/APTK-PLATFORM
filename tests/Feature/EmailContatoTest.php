<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Leva 14: CTAs de e-mail usam o contato geral (config marcas.email_contato),
 * não o e-mail pessoal do comercial.
 */
class EmailContatoTest extends TestCase
{
    use RefreshDatabase;

    public function test_ctas_de_email_usam_o_contato_geral(): void
    {
        foreach (['collabs', 'eventos'] as $slug) {
            $html = $this->get(route('pages.show', $slug))->assertOk()->getContent();

            $this->assertStringContainsString('mailto:contato@aptkspirits.com?subject=', $html);
            $this->assertStringNotContainsString('rafael', strtolower($html));
        }
    }

    public function test_trocar_o_config_troca_todos_os_ctas(): void
    {
        config(['marcas.email_contato' => 'outro@aptkspirits.com']);

        $this->get(route('pages.show', 'collabs'))->assertOk()
            ->assertSee('mailto:outro@aptkspirits.com?subject=', false);
    }
}
