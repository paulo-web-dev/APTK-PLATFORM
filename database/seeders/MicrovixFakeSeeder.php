<?php

namespace Database\Seeders;

use App\Models\MicrovixProduct;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * FERRAMENTA DE DESENVOLVIMENTO. Popula microvix_products com dados fictícios
 * imitando o formato real do ERP, enquanto o portal B2C da Linx não é liberado
 * (hoje os 1237 produtos estão com b2c=0 e os métodos de produto voltam vazios).
 *
 * Gera um codigoproduto por combinação produto+volume do catálogo do site
 * (o ERP trata cada volume como SKU próprio), com casos-limite de propósito:
 * saldo 0, saldo 1, saldo alto, volume zerado com o irmão em estoque, preços
 * divergentes de size_prices, registros inativos e SKUs que só existem no ERP.
 *
 * Todos os registros usam codigoproduto >= CODIGO_BASE para poderem ser
 * removidos sem tocar em dados vindos do ERP real (microvix:fake --limpar).
 * O vínculo (product_id + size) fica NULO — vínculo é sempre decisão humana,
 * inclusive nos dados de mentira, senão o matcher da tela nunca é exercitado.
 */
class MicrovixFakeSeeder extends Seeder
{
    public const CODIGO_BASE = 900000;

    /** Início da faixa dos SKUs sem correspondência na loja (só no ERP). */
    public const CODIGO_SO_NO_ERP = 900101;

    /**
     * Saldos em sequência fixa (não aleatória: os testes e o dia a dia de dev
     * precisam de resultado reproduzível). A sequência começa com [3, 0] de
     * propósito: o primeiro produto com dois volumes fica com um volume em
     * estoque e o outro zerado — o caso-limite mais importante da conciliação.
     */
    protected const SALDOS = [3, 0, 1, 250, 12, 0, 36, 7, 90, 2, 0, 5, 18, 60, 1, 0, 44, 9, 120, 30, 0, 15];

    /** SKUs que existem no ERP e não têm correspondência nenhuma na loja. */
    protected const SO_NO_ERP = [
        ['codigoproduto' => 900101, 'nome_produto' => 'GELO CRISTAL ICE4PROS 2KG', 'referencia' => 'GELO2KG', 'codauxiliar1' => null, 'saldo' => 500, 'preco' => 24.90, 'ativo' => 1],
        ['codigoproduto' => 900102, 'nome_produto' => 'BARIN AMBURANA 750ML', 'referencia' => null, 'codauxiliar1' => 'BAR-AMB-750', 'saldo' => 18, 'preco' => 189.00, 'ativo' => 1],
        ['codigoproduto' => 900103, 'nome_produto' => 'KIT COQUETELARIA DESCONTINUADO', 'referencia' => null, 'codauxiliar1' => null, 'saldo' => 0, 'preco' => 99.00, 'ativo' => 0],
    ];

    public function run(): void
    {
        $codigo = self::CODIGO_BASE;
        $i = 0;

        foreach (Product::orderBy('id')->get() as $product) {
            foreach ($product->sizes ?? [] as $size) {
                $this->grava($this->linha(++$codigo, $i++, $product, $size));
            }
        }

        foreach (self::SO_NO_ERP as $linha) {
            $this->grava($linha);
        }
    }

    public function limpar(): int
    {
        return MicrovixProduct::where('codigoproduto', '>=', self::CODIGO_BASE)->delete();
    }

    /** Uma linha fake no formato que o MicrovixProductMirror gravaria. */
    protected function linha(int $codigo, int $i, Product $product, string $size): array
    {
        // "375 ml" → "375ML", como o ERP escreveria no nome.
        $ml = strtoupper(str_replace(' ', '', $size));

        // Preço de propósito DIFERENTE do site (para a conciliação ter o que
        // mostrar): alterna acima e abaixo do preço atual do volume.
        $preco = round($product->priceForSize($size) + ($i % 2 === 0 ? 7.77 : -5.00), 2);

        // Três formatos de identificação, um por produto, para exercitar cada
        // critério do matcher: codauxiliar1 casando com o sku do site por
        // prefixo, só referencia, ou nada além do nome.
        [$referencia, $codauxiliar1] = match ($product->id % 3) {
            0       => [null, strtok((string) $product->sku, '-').'-'.preg_replace('/\D/', '', $size)],
            1       => [mb_substr(mb_strtoupper(strtok($product->name, ' ')), 0, 20), null],
            default => [null, null],
        };

        return [
            'codigoproduto' => $codigo,
            'nome_produto'  => mb_strtoupper('APTK '.$product->name.' '.$ml),
            'referencia'    => $referencia,
            'codauxiliar1'  => $codauxiliar1,
            'saldo'         => self::SALDOS[$i % count(self::SALDOS)],
            'preco'         => $preco,
            'ativo'         => $i % 9 === 5 ? 0 : 1,
        ];
    }

    protected function grava(array $linha): void
    {
        MicrovixProduct::updateOrCreate(
            ['codigoproduto' => $linha['codigoproduto']],
            $linha + [
                // Como viria do ERP: tudo string, no formato posicional do WS.
                'raw'       => array_map(fn ($v) => $v === null ? '' : (string) $v, $linha),
                'synced_at' => now(),
            ],
        );
    }
}
