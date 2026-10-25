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

    public function test_vitrine_editorial_sai_e_entram_collabs_e_lojas_largas_nessa_ordem(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('class="section hv"', $html);
        $this->assertStringNotContainsString('Histórias que vão além da garrafa.', $html);

        $universo = strpos($html, 'Um universo de possibilidades.');
        $collabs = strpos($html, 'Encontros que criam o extraordinário.');
        $lojas = strpos($html, 'A alquimia ganha endereço.');
        $clube = strpos($html, 'Pertencer é só o começo.');
        $this->assertTrue($universo < $collabs && $collabs < $lojas && $lojas < $clube);

        $this->assertStringContainsString('img/aptk/home-nossas-lojas.jpg', $html);
        $this->assertStringContainsString(route('pages.show', 'quem-somos').'#onde-encontrar', $html);
        $this->assertStringNotContainsString('Um círculo privado', $html);
    }

    public function test_collabs_usa_youtube_com_poster_e_troca_pelo_mp4_local_quando_existir(): void
    {
        $mp4 = public_path('video/collabs.mp4');
        $this->assertFileDoesNotExist($mp4);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-yt="j0ozDwpm9L8"', $html);
        $this->assertStringContainsString('youtube-nocookie.com', $html);
        $this->assertStringContainsString('img/aptk/collabs-poster.jpg', $html);
        $this->assertFileExists(public_path('img/aptk/collabs-poster.jpg'));
        $this->assertStringNotContainsString('video/collabs.mp4', $html);

        file_put_contents($mp4, '');
        try {
            $html = $this->get('/')->assertOk()->getContent();
            $this->assertStringContainsString('video/collabs.mp4', $html);
            $this->assertStringNotContainsString('data-yt=', $html);
            $this->assertStringContainsString('poster="'.asset('img/aptk/collabs-poster.jpg').'"', $html);
        } finally {
            unlink($mp4);
        }
    }
}
