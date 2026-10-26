<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Eventos (leva 10): view própria com o texto do briefing; portfólio (grade
 * de collabs desde a leva 14) só aparece com ao menos 1 item; o fechamento
 * não tem mais "Ver a carta".
 */
class EventosPaginaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagina_de_eventos_com_texto_novo(): void
    {
        $this->get('/eventos')
            ->assertOk()
            ->assertSee('Grandes encontros merecem uma assinatura especial.')
            ->assertSee('Cada ocasião, infinitas possibilidades.')
            ->assertSee('Diferentes formatos. A mesma excelência.')
            ->assertSee('Da primeira ideia ao último brinde.')
            ->assertSee('Seu próximo evento merece algo extraordinário.')
            ->assertSee('mailto:contato@aptkspirits.com', false)
            ->assertDontSee('Ver a carta');
    }

    public function test_portfolio_some_sem_itens(): void
    {
        $this->withViewErrors([])
            ->view('shop.eventos', ['portfolio' => [], 'proposta' => 'mailto:teste@exemplo.test'])
            ->assertDontSee('Marcas que já brindaram com a APTK.');
    }

    public function test_collabs_segue_no_template_institucional(): void
    {
        $this->get('/collabs')->assertOk()->assertSee('Tenha um coquetel para chamar de seu');
    }
}
