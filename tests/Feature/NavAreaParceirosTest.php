<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Header: "Área de Parceiros" é link externo (portal de parceiros), último
 * item da nav, em nova aba e nunca ativo. /parceiros segue indo pra /collabs.
 */
class NavAreaParceirosTest extends TestCase
{
    public function test_ultimo_item_da_nav_abre_o_portal_em_nova_aba(): void
    {
        $html = $this->get('/quem-somos')->assertOk()->getContent();

        $this->assertStringContainsString(
            '<li><a href="https://parceiro.aptkspirits.com/" target="_blank" rel="noopener">Área de Parceiros</a></li>',
            $html,
        );

        // Depois de Collabs e antes dos ícones.
        $collabs = strpos($html, '>Collabs</a>');
        $parceiros = strpos($html, '>Área de Parceiros</a>');
        $acoes = strpos($html, 'class="header-actions"');
        $this->assertTrue($collabs < $parceiros && $parceiros < $acoes);
    }

    public function test_redirect_de_parceiros_para_collabs_continua(): void
    {
        $this->get('/parceiros')->assertRedirect('/collabs')->assertStatus(301);
    }
}
