<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Leva 10: nomes novos das categorias da loja (briefing p.16). Só `name`:
 * os slugs ficam, então URLs (?categoria=...) e o catalogo:importar-planilha
 * não mudam. Categoria que não existir é ignorada.
 */
return new class extends Migration
{
    /** slug => [nome antigo, nome novo] */
    private const NOMES = [
        'classicos' => ['Clássicos', 'Clássicos'],
        'autorais'  => ['Autorais', 'Criações Autorais'],
        'bases'     => ['Bases', 'Bases & Ingredientes'],
        'barin'     => ['Barin', 'Barín'],
        'ice4pros'  => ['Ice4Pros', 'Ice4Pros'],
    ];

    public function up(): void
    {
        foreach (self::NOMES as $slug => [, $novo]) {
            DB::table('product_categories')->where('slug', $slug)->update(['name' => $novo]);
        }
    }

    public function down(): void
    {
        foreach (self::NOMES as $slug => [$antigo]) {
            DB::table('product_categories')->where('slug', $slug)->update(['name' => $antigo]);
        }
    }
};
