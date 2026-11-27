<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Leva 21: alterações pedidas pela Ice4Pros (docs/ice4pros-alteracoes.md).
 * Textos literais da LP, portfólio nos dois endereços, clientes, WhatsApp
 * próprio com a mensagem padrão e o encerramento legível.
 */
class Ice4prosAlteracoesTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAGEM = 'Olá! Conheci a Ice4Pros através do site e gostaria de receber mais informações sobre o portfólio e as soluções disponíveis.';

    public static function lp(): array
    {
        return [['/ice4pros'], ['http://ice4pros.com.br/']];
    }

    public static function portfolio(): array
    {
        return [['/ice4pros/produtos'], ['http://ice4pros.com.br/produtos']];
    }

    public static function todas(): array
    {
        return self::lp() + [2 => ['/ice4pros/produtos'], 3 => ['http://ice4pros.com.br/produtos']];
    }

    /** @dataProvider lp */
    public function test_lp_com_os_textos_do_briefing(string $url): void
    {
        $this->get($url)->assertOk()->assertSeeInOrder([
            'Ice4Pros · marca da holding Small Batches',
            'Gelo em estado de arte.',
            'Gelo cristal profissional, padronizado e personalizável para quem cria, serve e celebra.',
            'Inovação em estado sólido',
            'Há quase uma década, a Ice4Pros contribui',
            'Fábrica', 'Própria', '3×', 'Guia Michelin', '4×', 'Mais duração',
            'Cada formato, uma intenção.',
            'Ideais para Negroni, Old Fashioned, Penicillin, Boulevardier e suas variações.',
            'Ideais para Paloma, Highball, Tom Collins e demais long drinks.',
            'Soluções à altura de cada experiência.',
            'Bares, restaurantes &amp; hospitalidade',
            'Gelo personalizado',
            'Eventos e celebrações',
            'Ao lado de quem eleva a coquetelaria.',
            'A excelência está nos detalhes.',
            'Qualidade e precisão', 'Controle da diluição', 'Transparência e acabamento',
            'Leve gelo em estado de arte para o seu negócio.',
        ], false)
            ->assertDontSee('2017</span>', false)
            ->assertDontSee('Fundação')
            ->assertDontSee('em breve')
            ->assertSee('img/marcas/ice4pros/fotos/producao-gelo.jpg', false)
            ->assertSee('img/marcas/ice4pros/fotos/formato-esfera.jpg', false)
            ->assertSee('img/marcas/ice4pros/fotos/formato-diamante.jpg', false);
    }

    /** @dataProvider lp */
    public function test_clientes_dez_na_ordem_com_foto_so_nos_que_tem(string $url): void
    {
        $html = $this->get($url)->getContent();

        preg_match_all('#<span class="cli-nome">([^<]+)</span>#', $html, $m);
        $this->assertSame([
            'Copacabana Palace', 'Fasano', 'Emiliano', 'Hotel Unique', 'Casa Camolese',
            'Nosso Coquetelaria', 'Guilhotina', 'Astor', 'Gurumê', 'Pirajá',
        ], $m[1]);

        $this->assertSame(8, substr_count($html, 'data-foto-pendente'));
        $this->assertStringContainsString('fotos/clientes/copacabana-palace.jpg', $html);
        $this->assertStringContainsString('fotos/clientes/fasano.jpg', $html);
        foreach (['Cacau Show', 'Braz Pizzaria', 'Pullman'] as $saiu) {
            $this->assertStringNotContainsString($saiu, $html);
        }
    }

    /** @dataProvider portfolio */
    public function test_portfolio_com_os_dois_blocos_na_ordem(string $url): void
    {
        $html = $this->get($url)->assertOk()
            ->assertSee('class="i4p-wordmark', false)
            ->assertSee('Explore nossos formatos')
            ->assertSee('Diferentes formatos, infinitas possibilidades.')
            ->assertSee('img/marcas/ice4pros/fotos/bg-produtos.jpg', false)
            ->assertSeeInOrder(['Portfólio de gelo profissional', 'Um formato para cada ritual.', 'Custom Ice', 'Quando o gelo se transforma em experiência.', 'Produtos Ice4Pros'])
            ->assertDontSee('Explore nossos coquetéis')
            ->assertDontSee('o B2B da holding')
            ->getContent();

        preg_match_all('#data-formato="([^"]+)"#', $html, $f);
        $this->assertSame(['super30', 'cinquenta', '50x65', 'collins', 'esfera', 'diamante'], $f[1]);
        preg_match_all('#data-custom="([^"]+)"#', $html, $c);
        $this->assertSame(['id-no-copo', 'elementos-afetivos', 'acabamentos-premium'], $c[1]);

        $this->assertStringContainsString('30 × 30 mm<span aria-hidden="true">•</span>Volume e frescor', $html);
        $this->assertStringContainsString('Pack: 150 cubos', $html);
        $this->assertStringContainsString('Godfather · Whiskies Premium · Fitzgerald', $html);
        $this->assertStringContainsString('30 × 30 × 130 mm', $html);

        // toda foto do config existe no disco
        foreach (array_merge(config('ice4pros.formatos'), config('ice4pros.custom')) as $item) {
            $this->assertFileExists(public_path('img/marcas/ice4pros/fotos/'.$item['foto']));
        }
    }

    /** @dataProvider todas */
    public function test_todo_whatsapp_da_pagina_e_da_ice4pros_com_a_mensagem_padrao(string $url): void
    {
        $html = $this->get($url)->getContent();

        preg_match_all('#href="(https://wa\.me/[^"]*)"#', $html, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $href) {
            $this->assertSame(whatsapp_link(self::MENSAGEM, 'ice4pros'), html_entity_decode($href), $url);
        }
        $this->assertStringNotContainsString('wa.me/'.config('marcas.whatsapp'), $html);
    }

    /** @dataProvider todas */
    public function test_encerramento_com_wordmark_e_botoes_em_contorno_preto(string $url): void
    {
        $html = $this->get($url)->getContent();

        $this->assertMatchesRegularExpression('#i4p-closing.*?class="i4p-wordmark i4p-closing-logo"#s', $html);
        $this->assertSame(2, substr_count($html, 'class="i4p-btn i4p-btn--contorno-preto"'));
        $this->assertStringNotContainsString('i4p-btn--escuro', $html);
    }

    public function test_css_do_botao_do_encerramento_vence_o_link_da_faixa(): void
    {
        $css = file_get_contents(public_path('css/ice4pros-tokens.css'));

        $this->assertStringContainsString('[data-marca="ice4pros"] .i4p-faixa .i4p-btn--contorno-preto', $css);
        $this->assertStringNotContainsString('i4p-btn--escuro', $css);
    }

    /** @dataProvider todas */
    public function test_marca_escrita_ice4pros_no_texto(string $url): void
    {
        $html = $this->get($url)->getContent();
        // Fora de atributos/URLs (o SVG do logo é desenho, não texto).
        $texto = strip_tags(preg_replace('#<(script|style|svg)\b.*?</\1>#s', '', $html));

        $this->assertStringNotContainsString('ICE4PROS', $texto);
        $this->assertStringNotContainsString('ICE4PROs', $texto);
    }
}
