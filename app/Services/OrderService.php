<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    public function __construct(protected CartService $cart)
    {
    }

    /**
     * Cria um pedido a partir do carrinho atual, dentro de uma transação.
     *
     * @param  array<string, mixed>  $data  dados já validados (endereço + pagamento)
     */
    public function placeFromCart(User $user, array $data): Order
    {
        $items = $this->cart->items();

        if ($items->isEmpty()) {
            throw new RuntimeException('Carrinho vazio.');
        }

        return DB::transaction(function () use ($user, $data, $items) {
            // 1) Endereço de entrega, salvo na conta do usuário.
            $address = Address::create([
                'user_id'      => $user->id,
                'label'        => 'Entrega',
                'name'         => $data['name'],
                'street'       => $data['street'],
                'number'       => $data['number'],
                'complement'   => $data['complement'] ?? null,
                'neighborhood' => $data['neighborhood'],
                'city'         => $data['city'],
                'state'        => strtoupper($data['state']),
                'zipcode'      => $data['zipcode'],
                'is_default'   => $user->addresses()->count() === 0,
            ]);

            // 2) Totais.
            $subtotal = (float) $items->sum('subtotal');
            $discount = 0.0;
            $shipping = 0.0; // frete fica para fase posterior
            $total    = $subtotal + $shipping - $discount;

            // 3) Snapshot legível do endereço em notes
            //    (a tabela orders não tem colunas de endereço).
            $addressLine = sprintf(
                '%s, %s%s — %s, %s/%s — CEP %s',
                $address->street,
                $address->number,
                $address->complement ? ' ('.$address->complement.')' : '',
                $address->neighborhood,
                $address->city,
                $address->state,
                $address->zipcode,
            );

            $notes = "Entregar a: {$address->name}\nEndereço: {$addressLine}";
            if (! empty($data['notes'])) {
                $notes .= "\nObs. do cliente: ".$data['notes'];
            }

            // 4) Pedido.
            $order = Order::create([
                'user_id'         => $user->id,
                'status'          => 'pending',
                'subtotal'        => $subtotal,
                'discount'        => $discount,
                'shipping_cost'   => $shipping,
                'total'           => $total,
                'payment_method'  => $data['payment_method'],
                'payment_status'  => 'pending',
                'shipping_method' => $data['shipping_method'] ?? null,
                'notes'           => $notes,
            ]);

            // 5) Itens (snapshot de nome/sku/preço) + baixa de estoque.
            foreach ($items as $row) {
                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $row->product->id,
                    'product_name' => $row->product->name,
                    'product_sku'  => $row->product->sku,
                    'qty'          => $row->qty,
                    'unit_price'   => $row->product->price,
                    'total_price'  => $row->subtotal,
                ]);

                $product = $row->product;
                $product->stock_qty = max(0, (int) $product->stock_qty - (int) $row->qty);
                $product->save();
            }

            // 6) Esvazia o carrinho (só chega aqui se tudo deu certo).
            $this->cart->clear();

            return $order;
        });
    }
}
