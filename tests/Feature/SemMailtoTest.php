<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Leva 20: nenhum botão/link do site público usa mailto: (no Windows abre o
 * Outlook pedindo conta e o cliente desiste). Contato comercial é WhatsApp;
 * o e-mail aparece só como texto no rodapé, então não há exceção a liberar.
 */
class SemMailtoTest extends TestCase
{
    use RefreshDatabase;

    private const PAGINAS = [
        '/', '/loja', '/custom', '/quem-somos', '/clube', '/eventos', '/collabs',
        '/assinantes', '/novidades', '/barin', '/ice4pros',
        '/carrinho', '/login', '/register', // /checkout com carrinho vazio volta pro carrinho
        'http://ice4pros.com.br/', // por último: o host fica nas requisições seguintes do teste
    ];

    public function test_nenhuma_pagina_publica_tem_mailto(): void
    {
        foreach (self::PAGINAS as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertDoesNotMatchRegularExpression('#href\s*=\s*["\']?\s*mailto:#i', $html, $url);
        }
    }

    public function test_ctas_comerciais_vao_pro_whatsapp(): void
    {
        $eventos = $this->get('/eventos')->getContent();
        $link = e(whatsapp_link('Olá! Vim pelo site e quero uma proposta para um evento.'));
        $this->assertSame(2, substr_count($eventos, 'href="'.$link.'" target="_blank" rel="noopener" class="btn-aptk"'));

        $collabs = $this->get('/collabs')->getContent();
        $this->assertGreaterThanOrEqual(2, substr_count($collabs, e(whatsapp_link('Olá! Vim pelo site e quero conversar sobre uma collab / projeto personalizado.'))));

        $this->get('/ice4pros')->assertSee(e(whatsapp_link(config('marcas.marcas.ice4pros.whatsapp_mensagem'), 'ice4pros')), false);
        $this->get('/barin')->assertSee(whatsapp_link('Olá! Vim pelo site e quero falar sobre revenda da BARIN.'), false);
    }
}
