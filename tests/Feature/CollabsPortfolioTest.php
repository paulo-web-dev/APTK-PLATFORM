<?php

namespace Tests\Feature;

use App\Http\Controllers\Shop\PageController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Leva 14: grade de collabs (config/collabs.php) em /collabs, no lugar do
 * carrossel, e no portfólio de /eventos.
 */
class CollabsPortfolioTest extends TestCase
{
    use RefreshDatabase;

    private const ORDEM = ['Montblanc', 'Swarovski', 'Vivara', 'Iguatemi', 'Emiliano', 'Unique', 'Rosewood',
        'Banco Inter (Orange Nation)', 'Pirajá', 'Braz', 'Il Barista', 'Freixenet'];

    public function test_so_entram_as_ativas_com_foto_na_ordem_do_config(): void
    {
        $collabs = PageController::collabs();

        $this->assertSame(self::ORDEM, array_column($collabs, 'nome'));
        foreach ($collabs as $c) {
            $this->assertFileExists(public_path($c['imagem']));
            $this->assertLessThan(300 * 1024, filesize(public_path($c['imagem'])));
            [$w, $h] = getimagesize(public_path($c['imagem']));
            $this->assertEqualsWithDelta(0.8, $w / $h, 0.01, $c['imagem'].' fora do 4:5');
        }
    }

    public function test_aguardando_foto_ficam_no_config_inativas(): void
    {
        $inativas = collect(config('collabs.marcas'))->where('ativo', false)->pluck('nome')->all();

        $this->assertSame(['Selvagem', 'Gurumê', 'Camolese', 'Barbacoa'], $inativas);
    }

    public function test_reativar_uma_linha_com_foto_faz_a_marca_aparecer(): void
    {
        $marcas = config('collabs.marcas');
        $marcas[12] = ['nome' => 'Selvagem', 'imagem' => 'img/aptk/collab-braz.jpg', 'ativo' => true];
        config(['collabs.marcas' => $marcas]);

        $this->get('/collabs')->assertOk()->assertSee('<figcaption>Selvagem</figcaption>', false);
    }

    public function test_collabs_mostra_a_grade_no_lugar_do_carrossel(): void
    {
        $html = $this->get('/collabs')->assertOk()->getContent();

        $this->assertStringContainsString('Algumas histórias também são servidas em uma garrafa', $html);
        $this->assertStringContainsString('class="portfolio-grid"', $html);
        $this->assertStringNotContainsString('casesCarousel', $html);
        $this->assertStringNotContainsString('Foto do produto', $html);
        foreach (['Selvagem', 'Gurumê', 'Camolese', 'Barbacoa'] as $fora) {
            $this->assertStringNotContainsString($fora, $html);
        }
        $this->assertSame(self::ORDEM, $this->legendas($html));
    }

    public function test_eventos_mostra_o_mesmo_portfolio(): void
    {
        $html = $this->get('/eventos')->assertOk()->getContent();

        $this->assertStringContainsString('Eventos que já levaram a nossa assinatura.', $html);
        $this->assertSame(self::ORDEM, $this->legendas($html));
    }

    private function legendas(string $html): array
    {
        preg_match_all('#<figcaption>(.*?)</figcaption>#', $html, $m);

        return array_map(fn ($s) => html_entity_decode($s), $m[1]);
    }
}
