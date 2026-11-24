<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Identidade visual da Ice4Pros (layouts.ice4pros + ice4pros-tokens.css):
 * a LP nos dois endereços carrega só o CSS/fontes da Ice4Pros; as páginas
 * da APTK e da BARIN nunca carregam o CSS da Ice4Pros.
 */
class Ice4prosIdentidadeTest extends TestCase
{
    use RefreshDatabase; // a home e a loja da APTK consultam o banco

    public static function enderecosDaIce4pros(): array
    {
        return [
            'rota /ice4pros na APTK' => ['/ice4pros'],
            'domínio próprio'        => ['http://ice4pros.com.br/'],
        ];
    }

    /** @dataProvider enderecosDaIce4pros */
    public function test_lp_carrega_so_a_identidade_da_ice4pros(string $url): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee('css/ice4pros-tokens.css', false)
            ->assertSee('fonts/Oswald-Bold.woff2', false)
            ->assertSee('data-marca="ice4pros"', false)
            ->assertDontSee('aptk-tokens.css', false)
            ->assertDontSee('css/aptk.css', false)
            ->assertDontSee('bootstrap', false)
            ->assertDontSee('PPRader', false)
            ->assertDontSee('NeueWorld', false)
            // sem o script de tema da APTK (a marca já é escura)
            ->assertDontSee('data-theme', false);
    }

    /** @dataProvider enderecosDaIce4pros */
    public function test_meta_favicon_e_logos_da_ice4pros(string $url): void
    {
        $this->get($url)
            ->assertSee('<meta name="theme-color" content="#231F20">', false)
            ->assertSee('img/marcas/ice4pros/favicon.svg', false)
            ->assertSee('img/aptk/home-ice4pros.jpg', false) // og:image
            ->assertSee('img/marcas/ice4pros/ice4pros-wordmark.svg', false)
            ->assertSee('class="i4p-caveira"', false);
    }

    public function test_tokens_estao_escopados_na_marca(): void
    {
        $css = file_get_contents(public_path('css/ice4pros-tokens.css'));

        $this->assertStringContainsString('body[data-marca="ice4pros"] {', $css);
        $this->assertStringNotContainsString(':root', $css);

        // toda regra fora do @font-face começa no escopo da marca
        $semComentarios = preg_replace('#/\*.*?\*/#s', '', $css);
        $semFontFace = preg_replace('/@font-face\s*\{[^}]*\}/', '', $semComentarios);
        $semMedia = preg_replace('/@media[^{]*\{/', '', $semFontFace);
        preg_match_all('/([^{}]+)\{/', $semMedia, $m);

        foreach ($m[1] as $seletores) {
            foreach (explode(',', $seletores) as $seletor) {
                $this->assertMatchesRegularExpression(
                    '/^\s*(body)?\[data-marca="ice4pros"\]/',
                    $seletor,
                    "Seletor fora do escopo da Ice4Pros: {$seletor}"
                );
            }
        }
    }

    public static function paginasDaAptk(): array
    {
        return [['/'], ['/loja'], ['/barin'], ['/quem-somos']];
    }

    /** @dataProvider paginasDaAptk */
    public function test_paginas_da_aptk_nao_carregam_a_ice4pros(string $url): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee('css/aptk.css', false)
            ->assertDontSee('ice4pros-tokens.css', false)
            ->assertDontSee('Oswald', false)
            ->assertDontSee('data-marca="ice4pros"', false);
    }
}
