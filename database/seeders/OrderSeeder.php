<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();
        if ($products->isEmpty()) {
            $this->command->warn('Sem produtos — rode o ProductSeeder antes do OrderSeeder.');
            return;
        }

        $customers = User::where('role', 'customer')->get();
        if ($customers->isEmpty()) {
            $this->command->warn('Sem clientes — rode o CustomerSeeder antes do OrderSeeder.');
            return;
        }

        // Demonstração: zera os pedidos para um conjunto consistente a cada seed.
        OrderItem::query()->delete();
        Order::query()->delete();

        // Pesos: paid/delivered mais frequentes; todos os status aparecem.
        $statusPool = ['pending', 'paid', 'paid', 'shipped', 'delivered', 'delivered', 'cancelled'];
        $methods    = ['pix', 'cartao', 'boleto'];
        $shipPool   = [0, 0, 0, 19.90, 29.90];
        $cidades    = [
            ['São Paulo', 'SP'], ['Campinas', 'SP'], ['Rio de Janeiro', 'RJ'],
            ['Belo Horizonte', 'MG'], ['Curitiba', 'PR'], ['Porto Alegre', 'RS'],
        ];

        foreach ($customers as $customer) {
            $numOrders = rand(0, 4); // alguns clientes ficam sem pedido

            for ($n = 0; $n < $numOrders; $n++) {
                // ~40% no mês atual, resto nos últimos 90 dias.
                $date = rand(1, 100) <= 40
                    ? now()->startOfMonth()->addDays(rand(0, max(0, (int) now()->day - 1)))->setTime(rand(8, 20), rand(0, 59))
                    : now()->subDays(rand(7, 90))->setTime(rand(8, 20), rand(0, 59));

                $status    = $statusPool[array_rand($statusPool)];
                $method    = $methods[array_rand($methods)];
                $payStatus = match ($status) {
                    'cancelled' => 'failed',
                    'pending'   => 'pending',
                    default     => 'paid',
                };

                // Itens
                $chosen   = $products->random(rand(1, 3));
                $subtotal = 0.0;
                $items    = [];
                foreach ($chosen as $product) {
                    $qty  = rand(1, 3);
                    $line = (float) $product->price * $qty;
                    $subtotal += $line;
                    $items[] = [
                        'product_id'   => $product->id,
                        'product_name' => $product->name,
                        'product_sku'  => $product->sku,
                        'qty'          => $qty,
                        'unit_price'   => $product->price,
                        'total_price'  => $line,
                    ];
                }

                $shipping = $shipPool[array_rand($shipPool)];
                $discount = rand(1, 100) <= 20 ? round($subtotal * 0.10, 2) : 0.0;
                $total    = $subtotal + $shipping - $discount;

                [$cidade, $uf] = $cidades[array_rand($cidades)];
                $notes = "Entrega:\n{$customer->name}\n"
                    .'Rua Exemplo, '.rand(10, 999)." — Bairro Centro\n"
                    ."{$cidade}/{$uf} — CEP ".sprintf('%05d-%03d', rand(1000, 99999), rand(0, 999));

                $order = Order::create([
                    'user_id'         => $customer->id,
                    'status'          => $status,
                    'subtotal'        => $subtotal,
                    'discount'        => $discount,
                    'shipping_cost'   => $shipping,
                    'total'           => $total,
                    'payment_method'  => $method,
                    'payment_status'  => $payStatus,
                    'shipping_method' => $shipping > 0 ? 'sedex' : 'ifood',
                    'notes'           => $notes,
                ]);

                // Backdate consistente (created + updated).
                $order->created_at = $date;
                $order->updated_at = $date;
                $order->save();

                foreach ($items as $it) {
                    $order->items()->create($it);
                }
            }
        }

        $this->command->info('Pedidos de demonstração: '.Order::count());
    }
}
