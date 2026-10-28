<?php

namespace App\Services;

use App\Exceptions\OutOfStockException;
use App\Jobs\EnviarPedidoMicrovix;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class OrderService
{
    public function __construct(
        protected CartService $cart,
        protected FreteService $frete,
        protected CustomService $custom,
    ) {
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

        // Volume que saiu do produto com o item no carrinho (CartService::items).
        if ($fora = $items->firstWhere('disponivel', false)) {
            throw new OutOfStockException("O volume de {$fora->size} não está mais disponível. "
                ."Remova \"{$fora->nome} ({$fora->size})\" do carrinho para continuar.");
        }

        // Garrafa custom: revalida a personalização (a config pode ter mudado
        // com o item no carrinho). O preço já vem recalculado da config pelo
        // CartService::items(). Recusada → volta pro carrinho com o motivo.
        foreach ($items->whereNotNull('personalizacao') as $row) {
            try {
                $p = $this->custom->validar($row->personalizacao);
                if ($p['bebida'] !== $row->product->slug) {
                    throw ValidationException::withMessages(['bebida' => 'Essa bebida não está disponível para personalização.']);
                }
            } catch (ValidationException $e) {
                throw new OutOfStockException('A garrafa personalizada de "'.$row->nome.'" não pode ser confirmada: '
                    .collect($e->errors())->flatten()->first().' Remova o item do carrinho para continuar.');
            }
        }

        // Frete recalculado AQUI, do CEP de entrega e da opção escolhida — o
        // valor que o front mostrou nunca entra. O checkout exige a opção;
        // sem ela (chamadas internas/testes antigos) o frete fica zerado.
        // Fora da transação: CEP/opção inválidos lançam ValidationException
        // antes de qualquer gravação.
        $frete = filled($data['shipping_method'] ?? null)
            ? $this->frete->opcao($items, (string) $data['zipcode'], $data['shipping_method'])
            : null;

        $order = DB::transaction(function () use ($user, $data, $items, $frete) {
            // 0) Revalida o estoque por volume DENTRO da transação, com lock
            //    de linha. Validar antes dela abriria a corrida clássica: duas
            //    compras simultâneas do último item leriam "tem 1" e as duas
            //    passariam. Com o lock, a segunda espera e relê o estoque já
            //    debitado pela primeira.
            //    Volume não rastreado (saldo null) segue sem checagem — o
            //    fallback conservador da fase 1.5.
            //    Ordem de lock FIXA (id ascendente): dois pedidos com os
            //    mesmos produtos em ordens diferentes travam na mesma ordem
            //    e não se cruzam em deadlock. O IN numa instrução única já
            //    varre o PK em ordem no InnoDB; o orderBy torna a invariante
            //    explícita e sobrevive a refactor para locks por linha.
            $produtos = Product::whereIn('id', $items->pluck('product.id')->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Por produto + volume, somando as linhas: a 375 normal e as
            // garrafas custom da mesma bebida dividem o mesmo estoque.
            $porVolume = $items->groupBy(fn ($row) => $row->product->id.':'.$row->size)
                ->map(fn ($linhas) => (object) [
                    'product' => $linhas->first()->product,
                    'size'    => $linhas->first()->size,
                    'qty'     => (int) $linhas->sum('qty'),
                ]);

            foreach ($porVolume as $row) {
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
            $shipping = $frete ? (float) $frete['valor'] : 0.0;
            $total    = round($subtotal + $shipping - $discount, 2);

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
                'shipping_method' => $frete['codigo'] ?? null,
                'shipping_days'   => $frete['prazo_dias'] ?? null,
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
                    'personalizacao' => $row->personalizacao ? $this->custom->paraPedido($row->personalizacao) : null,
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

        // Fase 2 Microvix: reserva de estoque no ERP (somente_reserva=1).
        // FORA do closure da transação (a fila database grava o job na MESMA
        // conexão — dentro, ele participaria da transação) E com afterCommit
        // (se alguém reembrulhar isto numa transação maior, o job só sai no
        // commit real). Falha ao enfileirar jamais derruba o pedido.
        // Integração desligada: nem enfileira (leva 08).
        try {
            if (EnviarPedidoMicrovix::integracaoLigada()) {
                EnviarPedidoMicrovix::dispatch($order->id, 'reservar')->afterCommit();
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $order;
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

        // Fase 2 Microvix: libera a reserva no ERP (B2CCancelaPedido). Ponto
        // ÚNICO de cancelamento — cobre webhook (expirado/recusado), a
        // compensação do checkout e o comando cancelar-cartao-preso. FORA da
        // transação (fila database grava na mesma conexão) + afterCommit; e
        // falha ao enfileirar nunca desfaz o cancelamento local.
        try {
            if (EnviarPedidoMicrovix::integracaoLigada()) {
                EnviarPedidoMicrovix::dispatch($order->id, 'cancelar')->afterCommit();
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Pagamento confirmado pela Appmax num pedido ainda aberto (webhook ou
     * reconciliação): pending → processing, pago. Idempotente. Enfileira a
     * confirmação no ERP e o aviso de pagamento confirmado à equipe (fora de
     * transação; falha nunca derruba o pagamento).
     */
    public function aprovarPagamento(Order $order): void
    {
        if ($order->isPaid()) {
            return;
        }

        $order->update([
            'payment_status' => 'paid',
            'status'         => $order->status === 'pending' ? 'processing' : $order->status,
            'paid_at'        => $order->paid_at ?? now(),
        ]);

        try {
            if (EnviarPedidoMicrovix::integracaoLigada()) {
                EnviarPedidoMicrovix::dispatch($order->id, 'confirmar');
            }
        } catch (Throwable $e) {
            report($e);
        }

        AvisoPedido::agendarPagamento($order);
    }

    /**
     * Inverso do cancelAndRestoreStock: a Appmax aprovou um pedido que aqui
     * já estava cancelado (timeout na cobrança, cartão preso cancelado antes
     * do webhook). O dinheiro entrou — o pedido volta a valer e a reserva de
     * estoque é retomada, com o mesmo lock da criação.
     *
     * Estoque não trava a reativação (a venda já aconteceu): se o saldo não
     * cobre mais, baixa até zero e devolve a lista do que faltou, para o
     * incidente avisar o admin.
     *
     * @return list<string>  itens sem saldo suficiente ("Nome (vol): pedia N, havia M")
     */
    public function reativarPago(Order $order): array
    {
        $faltas = [];

        DB::transaction(function () use ($order, &$faltas) {
            $produtos = Product::whereIn('id', $order->items->pluck('product_id')->filter()->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($order->items as $item) {
                $rotulo = $item->product_name.($item->size ? " ({$item->size})" : '');
                $product = $produtos->get($item->product_id);

                if (! $product) {
                    $faltas[] = "{$rotulo}: produto não existe mais";

                    continue;
                }

                if (($saldo = $product->stockForSize($item->size)) !== null) {
                    if ($saldo < $item->qty) {
                        $faltas[] = "{$rotulo}: pedia {$item->qty}, havia {$saldo}";
                    }

                    $porVolume = $product->size_stock;
                    $porVolume[$item->size] = max(0, $saldo - (int) $item->qty);
                    $product->size_stock = $porVolume;
                }

                $product->stock_qty = max(0, (int) $product->stock_qty - (int) $item->qty);
                $product->save();
            }

            $order->update([
                'status'         => 'processing',
                'payment_status' => 'paid',
                'paid_at'        => $order->paid_at ?? now(),
            ]);
        });

        return $faltas;
    }

    /**
     * Devolve os itens de um pedido ao carrinho da sessão (produto + volume
     * + personalização), para o cliente tentar pagar de novo sem remontar a
     * sacola. Sem a personalização a garrafa custom voltaria como 375 comum.
     */
    public function restoreCartFromOrder(Order $order): void
    {
        foreach ($order->items as $item) {
            // Só os campos do cliente: o nome da bebida volta a vir da config.
            $p = $item->isCustom() ? Arr::only($item->personalizacao, CustomService::CAMPOS) : null;
            $this->cart->add((int) $item->product_id, (int) $item->qty, $item->size, $p);
        }
    }
}
