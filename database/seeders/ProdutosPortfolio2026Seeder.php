<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Portfólio APTK 2026 (arquivo oficial "PORTFÓLIO Ed. 2026") — atualiza as
 * DESCRIÇÕES dos produtos existentes com a redação canônica: tipo,
 * ingredientes e "serviço perfeito" de cada rótulo.
 *
 * NÃO mexe em: preço, volumes vendidos (375/750), estoque, peso/dimensões,
 * imagens ou status. Atualização por slug; slug ausente é ignorado com aviso.
 *
 * Rodar: php artisan db:seed --class=ProdutosPortfolio2026Seeder
 */
class ProdutosPortfolio2026Seeder extends Seeder
{
    public function run(): void
    {
        $portfolio = [
            // ---- CLÁSSICOS ----
            'negroni-classico' => [
                'abv'  => 27,
                'desc' => 'Coquetel composto. Ingredientes: APTK Gin, amaro e vermute rosso. Serviço perfeito: copo baixo, gelo, 90 ml de Negroni e zest de laranja Bahia.',
            ],
            'fitzgerald' => [
                'abv'  => 30,
                'desc' => 'Coquetel alcoólico. Ingredientes: APTK Gin, limão, bitters e açúcar. Serviço perfeito: copo baixo, gelo, 90 ml de Fitzgerald e zest de limão siciliano.',
            ],
            'cosmopolitan' => [
                'abv'  => 30,
                'desc' => 'Coquetel alcoólico. Ingredientes: Vodka VDK, limão, cranberry e laranja. Serviço perfeito: taça, 90 ml de Cosmopolitan e um zest de limão.',
            ],
            'moscow-mule' => [
                'abv'  => 30,
                'desc' => 'Coquetel alcoólico. Ingredientes: Vodka VDK, mix de cítricos, açúcar e especiarias. Serviço perfeito: caneca de cobre, 70 ml de Moscow Mule e espuma de gengibre.',
            ],
            'limoncello' => [
                'abv'  => 25,
                'desc' => 'Licor fino de limão. Ingredientes: álcool destilado de cereais, limão siciliano, água e açúcar. Serviço perfeito: taça coupe e 80 ml de Limoncello.',
            ],

            // ---- AUTORAIS ----
            'caju-amigo' => [
                'abv'  => 10,
                'desc' => 'Bebida alcoólica mista. Ingredientes: Vodka VDK, limão, caju e açúcar. Serviço perfeito: copo longo, muito gelo e 80 ml de Caju Amigo.',
            ],
            'penicillin' => [
                'abv'  => 17,
                'desc' => 'Coquetel alcoólico. Ingredientes: Buffalo Trace, limão, mel e gengibre. Serviço perfeito: copo baixo, muito gelo e 80 ml de Penicillin.',
            ],
            'sur-lorange' => [
                'abv'  => 30,
                'desc' => 'Coquetel alcoólico. Ingredientes: cachaça envelhecida, laranja e amêndoas.',
            ],
            'squadra-amendoas' => [
                'abv'  => 25,
                'desc' => 'Licor fino de amêndoas. Receita exclusiva, aveludada e aromática, com sabor único obtido da fruta.',
            ],
            'squadra-coco' => [
                'abv'  => 17,
                'desc' => 'Licor fino de coco. Receita exclusiva, cremosa e tropical, com sabor único obtido da fruta.',
            ],

            // ---- BASES ----
            'cachaca-prata' => [
                'abv'  => 40,
                'desc' => 'Cachaça. Mosto fermentado do caldo de cana de açúcar.',
            ],
            'cachaca-ouro' => [
                'abv'  => 40,
                'desc' => 'Cachaça envelhecida. Mosto fermentado do caldo de cana de açúcar, armazenada em barris de carvalho.',
            ],
            'neutral-gin' => [
                'abv'  => 42,
                'desc' => 'Gin. Botânicos: menta, limão, alcaçuz e lavanda. Serviço perfeito como base para coquetéis.',
            ],
            'vodka-vdk' => [
                'abv'  => 40,
                'desc' => 'Vodka. Álcool etílico potável de origem agrícola e água. A base da casa para coquetéis.',
            ],
        ];

        foreach ($portfolio as $slug => $data) {
            $updated = Product::where('slug', $slug)->update([
                'abv'         => $data['abv'],
                'description' => $data['desc'],
            ]);

            if (! $updated) {
                $this->command?->warn("Portfólio 2026: produto '{$slug}' não encontrado — pulado.");
            }
        }

        $this->command?->info('Portfólio 2026: descrições oficiais aplicadas.');
    }
}
