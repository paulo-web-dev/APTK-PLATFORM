<?php

namespace Tests\Feature\Microvix;

use App\Models\MicrovixProduct;
use App\Models\MicrovixSyncState;
use App\Services\Microvix\MicrovixProductMirror;
use App\Services\Microvix\MicrovixSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MicrovixSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'microvix.environment' => 'aceitacao',
            'microvix.id_portal'   => '18443',
            'microvix.cnpj_emp'    => '36491550000106',
            'microvix.user'        => 'u',
            'microvix.password'    => 'p',
            'microvix.chave'       => 'c',
        ]);
    }

    protected function fixture(string $nome): string
    {
        return file_get_contents(base_path("tests/fixtures/microvix/{$nome}.xml"));
    }

    public function test_avanca_o_timestamp_para_o_maior_retornado(): void
    {
        Http::fake(['*' => Http::response($this->fixture('produtos'))]);

        $count = app(MicrovixSync::class)->sync(
            'B2CConsultaProdutos',
            app(MicrovixProductMirror::class)->handler('B2CConsultaProdutos'),
        );

        $this->assertSame(2, $count);

        $state = MicrovixSyncState::where('method', 'B2CConsultaProdutos')->firstOrFail();
        $this->assertSame(250, $state->last_timestamp); // maior dos dois (100, 250)
        $this->assertSame(2, $state->records_synced);
        $this->assertNull($state->last_error);

        $this->assertSame(2, MicrovixProduct::count());
        $this->assertDatabaseHas('microvix_products', [
            'codigoproduto' => 12837,
            'nome_produto'  => 'APTK Negroni 375ml',
        ]);
    }

    public function test_erro_no_meio_do_lote_nao_avanca_o_timestamp(): void
    {
        Http::fake(['*' => Http::response($this->fixture('produtos'))]);

        MicrovixSyncState::create(['method' => 'B2CConsultaProdutos', 'last_timestamp' => 50]);

        $processados = 0;

        try {
            app(MicrovixSync::class)->sync('B2CConsultaProdutos', function () use (&$processados) {
                $processados++;
                if ($processados === 2) {
                    throw new RuntimeException('falha simulada no handler');
                }
            });
            $this->fail('Deveria ter relançado a exceção do handler.');
        } catch (RuntimeException) {
            // esperado
        }

        $state = MicrovixSyncState::where('method', 'B2CConsultaProdutos')->firstOrFail();

        // Se avançasse, o registro que falhou sumiria para sempre (4.1 da doc).
        $this->assertSame(50, $state->last_timestamp);
        $this->assertStringContainsString('falha simulada', $state->last_error);
    }

    public function test_resposta_vazia_significa_nada_mudou_e_nao_e_erro(): void
    {
        Http::fake(['*' => Http::response($this->fixture('vazio'))]);

        MicrovixSyncState::create(['method' => 'B2CConsultaProdutos', 'last_timestamp' => 999]);

        $count = app(MicrovixSync::class)->sync('B2CConsultaProdutos', fn () => null);

        $this->assertSame(0, $count);

        $state = MicrovixSyncState::where('method', 'B2CConsultaProdutos')->firstOrFail();
        $this->assertSame(999, $state->last_timestamp);
        $this->assertNull($state->last_error);
        $this->assertNotNull($state->last_run_at);
    }

    public function test_envia_o_last_timestamp_guardado_na_consulta(): void
    {
        Http::fake(['*' => Http::response($this->fixture('vazio'))]);

        MicrovixSyncState::create(['method' => 'B2CConsultaProdutos', 'last_timestamp' => 8146561522]);

        app(MicrovixSync::class)->sync('B2CConsultaProdutos', fn () => null);

        Http::assertSent(fn (Request $r) => str_contains(
            $r->body(),
            '<Parameter id="timestamp">8146561522</Parameter>',
        ));
    }

    public function test_saldo_e_preco_compoem_o_espelho(): void
    {
        $mirror = app(MicrovixProductMirror::class);
        $sync = app(MicrovixSync::class);

        // Fake único roteando pelo <Name> do envelope (Http::fake empilha
        // stubs — um '*' antigo continuaria respondendo às chamadas novas).
        // Os nomes mais longos vêm primeiro: "B2CConsultaProdutos" é prefixo deles.
        Http::fake(function (Request $request) {
            $body = $request->body();

            return match (true) {
                str_contains($body, '<Name>B2CConsultaProdutosDetalhes</Name>')      => Http::response($this->fixture('produtos-detalhes')),
                str_contains($body, '<Name>B2CConsultaProdutosTabelasPrecos</Name>') => Http::response($this->fixture('tabelas-precos')),
                default                                                              => Http::response($this->fixture('produtos')),
            };
        });

        foreach (MicrovixProductMirror::METHODS as $method) {
            $sync->sync($method, $mirror->handler($method));
        }

        $p = MicrovixProduct::where('codigoproduto', 12837)->firstOrFail();

        $this->assertSame('3.000', $p->saldo);   // decimal:3
        $this->assertSame('89.90', $p->preco);   // "89,90" (MONEY com vírgula) → 89.90
        $this->assertSame('APTK Negroni 375ml', $p->nome_produto);
        $this->assertNull($p->product_id);       // vínculo com o catálogo é manual
    }
}
