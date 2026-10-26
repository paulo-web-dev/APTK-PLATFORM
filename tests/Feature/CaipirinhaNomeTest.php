<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Leva 14: "Caipirinha Arazul" vira "Caipirinha" no site todo (produto e
 * planilha). Slug caipirinha-arazul não muda; no Custom já era "Caipirinha".
 */
class CaipirinhaNomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_planilha_sem_arazul(): void
    {
        $csv = file_get_contents(database_path('data/catalogo-planilha-2026-10.csv'));

        $this->assertStringNotContainsString('Arazul', $csv);
        $this->assertSame(3, substr_count($csv, 'caipirinha-arazul,Caipirinha,aptk,'));
        $this->assertSame('Caipirinha', config('custom.bebidas.caipirinha-arazul.nome'));
    }

    public function test_migration_troca_so_o_nome_e_volta_no_down(): void
    {
        $migration = require database_path('migrations/2026_10_26_110000_caipirinha_sem_arazul.php');
        $p = Product::create(['name' => 'Caipirinha Arazul', 'slug' => 'caipirinha-arazul', 'sku' => 'CAIP750', 'price' => 165, 'active' => false]);
        $outro = Product::create(['name' => 'Negroni Clássico', 'slug' => 'negroni-classico', 'sku' => 'NC750', 'price' => 210, 'active' => false]);

        $migration->up();
        $this->assertSame('Caipirinha', $p->refresh()->name);
        $this->assertSame('caipirinha-arazul', $p->slug);
        $this->assertSame('Negroni Clássico', $outro->refresh()->name);

        $migration->down();
        $this->assertSame('Caipirinha Arazul', $p->refresh()->name);
    }

    public function test_importador_nao_volta_o_nome(): void
    {
        Product::create(['name' => 'Caipirinha', 'slug' => 'caipirinha-arazul', 'sku' => 'CAIP750', 'price' => 165, 'active' => false]);

        $this->artisan('catalogo:importar-planilha')->assertSuccessful();

        $this->assertSame('Caipirinha', Product::where('slug', 'caipirinha-arazul')->sole()->name);
    }
}
