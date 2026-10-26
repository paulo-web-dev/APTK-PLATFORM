<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Leva 14: fotos novas do Custom ("Sua história. Nossa alquimia." e Full Custom).
 */
class CustomLeva14Test extends TestCase
{
    use RefreshDatabase;

    public function test_usa_as_fotos_novas_otimizadas(): void
    {
        $html = $this->get('/custom')->assertOk()->getContent();

        foreach (['custom-presentes', 'custom-marcas', 'custom-full'] as $foto) {
            $this->assertStringContainsString("img/aptk/{$foto}.jpg", $html);
            $this->assertFileExists(public_path("img/aptk/{$foto}.jpg"));
            $this->assertLessThan(500 * 1024, filesize(public_path("img/aptk/{$foto}.jpg")));
        }
        foreach (['cat-pessoas.jpg', 'about-loja-3.jpg', 'cat-dry-martini.jpg'] as $antiga) {
            $this->assertStringNotContainsString($antiga, $html);
        }
    }
}
