<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Domínio próprio de marca (MarcaHost + config/marcas.php):
 * raiz serve a LP isolada, resto leva 301 pra APTK, assets passam,
 * e as rotas da APTK (/ice4pros, /barin) seguem intocadas.
 */
class MarcaHostTest extends TestCase
{
    use RefreshDatabase; // a home da APTK consulta products/posts

    private function aptkUrl(string $path = ''): string
    {
        return rtrim(config('app.url'), '/').$path;
    }

    public function test_raiz_no_host_de_marca_serve_a_lp_com_layout_isolado(): void
    {
        $response = $this->get('http://ice4pros.com.br/');

        $response->assertOk()
            // conteúdo da LP intacto
            ->assertSee('O melhor gelo cristal do Brasil')
            // layout isolado presente...
            ->assertSee('marca-header')
            ->assertSee('uma marca')
            // ...e nada do layout público da APTK
            ->assertDontSee('site-header')
            ->assertDontSee('site-nav')
            ->assertDontSee('feature-band')
            ->assertDontSee('news-form');
    }

    public function test_hosts_www_e_de_dev_tambem_servem_a_lp(): void
    {
        $this->get('http://www.ice4pros.com.br/')->assertOk()->assertSee('marca-header');
        $this->get('http://ice4pros.localhost/')->assertOk()->assertSee('marca-header');
    }

    public function test_canonical_e_og_no_contexto_isolado(): void
    {
        $this->get('http://ice4pros.com.br/')
            ->assertSee('<link rel="canonical" href="https://ice4pros.com.br/">', false)
            ->assertSee('<meta property="og:url" content="https://ice4pros.com.br/">', false)
            ->assertSee('<meta property="og:site_name" content="Ice4Pros">', false)
            ->assertSee('<title>Ice4Pros — O Melhor Gelo Cristal do Brasil</title>', false);
    }

    public function test_raiz_no_host_da_aptk_continua_sendo_a_home(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('site-header')
            ->assertDontSee('marca-header');
    }

    public function test_ice4pros_no_host_da_aptk_mantem_o_layout_completo(): void
    {
        $this->get('/ice4pros')
            ->assertOk()
            ->assertSee('O melhor gelo cristal do Brasil')
            // layout público completo: header, nav, feature band, newsletter
            ->assertSee('site-header')
            ->assertSee('site-nav')
            ->assertSee('feature-band')
            ->assertSee('news-form')
            ->assertDontSee('marca-header')
            // canonical aponta pro domínio próprio também neste contexto
            ->assertSee('<link rel="canonical" href="https://ice4pros.com.br/">', false);
    }

    public function test_barin_no_host_da_aptk_segue_inalterada(): void
    {
        $this->get('/barin')
            ->assertOk()
            ->assertSee('site-header')
            ->assertDontSee('marca-header');
    }

    public function test_qualquer_caminho_no_host_de_marca_redireciona_301_pra_aptk(): void
    {
        $this->get('http://ice4pros.com.br/loja?categoria=ice4pros')
            ->assertStatus(301)
            ->assertHeader('Location', $this->aptkUrl('/loja?categoria=ice4pros'));

        // caminho que nem existe como rota também redireciona (middleware global)
        $this->get('http://ice4pros.com.br/caminho-inexistente')
            ->assertStatus(301)
            ->assertHeader('Location', $this->aptkUrl('/caminho-inexistente'));
    }

    public function test_assets_e_acme_challenge_nao_redirecionam_no_host_de_marca(): void
    {
        // Sem rota Laravel pra esses caminhos: o que importa é NÃO ser 301
        // (em produção o Apache os serve antes do framework).
        $this->get('http://ice4pros.com.br/.well-known/acme-challenge/token-de-teste')
            ->assertStatus(404);
        $this->get('http://ice4pros.com.br/css/aptk.css')->assertStatus(404);
        $this->get('http://ice4pros.com.br/video/ice4pros-cubo.mp4')->assertStatus(404);
    }

    public function test_helper_aptk_route_gera_url_absoluta_da_aptk(): void
    {
        $this->assertSame($this->aptkUrl('/loja'), aptk_route('catalog'));
        $this->assertSame(
            $this->aptkUrl('/loja?categoria=ice4pros'),
            aptk_route('catalog', ['categoria' => 'ice4pros'])
        );
    }

    public function test_ctas_da_lp_no_host_de_marca_apontam_pra_aptk(): void
    {
        $this->get('http://ice4pros.com.br/')
            ->assertSee($this->aptkUrl('/loja?categoria=ice4pros'), false)
            ->assertSee($this->aptkUrl('/collabs'), false);
    }
}
