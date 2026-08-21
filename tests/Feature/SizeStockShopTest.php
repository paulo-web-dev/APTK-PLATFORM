<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Estoque por volume na vitrine e no carrinho (Microvix fase 1.5).
 * Regra de ouro: size_stock null = comportamento EXATAMENTE como antes
 * (stock_qty placeholder, nada esgota). Só produto conciliado muda.
 */
class SizeStockShopTest extends TestCase
{
    use RefreshDatabase;

    /** Produto conciliado: 375 ml com 3 unidades, 750 ml zerado. */
    protected function conciliado(): Product
    {
        return Product::create([
            'name'        => 'Negroni Clássico',
            'slug'        => 'negroni-classico',
            'sku'         => 'NEG-750',
            'price'       => 149.90,
            'sizes'       => ['375 ml', '750 ml'],
            'size_prices' => ['375 ml' => 123.44, '750 ml' => 189.90],
            'size_stock'  => ['375 ml' => 3, '750 ml' => 0],
            'stock_qty'   => 30,
            'active'      => true,
        ]);
    }

    /** Produto como TODOS estão hoje: size_stock null, stock_qty placeholder. */
    protected function naoConciliado(): Product
    {
        return Product::create([
            'name'      => 'Fitzgerald',
            'slug'      => 'fitzgerald',
            'sku'       => 'FITZ-750',
            'price'     => 139.90,
            'sizes'     => ['375 ml', '750 ml'],
            'stock_qty' => 30,
            'active'    => true,
        ]);
    }

    /* ---------------- Model: fallback conservador ---------------- */

    public function test_produto_sem_size_stock_se_comporta_exatamente_como_hoje(): void
    {
        $p = $this->naoConciliado();

        $this->assertFalse($p->tracksSizeStock());
        $this->assertNull($p->stockForSize('375 ml'));
        $this->assertFalse($p->isSizeOutOfStock('375 ml'));
        $this->assertFalse($p->isSoldOut()); // stock_qty 30, como sempre
    }

    public function test_volume_sem_entrada_em_size_stock_nao_esgota(): void
    {
        $p = $this->conciliado();
        $p->size_stock = ['375 ml' => 0]; // 750 ml sem entrada: não rastreado

        $this->assertTrue($p->isSizeOutOfStock('375 ml'));
        $this->assertFalse($p->isSizeOutOfStock('750 ml'));
        $this->assertFalse($p->isSoldOut()); // o volume não rastreado segura a vitrine
    }

    public function test_esgotado_so_com_todos_os_volumes_rastreados_zerados(): void
    {
        $p = $this->conciliado();
        $this->assertFalse($p->isSoldOut()); // 375 ml tem 3

        $p->size_stock = ['375 ml' => 0, '750 ml' => 0];
        $this->assertTrue($p->isSoldOut()); // mesmo com stock_qty 30
    }

    /* ---------------- Página de produto ---------------- */

    public function test_volume_zerado_aparece_desabilitado_e_marcado_nao_some(): void
    {
        $this->conciliado();

        $resp = $this->get('/produto/negroni-classico')->assertOk();

        $resp->assertSee('750 ml — esgotado');
        $resp->assertSee('Adicionar ao carrinho'); // 375 ml ainda compra
        // O padrão (750 ml, o maior) esgotou → pré-seleciona o 375 ml.
        $resp->assertSee('id="pdpSizeInput" value="375 ml"', false);
        $resp->assertSee('123,44'); // preço exibido acompanha o volume selecionado
    }

    public function test_produto_com_todos_os_volumes_zerados_mostra_esgotado(): void
    {
        $p = $this->conciliado();
        $p->update(['size_stock' => ['375 ml' => 0, '750 ml' => 0]]);

        $resp = $this->get('/produto/negroni-classico')->assertOk();

        $resp->assertSee('Esgotado');
        $resp->assertDontSee('Adicionar ao carrinho');
    }

    public function test_pagina_de_produto_nao_conciliado_nao_muda(): void
    {
        $this->naoConciliado();

        $resp = $this->get('/produto/fitzgerald')->assertOk();

        $resp->assertSee('Em estoque');
        $resp->assertSee('Adicionar ao carrinho');
        $resp->assertDontSee('— esgotado'); // nenhum chip marcado
        $resp->assertDontSee('class="size-chip-btn is-active is-out"', false);
    }

    /* ---------------- Listagem ---------------- */

    public function test_listagem_mostra_esgotado_com_selo_sem_sumir(): void
    {
        $p = $this->conciliado();
        $p->update(['size_stock' => ['375 ml' => 0, '750 ml' => 0]]);
        $this->naoConciliado();

        $resp = $this->get('/loja')->assertOk();

        $resp->assertSee('Negroni Clássico'); // continua na vitrine
        $resp->assertSee('prod-out');         // com selo
        $resp->assertSee('Fitzgerald');
    }

    /* ---------------- Carrinho ---------------- */

    public function test_carrinho_nao_deixa_adicionar_acima_do_saldo_do_volume(): void
    {
        $p = $this->conciliado();

        $this->post('/carrinho/adicionar', ['product_id' => $p->id, 'qty' => 2, 'size' => '375 ml'])
            ->assertSessionMissing('stock_error');

        // 2 no carrinho + 2 > 3: recusa dizendo o item, o saldo e o que já tem.
        $resp = $this->post('/carrinho/adicionar', ['product_id' => $p->id, 'qty' => 2, 'size' => '375 ml']);

        $resp->assertSessionHas('stock_error', function (string $msg) {
            return str_contains($msg, 'Negroni Clássico (375 ml)')
                && str_contains($msg, '3 unidade')
                && str_contains($msg, 'já tem 2');
        });
    }

    public function test_carrinho_recusa_volume_esgotado(): void
    {
        $p = $this->conciliado();

        $this->post('/carrinho/adicionar', ['product_id' => $p->id, 'qty' => 1, 'size' => '750 ml'])
            ->assertSessionHas('stock_error', fn (string $msg) => str_contains($msg, 'esgotado'));
    }

    public function test_atualizar_quantidade_revalida_contra_o_saldo(): void
    {
        $p = $this->conciliado();
        $this->post('/carrinho/adicionar', ['product_id' => $p->id, 'qty' => 2, 'size' => '375 ml']);

        $this->patch('/carrinho/atualizar', ['key' => "{$p->id}:375 ml", 'qty' => 10])
            ->assertSessionHas('stock_error', function (string $msg) {
                return str_contains($msg, '3 unidade') && str_contains($msg, 'mantida em 2');
            });

        // Dentro do saldo passa.
        $this->patch('/carrinho/atualizar', ['key' => "{$p->id}:375 ml", 'qty' => 3])
            ->assertSessionMissing('stock_error');
    }

    public function test_produto_nao_conciliado_segue_sem_limite_no_carrinho(): void
    {
        $p = $this->naoConciliado();

        // 999 unidades: hoje nada limita, e nada pode passar a limitar.
        $this->post('/carrinho/adicionar', ['product_id' => $p->id, 'qty' => 999, 'size' => '750 ml'])
            ->assertSessionMissing('stock_error');
    }
}
