<?php

namespace App\Services;

use App\Exceptions\OutOfStockException;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
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
            // 0) Revalida o estoque por volume DENTRO da transação, com lock
            //    de linha. Validar antes dela abriria a corrida clássica: duas
            //    compras simultâneas do último item leriam "tem 1" e as duas
            //    passariam. Com o lock, a segunda espera e relê o estoque já
            //    debitado pela primeira.
            //    Volume não rastreado (saldo null) segue sem checagem — o
            //    fallback conservador da fase 1.5.
            $produtos = Product::whereIn('id', $items->pluck('product.id')->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $row) {
                $produto = $produtos->get($row->product->id);
                $rotulo = '"'.$row->product->name.($row->size ? " ({$row->size})" : '').'"';

                if (! $produto) {
                    throw new OutOfStockException("{$rotulo} saiu do catálogo — remova o item do carrinho para continuar.");
                }

                $saldo = $produto->stockForSize($row->size);

                if ($saldo !== null && $saldo <= 0) {
                    throw new OutOfStockException("{$rotulo} esgotou enquanto você fechava o pedido — remova o item do carrinho para continuar.");
                }

                if ($saldo !== null && $saldo < $row->qty) {
                    throw new OutOfStockException("{$rotulo} tem só {$saldo} unidade(s) em estoque e o carrinho pede {$row->qty} — ajuste a quantidade para continuar.");
                }
            }

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

            // CPF: o checkout valida mas o dado morria aqui. Grava no usuário
            // (só se ainda não tiver — não sobrescreve cadastro existente).
            // B2CCadastraClientes (Microvix) exige doc_cliente.
            $cpf = isset($data['cpf'])
                ? preg_replace('/\D+/', '', $data['cpf'])
                : null;

            if ($cpf && blank($user->cpf)) {
                $user->forceFill(['cpf' => $cpf])->save();
            }

            // 4) Pedido — endereço congelado em colunas ship_* (snapshot manda;
            //    address_id é referência fraca, o cliente pode editar/apagar
            //    o endereço da conta depois).
            $order = Order::create([
                'user_id'           => $user->id,
                'address_id'        => $address->id,
                'ship_name'         => $address->name,
                'ship_cpf'          => $cpf,
                'ship_phone'        => $data['phone'] ?? null,
                'ship_zipcode'      => $address->zipcode,
                'ship_street'       => $address->street,
                'ship_number'       => $address->number,
                'ship_complement'   => $address->complement,
                'ship_neighborhood' => $address->neighborhood,
                'ship_city'         => $address->city,
                'ship_state'        => $address->state,
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
                    'size'         => $row->size,
                    'qty'          => $row->qty,
                    'unit_price'   => $row->unit_price,
                    'total_price'  => $row->subtotal,
                ]);

                // Baixa no MESMO model lockado da revalidação, nunca no da
                // sessão do carrinho (que pode estar defasado).
                $product = $produtos[$row->product->id];

                if (($saldo = $product->stockForSize($row->size)) !== null) {
                    $porVolume = $product->size_stock;
                    $porVolume[$row->size] = max(0, $saldo - (int) $row->qty);
                    $product->size_stock = $porVolume;
                }

                // O agregado continua baixando como sempre (fallback e vitrine).
                $product->stock_qty = max(0, (int) $product->stock_qty - (int) $row->qty);
                $product->save();
            }

            // 6) Esvazia o carrinho (só chega aqui se tudo deu certo).
            $this->cart->clear();

            return $order;
        });
    }

    /**
     * Cancela um pedido e devolve o estoque dos itens.
     * Usado quando o cartão é recusado no checkout e quando um Pix/boleto
     * expira (webhook Appmax). Idempotente: não devolve estoque duas vezes.
     */
    public function cancelAndRestoreStock(Order $order, string $paymentStatus = 'failed'): void
    {
        if ($order->status === 'cancelled') {
            return;
        }

        DB::transaction(function () use ($order, $paymentStatus) {
            foreach ($order->items as $item) {
                if (! $item->product) {
                    continue;
                }

                // Devolve também o saldo do volume, se o item era rastreado.
                $product = $item->product;
                if (($saldo = $product->stockForSize($item->size)) !== null) {
                    $porVolume = $product->size_stock;
                    $porVolume[$item->size] = $saldo + (int) $item->qty;
                    $product->size_stock = $porVolume;
                    $product->save();
                }

                $product->increment('stock_qty', (int) $item->qty);
            }

            $order->update([
                'status'         => 'cancelled',
                'payment_status' => $paymentStatus,
            ]);
        });
    }

    /**
     * Devolve os itens de um pedido ao carrinho da sessão (produto + volume),
     * para o cliente tentar pagar de novo sem remontar a sacola.
     */
    public function restoreCartFromOrder(Order $order): void
    {
        foreach ($order->items as $item) {
            $this->cart->add((int) $item->product_id, (int) $item->qty, $item->size);
        }
    }
}
