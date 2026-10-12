<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Produto sem foto (leva 08): placeholder em todo lugar, nada de <img>
 * quebrado nem imagem antiga; o carrossel da home só leva produto com foto.
 */
class ProdutoPlaceholderTest extends TestCase
{
    use RefreshDatabase;

    protected function produto(string $slug, ?string $foto = null): Product
    {
        $p = Product::create([
            'name'        => ucfirst(str_replace('-', ' ', $slug)),
            'slug'        => $slug,
            'price'       => 165.00,
            'sizes'       => ['375 ml'],
            'size_prices' => ['375 ml' => 165.00],
            'stock_qty'   => 30,
            'active'      => true,
        ]);

        if ($foto) {
            ProductImage::create(['product_id' => $p->id, 'path' => $foto, 'is_primary' => true, 'sort_order' => 0]);
        }

        return $p;
    }

    public function test_catalogo_e_pagina_do_produto_mostram_o_placeholder(): void
    {
        $this->produto('canarinha');

        $this->get('/loja')
            ->assertOk()
            ->assertSee('Produto ainda sem foto')
            ->assertDontSee('/storage/', false);

        $this->get('/produto/canarinha')
            ->assertOk()
            ->assertSee('Produto ainda sem foto')
            ->assertDontSee('/storage/', false)
            ->assertDontSee('class="pdp-thumbs"', false); // sem miniaturas falsas
    }

    public function test_produto_com_foto_mostra_a_foto(): void
    {
        $this->produto('boulevardier', 'products/boulevardier-375ml-frente.jpg');

        $this->get('/loja')
            ->assertOk()
            ->assertSee('/storage/products/boulevardier-375ml-frente.jpg', false)
            ->assertDontSee('Produto ainda sem foto');
    }

    public function test_carrossel_da_home_so_leva_produto_com_foto(): void
    {
        $this->produto('boulevardier', 'products/boulevardier-375ml-frente.jpg');
        $this->produto('canarinha');

        $this->get('/')
            ->assertOk()
            ->assertSee('Boulevardier')
            ->assertDontSee('Canarinha')
            ->assertDontSee('Produto ainda sem foto');
    }

    public function test_carrinho_mostra_foto_ou_placeholder(): void
    {
        $com = $this->produto('boulevardier', 'products/boulevardier-375ml-frente.jpg');
        $sem = $this->produto('canarinha');
        app(CartService::class)->add($com->id, 1, '375 ml');
        app(CartService::class)->add($sem->id, 1, '375 ml');

        $this->get('/carrinho')
            ->assertOk()
            ->assertSee('/storage/products/boulevardier-375ml-frente.jpg', false)
            ->assertSee('aria-label="Produto ainda sem foto"', false);
    }
}
