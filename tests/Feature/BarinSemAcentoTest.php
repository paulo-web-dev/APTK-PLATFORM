<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Leva 13: a marca é BARIN, sem acento, em todo texto visível. Slugs e rotas
 * (/barin) não mudam; a planilha do importador também está sem acento.
 */
class BarinSemAcentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginas_nao_tem_mais_barin_com_acento(): void
    {
        foreach (['/', '/barin', route('pages.show', 'quem-somos'), route('catalog')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('Barín', $html, $url);
            $this->assertStringNotContainsString('BARÍN', $html, $url);
        }

        $this->get('/barin')->assertSee('A linha BARIN');
        $this->get('/')->assertSee('Conhecer a BARIN');
    }

    public function test_planilha_e_config_sem_acento(): void
    {
        $csv = file_get_contents(database_path('data/catalogo-planilha-2026-10.csv'));
        $this->assertStringNotContainsString('Barín', $csv);
        $this->assertStringContainsString('cajuzaco-barin,Cajuzaço BARIN,barin', $csv);
        $this->assertSame('Conhecer a BARIN', config('marcas.links.barin.label'));
    }

    public function test_migration_troca_so_o_nome_e_volta_no_down(): void
    {
        $migration = require database_path('migrations/2026_10_25_100000_barin_sem_acento.php');
        $cat = ProductCategory::updateOrCreate(['slug' => 'barin'], ['name' => 'Barín']);
        $p = Product::create(['name' => 'Negroni Barín', 'slug' => 'negroni-barin', 'sku' => 'NCB750', 'price' => 90, 'category_id' => $cat->id, 'active' => false]);
        $outro = Product::create(['name' => 'Negroni Clássico', 'slug' => 'negroni-classico', 'sku' => 'NC750', 'price' => 210, 'active' => false]);

        $migration->up();
        $this->assertSame('BARIN', $cat->refresh()->name);
        $this->assertSame('Negroni BARIN', $p->refresh()->name);
        $this->assertSame('negroni-barin', $p->slug);
        $this->assertSame('Negroni Clássico', $outro->refresh()->name);

        $migration->down();
        $this->assertSame('Barín', $cat->refresh()->name);
        $this->assertSame('Negroni Barín', $p->refresh()->name);
        $this->assertSame(1, DB::table('product_categories')->where('slug', 'barin')->count());
    }
}
