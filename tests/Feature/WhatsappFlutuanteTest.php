<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Leva 20: botão flutuante de WhatsApp (<x-whatsapp-flutuante>) em todas as
 * páginas públicas (layouts.public, layouts.marca, layouts.ice4pros), com
 * mensagem de contexto por página; nunca no admin.
 */
class WhatsappFlutuanteTest extends TestCase
{
    use RefreshDatabase;

    private function flutuante(string $html): ?string
    {
        preg_match_all('#<a\b[^>]*data-whatsapp-flutuante[^>]*>#', $html, $m);
        $this->assertLessThanOrEqual(1, count($m[0]), 'mais de um botão flutuante');

        return $m[0][0] ?? null;
    }

    private function mensagem(string $tag): string
    {
        preg_match('#href="https://wa\.me/\d+\?text=([^"]+)"#', $tag, $m);

        return rawurldecode(html_entity_decode($m[1] ?? ''));
    }

    public static function paginas(): array
    {
        return [
            'home'          => ['/', 'Olá! Vim pelo site da APTK.'],
            'loja'          => ['/loja', 'Olá! Vim pelo site da APTK.'],
            'eventos'       => ['/eventos', 'Olá! Vim pelo site e quero uma proposta para um evento.'],
            'custom'        => ['/custom', 'Olá! Vim pelo site e quero saber mais sobre o Full Custom.'],
            'collabs'       => ['/collabs', 'Olá! Vim pelo site e quero conversar sobre uma collab / projeto personalizado.'],
            'barin'         => ['/barin', 'Olá! Vim pelo site e quero saber mais sobre a BARIN.'],
            'carrinho'      => ['/carrinho', 'Olá! Vim pelo site da APTK.'],
            'ice4pros'      => ['/ice4pros', 'Olá! Vim pelo site da Ice4Pros.'],
            'host ice4pros' => ['http://ice4pros.com.br/', 'Olá! Vim pelo site da Ice4Pros.'],
        ];
    }

    /** @dataProvider paginas */
    public function test_botao_aparece_com_a_mensagem_da_pagina(string $url, string $mensagem): void
    {
        $tag = $this->flutuante($this->get($url)->assertOk()->getContent());

        $this->assertNotNull($tag, "sem botão flutuante em {$url}");
        $this->assertStringContainsString('aria-label="Falar no WhatsApp"', $tag);
        $this->assertStringContainsString('target="_blank"', $tag);
        $this->assertStringContainsString('rel="noopener"', $tag);
        $this->assertStringContainsString('https://wa.me/'.config('marcas.whatsapp').'?text=', $tag);
        $this->assertSame($mensagem, $this->mensagem($tag));
    }

    public function test_pagina_de_produto_leva_o_nome_do_produto(): void
    {
        $categoria = ProductCategory::firstOrCreate(['slug' => 'classicos'], ['name' => 'Clássicos', 'active' => true]);
        Product::create([
            'name' => 'Negroni & Cia', 'slug' => 'negroni-cia', 'category_id' => $categoria->id,
            'sku' => 'NG750', 'price' => 210, 'sizes' => ['750 ml'], 'size_prices' => ['750 ml' => 210],
            'active' => true,
        ]);

        $tag = $this->flutuante($this->get('/produto/negroni-cia')->assertOk()->getContent());

        $this->assertNotNull($tag);
        $this->assertSame('Olá! Vim pelo site e tenho uma dúvida sobre Negroni & Cia.', $this->mensagem($tag));
    }

    public function test_cores_vem_dos_tokens_de_cada_marca(): void
    {
        $aptk = file_get_contents(public_path('css/aptk-tokens.css'));
        $i4p = file_get_contents(public_path('css/ice4pros-tokens.css'));

        $this->assertMatchesRegularExpression('/--color-whatsapp:\s*#25D366;/', $aptk);
        $this->assertMatchesRegularExpression('/--color-whatsapp:\s*var\(--i4p-turquesa\);/', $i4p);
        $this->assertMatchesRegularExpression('/--color-on-whatsapp:\s*var\(--i4p-preto\);/', $i4p);
    }

    public function test_nao_aparece_no_admin(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@aptk.test', 'password' => bcrypt('x'), 'role' => 'admin']);

        foreach (['/admin', '/admin/produtos', '/admin/pedidos'] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertNull($this->flutuante($html), $url);
            $this->assertStringNotContainsString('wa-flutuante', $html, $url);
        }
    }
}
