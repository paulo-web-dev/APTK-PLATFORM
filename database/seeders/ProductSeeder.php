<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;

/**
 * Catálogo APTK Spirits — Portfólio 2026.
 *
 * 3 categorias (Clássicos, Autorais, Bases) + 14 produtos, com imagens
 * (garrafa = principal, foto do drink = secundária) em storage/app/public/products.
 *
 * Idempotente: usa updateOrCreate por slug/path, então pode rodar de novo.
 * Os preços são SUGESTÕES — ajustar conforme a tabela comercial.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        /* ---------------- Categorias ---------------- */
        $categories = [
            ['slug' => 'classicos', 'name' => 'Clássicos', 'description' => 'Os grandes coquetéis de sempre, no ponto e engarrafados pela APTK — prontos para servir.'],
            ['slug' => 'autorais',  'name' => 'Autorais',  'description' => 'Criações e releituras com assinatura APTK: o nosso jeito inquieto de contar histórias.'],
            ['slug' => 'bases',     'name' => 'Bases',     'description' => 'Destilados com excelência de produção APTK, para criar na sua própria coquetelaria.'],
            // Leva 01: áreas de marca na página Produtos. Capa (image) e
            // produtos são cadastrados pelo admin — ficam vazias até lá.
            ['slug' => 'barin',     'name' => 'Barin',     'description' => 'A linha artesanal da holding, com receitas próprias e identidade de bar.', 'image' => null],
            ['slug' => 'ice4pros',  'name' => 'Ice4Pros',  'description' => 'Gelo, insumos e linha para empresas — o B2B da holding.', 'image' => null],
        ];

        $catId = [];
        foreach ($categories as $i => $c) {
            $catId[$c['slug']] = ProductCategory::updateOrCreate(
                ['slug' => $c['slug']],
                [
                    'name'        => $c['name'],
                    'description' => $c['description'],
                    'image'       => array_key_exists('image', $c) ? $c['image'] : "products/section-{$c['slug']}.jpg",
                    'active'      => true,
                    'sort_order'  => $i,
                ],
            )->id;
        }

        /* ---------------- Produtos ----------------
         | cada item: dados + montagem da descrição a partir dos campos do catálogo.
         */
        $products = [
            // ---- CLÁSSICOS ----
            [
                'slug' => 'negroni-classico', 'name' => 'Negroni Clássico', 'cat' => 'classicos',
                'short' => 'O amargo-doce do clássico italiano, no ponto, engarrafado pela APTK.',
                'tipo' => 'Coquetel composto', 'base' => 'APTK Gin', 'ingredientes' => 'gin, amaro e vermute rosso',
                'abv' => 27, 'sizes' => ['375 ml', '750 ml'],
                'servico' => 'copo baixo com gelo, 90 ml, finalizado com zest de Laranja Bahia',
                'price' => 189.90, 'sku' => 'NEG-750', 'weight' => 1.2, 'featured' => true,
            ],
            [
                'slug' => 'fitzgerald', 'name' => 'Fitzgerald', 'cat' => 'classicos',
                'short' => 'Gin, limão e bitters — o sour cítrico que abre a noite.',
                'tipo' => 'Coquetel alcoólico', 'base' => 'APTK Gin', 'ingredientes' => 'gin, limão, bitters e açúcar',
                'abv' => 30, 'sizes' => ['375 ml', '750 ml'],
                'servico' => 'copo baixo com gelo, 90 ml, com zest de Limão Siciliano',
                'price' => 179.90, 'sku' => 'FITZ-750', 'weight' => 1.2, 'featured' => true,
            ],
            [
                'slug' => 'cosmopolitan', 'name' => 'Cosmopolitan', 'cat' => 'classicos',
                'short' => 'Vodka, cranberry e cítricos: o ícone novaiorquino, pronto para servir.',
                'tipo' => 'Coquetel alcoólico', 'base' => 'Vodka VDK', 'ingredientes' => 'vodka, limão, cranberry e laranja',
                'abv' => 30, 'sizes' => ['375 ml', '750 ml'],
                'servico' => 'taça, 90 ml, com zest de limão',
                'price' => 169.90, 'sku' => 'COSMO-750', 'weight' => 1.2, 'featured' => true,
            ],
            [
                'slug' => 'moscow-mule', 'name' => 'Moscow Mule', 'cat' => 'classicos',
                'short' => 'Vodka e especiarias com a alma do gengibre, na caneca de cobre.',
                'tipo' => 'Coquetel alcoólico', 'base' => 'Vodka VDK', 'ingredientes' => 'vodka, mix de cítricos, açúcar e especiarias',
                'abv' => 30, 'sizes' => ['375 ml', '750 ml'],
                'servico' => 'caneca de cobre, 70 ml, com espuma de gengibre',
                'price' => 169.90, 'sku' => 'MULE-750', 'weight' => 1.2, 'featured' => false,
            ],
            [
                'slug' => 'limoncello', 'name' => 'Limoncello', 'cat' => 'classicos',
                'short' => 'Licor de limão siciliano, fresco e intenso, para o fim da refeição.',
                'tipo' => 'Licor fino de limão', 'base' => 'álcool de cereais', 'ingredientes' => 'álcool destilado, limão siciliano, água e açúcar',
                'abv' => 25, 'sizes' => ['375 ml', '750 ml'],
                'servico' => 'taça coupe, 80 ml',
                'price' => 149.90, 'sku' => 'LIMON-750', 'weight' => 1.2, 'featured' => false,
            ],

            // ---- AUTORAIS ----
            [
                'slug' => 'caju-amigo', 'name' => 'Caju Amigo', 'cat' => 'autorais',
                'short' => 'Caju, limão e vodka — o Brasil no copo, leve e refrescante.',
                'tipo' => 'Bebida alcoólica mista', 'base' => 'Vodka VDK', 'ingredientes' => 'vodka, limão, caju e açúcar',
                'abv' => 10, 'sizes' => ['375 ml', '750 ml'],
                'servico' => 'copo longo com muito gelo, 80 ml',
                'price' => 139.90, 'sku' => 'CAJU-750', 'weight' => 1.2, 'featured' => false,
            ],
            [
                'slug' => 'penicillin', 'name' => 'Penicillin', 'cat' => 'autorais',
                'short' => 'Bourbon, mel e gengibre defumado: o moderno que já virou clássico.',
                'tipo' => 'Coquetel alcoólico', 'base' => 'Buffalo Trace', 'ingredientes' => 'bourbon, limão, mel e gengibre',
                'abv' => 17, 'sizes' => ['375 ml'],
                'servico' => 'copo baixo com muito gelo, 80 ml',
                'price' => 199.90, 'sku' => 'PENI-375', 'weight' => 0.8, 'featured' => true,
            ],
            [
                'slug' => 'sur-lorange', 'name' => "Sur L'Orange", 'cat' => 'autorais',
                'short' => 'Cachaça envelhecida, laranja e amêndoas — releitura autoral da casa.',
                'tipo' => 'Coquetel alcoólico', 'base' => 'cachaça envelhecida', 'ingredientes' => 'cachaça, laranja e amêndoas',
                'abv' => 30, 'sizes' => ['375 ml', '750 ml'],
                'servico' => null,
                'price' => 209.90, 'sku' => 'SURL-750', 'weight' => 1.2, 'featured' => false,
            ],
            [
                'slug' => 'squadra-amendoas', 'name' => 'Squadra Amêndoas', 'cat' => 'autorais',
                'short' => 'Licor fino de amêndoas, aveludado e aromático.',
                'tipo' => 'Licor fino de amêndoas', 'base' => null, 'ingredientes' => null,
                'abv' => 25, 'sizes' => ['375 ml', '750 ml'],
                'servico' => null,
                'price' => 159.90, 'sku' => 'SQAM-750', 'weight' => 1.2, 'featured' => false,
            ],
            [
                'slug' => 'squadra-coco', 'name' => 'Squadra Coco', 'cat' => 'autorais',
                'short' => 'Licor fino de coco, cremoso e tropical.',
                'tipo' => 'Licor fino de coco', 'base' => null, 'ingredientes' => null,
                'abv' => 17, 'sizes' => ['375 ml', '750 ml'],
                'servico' => null,
                'price' => 149.90, 'sku' => 'SQCO-750', 'weight' => 1.2, 'featured' => false,
            ],

            // ---- BASES ----
            [
                'slug' => 'cachaca-prata', 'name' => 'Cachaça Prata', 'cat' => 'bases',
                'short' => 'Cachaça fresca de alambique, do caldo de cana ao copo.',
                'tipo' => 'Cachaça', 'base' => null, 'ingredientes' => 'mosto fermentado do caldo de cana de açúcar',
                'abv' => 40, 'sizes' => ['750 ml'],
                'servico' => null,
                'price' => 129.90, 'sku' => 'CACP-750', 'weight' => 1.2, 'featured' => false,
            ],
            [
                'slug' => 'cachaca-ouro', 'name' => 'Cachaça Ouro', 'cat' => 'bases',
                'short' => 'Cachaça descansada em barris de carvalho, redonda e dourada.',
                'tipo' => 'Cachaça envelhecida', 'base' => null, 'ingredientes' => 'mosto fermentado, armazenado em barris de carvalho',
                'abv' => 40, 'sizes' => ['750 ml'],
                'servico' => null,
                'price' => 159.90, 'sku' => 'CACO-750', 'weight' => 1.2, 'featured' => false,
            ],
            [
                'slug' => 'neutral-gin', 'name' => 'Neutral Gin', 'cat' => 'bases',
                'short' => 'Gin autoral com menta, limão, alcaçuz e lavanda.',
                'tipo' => 'Gin', 'base' => null, 'ingredientes' => 'menta, limão, alcaçuz e lavanda',
                'abv' => 42, 'sizes' => ['750 ml'],
                'servico' => null,
                'price' => 219.90, 'sku' => 'NEUT-750', 'weight' => 1.2, 'featured' => false,
            ],
            [
                'slug' => 'vodka-vdk', 'name' => 'Vodka VDK', 'cat' => 'bases',
                'short' => 'Vodka de origem agrícola, limpa e versátil — a base da casa.',
                'tipo' => 'Vodka', 'base' => null, 'ingredientes' => 'álcool etílico potável de origem agrícola e água',
                'abv' => 40, 'sizes' => ['750 ml'],
                'servico' => null,
                'price' => 169.90, 'sku' => 'VDK-750', 'weight' => 1.2, 'featured' => false,
            ],
        ];

        foreach ($products as $data) {
            $product = Product::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'category_id'       => $catId[$data['cat']],
                    'name'              => $data['name'],
                    'short_description' => $data['short'],
                    'base'              => $data['base'],
                    'abv'               => $data['abv'],
                    'sizes'             => $data['sizes'],
                    'size_prices'       => $this->deriveSizePrices($data['sizes'], (float) $data['price']),
                    'description'       => $this->buildDescription($data),
                    'price'             => $data['price'],
                    'sku'               => $data['sku'],
                    'stock_qty'         => 30,
                    'weight'            => $data['weight'],
                    'active'            => true,
                    'featured'          => $data['featured'],
                ],
            );

            // Imagem principal — a garrafa
            ProductImage::updateOrCreate(
                ['product_id' => $product->id, 'path' => "products/{$data['slug']}.jpg"],
                ['alt' => "{$data['name']} — garrafa APTK", 'sort_order' => 0, 'is_primary' => true],
            );

            // Imagem secundária — o drink servido
            ProductImage::updateOrCreate(
                ['product_id' => $product->id, 'path' => "products/{$data['slug']}-serve.jpg"],
                ['alt' => "{$data['name']} — serviço perfeito", 'sort_order' => 1, 'is_primary' => false],
            );
        }
    }

    /**
     * Preços por volume SUGERIDOS a partir do preço da 750 ml
     * (375 ml ≈ 65% · 750 ml = 100%). Leva 02: só 375/750 à venda.
     * São ponto de partida — ajustar produto a produto no admin.
     */
    private function deriveSizePrices(array $sizes, float $price): ?array
    {
        if (count($sizes) < 2) {
            return null; // volume único usa o price base
        }

        $ratio = ['375 ml' => 0.65, '750 ml' => 1.0];

        $out = [];
        foreach ($sizes as $size) {
            $out[$size] = round($price * ($ratio[$size] ?? 1.0), 2);
        }

        return $out;
    }

    /** Descrição editorial: tipo + ingredientes + serviço (base, teor e tamanhos viram specs). */
    private function buildDescription(array $d): string
    {
        $parts = [$d['tipo'].'.'];

        if (! empty($d['ingredientes'])) {
            $parts[] = 'Ingredientes: '.$d['ingredientes'].'.';
        }
        if (! empty($d['servico'])) {
            $parts[] = 'Serviço perfeito: '.$d['servico'].'.';
        }

        return implode(' ', $parts);
    }
}
