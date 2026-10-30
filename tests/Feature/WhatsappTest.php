<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WhatsApp único da APTK: config('marcas.whatsapp') ← APTK_WHATSAPP, link
 * sempre por whatsapp_link(). Nenhuma página renderiza outro número.
 */
class WhatsappTest extends TestCase
{
    use RefreshDatabase;

    private const PAGINAS = [
        '/', '/loja', '/custom', '/quem-somos', '/clube', '/eventos', '/collabs',
        '/assinantes', '/novidades', '/barin', '/ice4pros', '/carrinho', '/login', '/register',
    ];

    public function test_helpers_montam_link_e_numero_da_aptk(): void
    {
        $this->assertSame('5511934298435', config('marcas.whatsapp'));
        $this->assertSame('https://wa.me/5511934298435', whatsapp_link());
        $this->assertSame('https://wa.me/5511934298435?text=Ol%C3%A1%21%20Or%C3%A7amento%20%26%20prazo', whatsapp_link('Olá! Orçamento & prazo'));
        $this->assertSame('(11) 93429-8435', whatsapp_formatado());
    }

    public function test_nenhuma_pagina_renderiza_outro_numero_e_todo_link_abre_em_nova_aba(): void
    {
        $vistos = 0;

        foreach (self::PAGINAS as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('api.whatsapp.com', $html, $url);
            $this->assertStringNotContainsString('whatsapp://', $html, $url);
            $this->assertStringNotContainsString('941766319', $html, $url); // número antigo do Full Custom

            preg_match_all('#<a\b[^>]*href="(https://wa\.me/[^"]*)"[^>]*>#', $html, $links, PREG_SET_ORDER);
            foreach ($links as [$tag, $href]) {
                $vistos++;
                $this->assertMatchesRegularExpression('#^https://wa\.me/5511934298435(\?text=[^"]+)?$#', html_entity_decode($href), "{$url}: {$href}");
                $this->assertStringContainsString('target="_blank"', $tag, $url);
                $this->assertStringContainsString('rel="noopener', $tag, $url);
            }
            // Qualquer wa.me fora de <a> também tem de ser o da APTK.
            preg_match_all('#wa\.me/(\d+)#', $html, $numeros);
            $this->assertSame([], array_values(array_diff(array_unique($numeros[1]), ['5511934298435'])), $url);
        }

        // Rodapé em todas menos login e cadastro (layout guest, sem rodapé) + Full Custom.
        $this->assertSame(count(self::PAGINAS) - 2 + 1, $vistos);

        // Admin: nenhum número de WhatsApp que não seja o da APTK.
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@aptk.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        foreach (['/admin', '/admin/produtos', '/admin/pedidos', '/admin/configuracoes'] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            preg_match_all('#wa\.me/(\d+)#', $html, $numeros);
            $this->assertSame([], array_values(array_diff($numeros[1], ['5511934298435'])), $url);
        }
    }

    public function test_rodape_tem_o_link_e_o_numero_escrito(): void
    {
        $this->get('/')
            ->assertSee('href="https://wa.me/5511934298435" target="_blank" rel="noopener" aria-label="WhatsApp da APTK: (11) 93429-8435"', false);
    }

    public function test_numero_vem_do_env(): void
    {
        config(['marcas.whatsapp' => '5521987654321']);

        $this->assertSame('https://wa.me/5521987654321', whatsapp_link());
        $this->assertSame('(21) 98765-4321', whatsapp_formatado());
        $this->get('/custom')->assertSee('https://wa.me/5521987654321?text=', false);
    }
}
