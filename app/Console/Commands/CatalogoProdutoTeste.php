<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Produto de R$ 1,00 para testar pagamento real em produção (Pix, cartão,
 * estorno) sem mexer no catálogo de verdade.
 *
 * Idempotente: cria na primeira vez, reativa e repõe nome/preço/volume nas
 * seguintes. --remover só desativa (pedidos de teste continuam apontando
 * pro produto). Sem foto: a vitrine mostra o placeholder.
 *
 * O catalogo:importar-planilha não toca nele: o slug está fora da planilha,
 * fora da lista de desativação, e o importador pula o grupo explicitamente.
 */
class CatalogoProdutoTeste extends Command
{
    public const SLUG = 'teste';

    protected $signature = 'catalogo:produto-teste
        {--remover : Desativa o produto de teste}';

    protected $description = 'Cria/reativa (ou desativa, com --remover) o produto "Teste" de R$ 1,00 para teste de pagamento';

    protected const VOLUME = '100 ml';
    protected const PRECO = 1.00;
    protected const CATEGORIA = 'bases';

    public function handle(): int
    {
        $produto = Product::where('slug', self::SLUG)->first();

        if ($this->option('remover')) {
            if (! $produto) {
                $this->info('Produto de teste não existe, nada a desativar.');

                return self::SUCCESS;
            }

            $produto->update(['active' => false]);
            Log::info('catalogo:produto-teste — desativado', ['product_id' => $produto->id]);
            $this->info("Produto de teste #{$produto->id} desativado.");

            return self::SUCCESS;
        }

        $categoria = ProductCategory::where('slug', self::CATEGORIA)->first();
        if (! $categoria) {
            $this->warn("Categoria '".self::CATEGORIA."' não existe: produto fica sem categoria.");
        }

        $novo = ! $produto;
        $produto ??= new Product(['slug' => self::SLUG]);

        $produto->fill([
            'name'              => 'Teste',
            'sku'               => 'TESTE',
            'category_id'       => $categoria?->id,
            'short_description' => 'Produto de teste de pagamento. Não enviar.',
            'sizes'             => [self::VOLUME],
            'size_prices'       => [self::VOLUME => self::PRECO],
            'size_stock'        => null, // não rastreado: nunca esgota
            'price'             => self::PRECO,
            'stock_qty'         => max(100, (int) $produto->stock_qty),
            'active'            => true,
            'featured'          => false,
        ])->save();

        Log::info('catalogo:produto-teste — '.($novo ? 'criado' : 'reativado'), ['product_id' => $produto->id]);
        $this->info(($novo ? 'Criado' : 'Reativado')." o produto de teste #{$produto->id}: /produto/".self::SLUG
            .', '.self::VOLUME.' a R$ '.number_format(self::PRECO, 2, ',', '.').'.');

        return self::SUCCESS;
    }
}
