<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Leva 14: e-mail de contato geral (config marcas.email_contato), não o
 * pessoal do comercial. Leva 20: aparece só como texto nos rodapés, nunca
 * como destino de botão (ver SemMailtoTest).
 */
class EmailContatoTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_geral_aparece_como_texto_nos_rodapes(): void
    {
        foreach (['/', '/collabs', '/eventos', '/ice4pros', 'http://ice4pros.com.br/'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('contato@aptkspirits.com', $html, $url);
            $this->assertStringNotContainsString('mailto:', $html, $url);
            $this->assertStringNotContainsString('rafael', strtolower($html), $url);
        }
    }

    public function test_trocar_o_config_troca_o_rodape(): void
    {
        config(['marcas.email_contato' => 'outro@aptkspirits.com']);

        $this->get('/eventos')->assertOk()->assertSee('outro@aptkspirits.com');
    }
}
