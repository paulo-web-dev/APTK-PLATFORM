<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Leva 14: fotos novas de Eventos (hero e os quatro formatos).
 */
class EventosLeva14Test extends TestCase
{
    use RefreshDatabase;

    public function test_usa_as_fotos_novas_otimizadas(): void
    {
        $html = $this->get('/eventos')->assertOk()->getContent();

        foreach (['eventos-hero-2', 'eventos-bar', 'eventos-gifting', 'eventos-brand', 'home-crie-seu-drink'] as $foto) {
            $this->assertStringContainsString("img/aptk/{$foto}.jpg", $html);
            $this->assertLessThan(500 * 1024, filesize(public_path("img/aptk/{$foto}.jpg")));
        }
        foreach (['img/aptk/eventos-hero.jpg', 'clube-hero.jpg', 'featured-negroni.jpg', 'cat-evento.jpg', 'cat-fitzgerald.jpg'] as $antiga) {
            $this->assertStringNotContainsString($antiga, $html);
        }
        $this->assertMatchesRegularExpression('#id="gifting".*?eventos-gifting\.jpg#s', $html);
    }
}
