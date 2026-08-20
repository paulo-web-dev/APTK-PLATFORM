<?php

namespace Tests\Feature;

use Tests\TestCase;

class FreteCotarTest extends TestCase
{
    public function test_cotacao_devolve_a_opcao_fixa_no_formato_de_transportadora(): void
    {
        $response = $this->postJson('/frete/cotar', ['zipcode' => '89234-091']);

        $response->assertOk()->assertJson([
            'opcoes' => [
                ['nome' => 'Entrega padrão', 'valor' => 10.00, 'prazo_dias' => 5],
            ],
        ]);
    }

    public function test_cep_sem_hifen_tambem_e_aceito(): void
    {
        $this->postJson('/frete/cotar', ['zipcode' => '89234091'])->assertOk();
    }

    public function test_cep_invalido_devolve_422(): void
    {
        $this->postJson('/frete/cotar', ['zipcode' => 'abc'])->assertStatus(422);
        $this->postJson('/frete/cotar', [])->assertStatus(422);
    }
}
