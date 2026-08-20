<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderShippingSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function dadosCheckout(array $extra = []): array
    {
        return array_merge([
            'name'           => 'Maria Destinatária',
            'phone'          => '(47) 99999-8888',
            'cpf'            => '123.456.789-09',
            'zipcode'        => '89234-091',
            'street'         => 'Avenida das Alamedas',
            'number'         => '467',
            'complement'     => 'Fundos',
            'neighborhood'   => 'Boa Vista',
            'city'           => 'Joinville',
            'state'          => 'sc',
            'payment_method' => 'pix',
        ], $extra);
    }

    protected function pedidoPara(User $user, array $extra = [])
    {
        $product = Product::create([
            'name'      => 'APTK Negroni',
            'slug'      => 'aptk-negroni-'.uniqid(),
            'price'     => 89.90,
            'stock_qty' => 10,
            'active'    => true,
        ]);

        app(CartService::class)->add($product->id, 1);

        return app(OrderService::class)->placeFromCart($user, $this->dadosCheckout($extra));
    }

    public function test_pedido_congela_o_endereco_nas_colunas_ship(): void
    {
        $user = User::factory()->create();

        $order = $this->pedidoPara($user);

        $this->assertSame('Maria Destinatária', $order->ship_name);
        $this->assertSame('12345678909', $order->ship_cpf); // só dígitos
        $this->assertSame('(47) 99999-8888', $order->ship_phone);
        $this->assertSame('89234-091', $order->ship_zipcode);
        $this->assertSame('Avenida das Alamedas', $order->ship_street);
        $this->assertSame('467', $order->ship_number);
        $this->assertSame('Fundos', $order->ship_complement);
        $this->assertSame('Boa Vista', $order->ship_neighborhood);
        $this->assertSame('Joinville', $order->ship_city);
        $this->assertSame('SC', $order->ship_state); // normalizado pelo OrderService

        // Referência fraca ao endereço salvo na conta — quem manda é o snapshot.
        $this->assertNotNull($order->address_id);
        $this->assertSame($order->address_id, $user->addresses()->first()->id);
    }

    public function test_cpf_e_gravado_no_usuario_somente_se_ainda_nao_tiver(): void
    {
        $user = User::factory()->create();

        $this->pedidoPara($user);
        $this->assertSame('12345678909', $user->fresh()->cpf);

        // Um segundo pedido com outro CPF (ex.: presente para terceiro)
        // NÃO sobrescreve o CPF já cadastrado.
        $this->pedidoPara($user, ['cpf' => '987.654.321-00']);
        $this->assertSame('12345678909', $user->fresh()->cpf);
    }
}
