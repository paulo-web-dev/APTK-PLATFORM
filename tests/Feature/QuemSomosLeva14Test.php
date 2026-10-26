<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Leva 14: fotos novas de Quem Somos, galeria fora e fechamento sem o
 * contato pessoal do comercial.
 */
class QuemSomosLeva14Test extends TestCase
{
    use RefreshDatabase;

    public function test_usa_as_fotos_novas_otimizadas(): void
    {
        $html = $this->get('/quem-somos')->assertOk()->getContent();

        foreach (['qs-apothek-2016', 'qs-aptk-2021', 'qs-essencia',
                  'qs-marca-aptk', 'qs-marca-barin', 'qs-marca-ice4pros'] as $foto) {
            $this->assertStringContainsString("img/aptk/{$foto}.jpg", $html);
            $this->assertFileExists(public_path("img/aptk/{$foto}.jpg"));
            $this->assertLessThan(500 * 1024, filesize(public_path("img/aptk/{$foto}.jpg")));
        }
        foreach (['featured-negroni.jpg', 'about-loja-2.jpg', 'franquias-hero.jpg', 'cat-cosmopolitan.jpg'] as $antiga) {
            $this->assertStringNotContainsString($antiga, $html);
        }
    }

    public function test_galeria_sai_e_aptk_pelo_brasil_fica(): void
    {
        $this->get('/quem-somos')->assertOk()
            ->assertDontSee('Onde a alquimia acontece')
            ->assertSee('APTK pelo Brasil')
            ->assertSee('id="onde-encontrar"', false);
    }

    public function test_fechamento_sem_contato_pessoal_e_com_instagram(): void
    {
        $this->get('/quem-somos')->assertOk()
            ->assertSee('Vamos criar histórias juntos')
            ->assertDontSee('Rafael')
            ->assertDontSee('rafael@aptkspirits.com')
            ->assertDontSee('99278-8592')
            ->assertSee('https://www.instagram.com/aptkspirits/', false);
    }
}
