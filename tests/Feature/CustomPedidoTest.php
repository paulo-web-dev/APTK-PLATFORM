<?php

namespace Tests\Feature;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Custom Simples (leva 10): formulário reordenado (coquetel → rótulo →
 * dados → prévia e envio), botão "Solicitar minha personalização" e estilos
 * com nome na tela — o lead continua gravando a fonte.
 */
class CustomPedidoTest extends TestCase
{
    use RefreshDatabase;

    public function test_formulario_na_ordem_nova_com_o_botao_de_solicitacao(): void
    {
        $html = $this->get('/custom')->assertOk()->getContent();

        $coquetel = strpos($html, 'name="coquetel"');
        $fonte = strpos($html, 'name="fonte"');
        $mensagem = strpos($html, 'name="mensagem"');
        $nome = strpos($html, 'name="name"');
        $botao = strpos($html, 'Solicitar minha personalização');

        $this->assertNotFalse($coquetel);
        $this->assertTrue($coquetel < $fonte && $fonte < $mensagem && $mensagem < $nome && $nome < $botao);
        $this->assertStringNotContainsString('>Comprar</button>', $html);

        foreach (['Editorial', 'Autoral'] as $estilo) {
            $this->assertStringContainsString($estilo, $html);
        }
        // Blackword ("Clássico") fora do seletor até a fonte existir no projeto.
        $this->assertStringNotContainsString('value="Blackword"', $html);
        $this->assertStringNotContainsString('>Clássico<', $html);
    }

    public function test_pedido_grava_lead_com_a_fonte(): void
    {
        $this->post('/custom/pedido', [
            'coquetel' => 'Negroni',
            'fonte'    => 'Amithen',
            'mensagem' => 'Para a melhor mãe do mundo',
            'name'     => 'Bia',
            'email'    => 'bia@exemplo.test',
            'phone'    => '',
        ])->assertRedirect()->assertSessionHas('custom_ok');

        $lead = Lead::sole();
        $this->assertSame('custom', $lead->type);
        $this->assertStringContainsString('Fonte: Amithen', $lead->message);
        $this->assertStringContainsString('Coquetel: Negroni', $lead->message);
    }

    public function test_fonte_que_nao_existe_no_projeto_e_recusada(): void
    {
        $this->post('/custom/pedido', [
            'coquetel' => 'Negroni',
            'fonte'    => 'Blackword',
            'mensagem' => 'Feliz 50',
            'name'     => 'Bia',
            'email'    => 'bia@exemplo.test',
        ])->assertSessionHasErrors('fonte');

        $this->assertSame(0, Lead::count());
    }

    public function test_mensagem_com_mais_de_8_palavras_e_recusada(): void
    {
        $this->post('/custom/pedido', [
            'coquetel' => 'Negroni',
            'fonte'    => 'PP Rader',
            'mensagem' => 'um dois três quatro cinco seis sete oito nove',
            'name'     => 'Bia',
            'email'    => 'bia@exemplo.test',
        ])->assertSessionHasErrors('mensagem');

        $this->assertSame(0, Lead::count());
    }
}
