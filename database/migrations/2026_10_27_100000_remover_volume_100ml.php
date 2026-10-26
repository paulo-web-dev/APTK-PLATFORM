<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leva 15: o volume de 100 ml sai da venda em todos os produtos.
 *
 * Tira "100 ml" de sizes, size_prices e size_stock. O volume padrão é
 * calculado (Product::defaultSize = o maior de sizes), então se ajusta
 * sozinho. Produto que fica sem nenhum volume vira inativo e com preço 0:
 * sem isso o preço base (o do 100 ml) faria o produtos:aplicar-fotos ou o
 * admin reativá-lo vendendo sem volume. Pedidos não mudam.
 *
 * O produto "Teste" de pagamento (catalogo:produto-teste, só 100 ml) fica
 * de fora. O valor bruto das colunas vai pra backup_volume_100ml e o down
 * restaura exatamente o que tinha.
 */
return new class extends Migration
{
    private const VOLUME = '100 ml';

    private const IGNORAR = ['teste'];

    public function up(): void
    {
        Schema::create('backup_volume_100ml', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->primary();
            $table->text('sizes')->nullable();
            $table->text('size_prices')->nullable();
            $table->text('size_stock')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('active');
        });

        $produtos = DB::table('products')
            ->whereNotIn('slug', self::IGNORAR)
            ->get(['id', 'sizes', 'size_prices', 'size_stock', 'price', 'active']);

        foreach ($produtos as $p) {
            $sizes = json_decode($p->sizes ?? 'null', true);
            $precos = json_decode($p->size_prices ?? 'null', true);
            $estoque = json_decode($p->size_stock ?? 'null', true);

            $tem = (is_array($sizes) && in_array(self::VOLUME, $sizes, true))
                || (is_array($precos) && array_key_exists(self::VOLUME, $precos))
                || (is_array($estoque) && array_key_exists(self::VOLUME, $estoque));
            if (! $tem) {
                continue;
            }

            DB::table('backup_volume_100ml')->insert([
                'product_id'  => $p->id,
                'sizes'       => $p->sizes,
                'size_prices' => $p->size_prices,
                'size_stock'  => $p->size_stock,
                'price'       => $p->price,
                'active'      => (bool) $p->active,
            ]);

            $sizes = is_array($sizes) ? array_values(array_diff($sizes, [self::VOLUME])) : null;
            if (is_array($precos)) {
                unset($precos[self::VOLUME]);
            }
            if (is_array($estoque)) {
                unset($estoque[self::VOLUME]);
            }

            $novo = [
                'sizes'       => $sizes ? json_encode($sizes) : null,
                'size_prices' => $precos ? json_encode($precos) : null,
                'size_stock'  => $estoque ? json_encode($estoque) : null,
            ];
            if (! $sizes) {
                $novo += ['active' => false, 'price' => 0];
            }

            DB::table('products')->where('id', $p->id)->update($novo);
        }
    }

    public function down(): void
    {
        foreach (DB::table('backup_volume_100ml')->get() as $b) {
            DB::table('products')->where('id', $b->product_id)->update([
                'sizes'       => $b->sizes,
                'size_prices' => $b->size_prices,
                'size_stock'  => $b->size_stock,
                'price'       => $b->price,
                'active'      => (bool) $b->active,
            ]);
        }

        Schema::dropIfExists('backup_volume_100ml');
    }
};
