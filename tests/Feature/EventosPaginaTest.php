<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Eventos (leva 10): view própria com o texto do briefing; portfólio só
 * aparece com ao menos 1 evento; o fechamento não tem mais "Ver a carta".
 */
class EventosPaginaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagina_de_eventos_com_texto_novo_e_sem_portfolio_vazio(): void
    {
        $this->get('/eventos')
            ->assertOk()
            ->assertSee('Grandes encontros merecem uma assinatura especial.')
            ->assertSee('Cada ocasião, infinitas possibilidades.')
            ->assertSee('Diferentes formatos. A mesma excelência.')
            ->assertSee('Da primeira ideia ao último brinde.')
            ->assertSee('Seu próximo evento merece algo extraordinário.')
            ->assertSee('mailto:contato@aptkspirits.com', false)
            ->assertDontSee('Ver a carta')
            ->assertDontSee('Eventos que já levaram a nossa assinatura.');
    }

    public function test_portfolio_aparece_com_ao_menos_um_evento(): void
    {
        $this->withViewErrors([])
            ->view('shop.eventos', [
                'portfolio' => [['titulo' => 'Lançamento Exemplo', 'quando' => '2025 · São Paulo', 'descricao' => 'Bar APTK.', 'image' => null]],
                'proposta'  => 'mailto:teste@exemplo.test',
            ])
            ->assertSee('Eventos que já levaram a nossa assinatura.')
            ->assertSee('Lançamento Exemplo');
    }

    public function test_collabs_segue_no_template_institucional(): void
    {
        $this->get('/collabs')->assertOk()->assertSee('Tenha um coquetel para chamar de seu');
    }
}
