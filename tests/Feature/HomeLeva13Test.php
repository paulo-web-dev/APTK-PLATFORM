<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Leva 13: fotos da Home (img/aptk/home-*.jpg) e destino dos cards do
 * "Explore a APTK".
 */
class HomeLeva13Test extends TestCase
{
    use RefreshDatabase;

    public function test_cards_usam_as_fotos_novas_otimizadas(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['home-coqueteis-prontos', 'home-crie-seu-drink', 'home-arte-de-presentear',
                  'home-clube', 'home-barin', 'home-ice4pros'] as $foto) {
            $this->assertStringContainsString("img/aptk/{$foto}.jpg", $html);
            $this->assertFileExists(public_path("img/aptk/{$foto}.jpg"));
            $this->assertLessThan(500 * 1024, filesize(public_path("img/aptk/{$foto}.jpg")));
        }
        foreach (['about-loja-2.jpg', 'cat-fitzgerald.jpg', 'featured-negroni.jpg', 'hero-bartender.jpg', 'ice4pros-cubo.jpg'] as $antiga) {
            $this->assertStringNotContainsString($antiga, $html);
        }
    }

    public function test_crie_seu_drink_vai_pro_custom_e_presentear_pro_gifting_de_eventos(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('custom'), '#').'" class="media-card hx-card">.*?home-crie-seu-drink.*?Um coquetel\. A sua assinatura\.#s', $html);
        $this->assertStringContainsString('href="'.route('pages.show', 'eventos').'#gifting" class="media-card hx-card"', $html);
        $this->assertStringNotContainsString('categoria=bases', $html);

        $this->get(route('pages.show', 'eventos'))->assertOk()
            ->assertSee('id="gifting"', false);
    }
}
