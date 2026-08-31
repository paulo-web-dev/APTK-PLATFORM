<?php

namespace Tests\Feature\Microvix;

use App\Models\MicrovixProduct;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Monta pedido + vínculo + config prontos pro envio ao ERP (fase 2).
 * Compartilhado pelos testes de job, webhook e checkout.
 */
trait MontaPedidoParaEnvio
{
    protected function configuraMicrovixParaEnvio(bool $enviaLigado = true): void
    {
        config([
            'microvix.environment'       => 'aceitacao',
            'microvix.id_portal'         => '18443',
            'microvix.cnpj_emp'          => '36491550000106',
            'microvix.user'              => 'usuario_teste',
            'microvix.password'          => 'senha-secreta',
            'microvix.chave'             => 'chave-secreta-uuid',
            'microvix.empresa'           => '4',
            'microvix.cod_vendedor'      => '15',
            'microvix.id_deposito'       => '1',
            'microvix.id_tabela_preco'   => '1',
            'microvix.id_status_inicial' => '3',
            'microvix.id_tipo_encomenda' => '99',
            'microvix.planos_pagamento'  => ['cartao' => 279, 'pix' => null, 'boleto' => null],
            'microvix.envia_pedido'      => $enviaLigado,
        ]);
    }

    protected function fixtureMicrovix(string $nome): string
    {
        return file_get_contents(base_path("tests/Fixtures/microvix/{$nome}.xml"));
    }

    protected function fixturePedidoExisteNoErp(Order $order): string
    {
        return str_replace(
            ['{ID_PEDIDO}', '{COD_CLIENTE}'],
            [(string) $order->id, (string) $order->user_id],
            $this->fixtureMicrovix('pedidos-existe'),
        );
    }

    protected function pedidoParaEnvio(string $metodo = 'cartao', array $atributos = []): Order
    {
        $user = User::create([
            'name'     => 'Cliente ERP',
            'email'    => uniqid().'@aptk.test',
            'password' => bcrypt('secret123'),
            'role'     => 'customer',
        ]);

        $product = Product::firstOrCreate(['slug' => 'negroni-classico'], [
            'name'      => 'Negroni Clássico',
            'sku'       => 'NEG-750',
            'price'     => 189.90,
            'sizes'     => ['750 ml'],
            'stock_qty' => 30,
            'active'    => true,
        ]);

        MicrovixProduct::firstOrCreate(['codigoproduto' => 900123], [
            'product_id'   => $product->id,
            'size'         => '750 ml',
            'nome_produto' => 'NEGRONI 750',
            'ativo'        => true,
        ]);

        $order = Order::create(array_merge([
            'user_id'           => $user->id,
            'status'            => 'pending',
            'subtotal'          => 379.80,
            'discount'          => 0,
            'shipping_cost'     => 10.00,
            'total'             => 389.80,
            'payment_method'    => $metodo,
            'payment_status'    => 'pending',
            'ship_name'         => 'Cliente ERP',
            'ship_cpf'          => '111.444.777-35',
            'ship_phone'        => '11 99999-0000',
            'ship_zipcode'      => '01310-100',
            'ship_street'       => 'Avenida Paulista',
            'ship_number'       => '1000',
            'ship_neighborhood' => 'Bela Vista',
            'ship_city'         => 'São Paulo',
            'ship_state'        => 'SP',
        ], $atributos));

        $order->items()->create([
            'product_id'   => $product->id,
            'product_name' => 'Negroni Clássico',
            'product_sku'  => 'NEG-750',
            'size'         => '750 ml',
            'qty'          => 2,
            'unit_price'   => 189.90,
            'total_price'  => 379.80,
        ]);

        return $order->fresh(['items', 'user']);
    }

    /** Corpos das requisições HTTP gravadas pelo fake, na ordem. */
    protected function corposEnviados(): array
    {
        $corpos = [];
        Http::recorded(function ($request) use (&$corpos) {
            $corpos[] = $request->body();

            return true;
        });

        return $corpos;
    }
}
