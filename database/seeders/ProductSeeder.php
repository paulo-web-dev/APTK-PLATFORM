<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Drinks prontos',       'slug' => 'drinks-prontos'],
            ['name' => 'Gin, Vodka & Whisky',  'slug' => 'destilados'],
            ['name' => 'Kits & presentes',     'slug' => 'kits'],
            ['name' => 'Small batches',        'slug' => 'small-batches'],
        ];

        $catIds = [];
        foreach ($categories as $i => $cat) {
            $model = ProductCategory::updateOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name'], 'active' => true, 'sort_order' => $i],
            );
            $catIds[$cat['slug']] = $model->id;
        }

        // [nome, categoria(slug), preço, sku]
        $products = [
            ['Negroni Clássico',     'drinks-prontos', 189.90, 'NEG-750'],
            ['Gin Tônica APTK',      'drinks-prontos', 159.90, 'GT-750'],
            ['Gin Autoral APTK',     'destilados',     219.90, 'GIN-750'],
            ['Vodka Premium APTK',   'destilados',     199.90, 'VOD-750'],
            ['Whisky Single Malt',   'destilados',     389.90, 'WHI-750'],
            ['Kit Coquetelaria',     'kits',           279.90, 'KIT-COC'],
            ['Kit Presente Negroni', 'kits',           249.90, 'KIT-NEG'],
            ['Small Batch #014',     'small-batches',  329.90, 'SB-014'],
        ];

        foreach ($products as [$name, $catSlug, $price, $sku]) {
            Product::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'category_id'       => $catIds[$catSlug],
                    'name'              => $name,
                    'short_description' => 'Produção artesanal APTK em pequenos lotes.',
                    'price'             => $price,
                    'sku'               => $sku,
                    'stock_qty'         => 25,
                    'active'            => true,
                    'featured'          => in_array($sku, ['NEG-750', 'SB-014'], true),
                ],
            );
        }
    }
}
