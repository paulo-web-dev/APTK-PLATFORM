<?php

namespace App\Services\Microvix;

use App\Models\MicrovixProduct;

/**
 * Espelha o catálogo do ERP em microvix_products. NÃO toca na tabela
 * products — nesta fase o site continua com estoque/preço próprios; o
 * vínculo codigoproduto → product_id + size é manual.
 *
 * Três métodos de saída compõem o espelho:
 *   B2CConsultaProdutos             → cadastro (nome, referência, ativo)
 *   B2CConsultaProdutosDetalhes     → saldo por empresa
 *   B2CConsultaProdutosTabelasPrecos → preço por tabela
 */
class MicrovixProductMirror
{
    public const METHODS = [
        'B2CConsultaProdutos',
        'B2CConsultaProdutosDetalhes',
        'B2CConsultaProdutosTabelasPrecos',
    ];

    /** @return callable(array<string, string>): void */
    public function handler(string $method): callable
    {
        return match ($method) {
            'B2CConsultaProdutos'              => fn (array $r) => $this->aplicaProduto($r),
            'B2CConsultaProdutosDetalhes'      => fn (array $r) => $this->aplicaSaldo($r),
            'B2CConsultaProdutosTabelasPrecos' => fn (array $r) => $this->aplicaPreco($r),
        };
    }

    protected function aplicaProduto(array $r): void
    {
        MicrovixProduct::updateOrCreate(
            ['codigoproduto' => (int) $r['codigoproduto']],
            [
                'referencia'   => $this->texto($r['referencia'] ?? null),
                'codauxiliar1' => $this->texto($r['codauxiliar1'] ?? null),
                'nome_produto' => $this->texto($r['nome_produto'] ?? null),
                'ativo'        => isset($r['ativo']) ? (bool) (int) $r['ativo'] : null,
                'raw'          => $r,
                'synced_at'    => now(),
            ],
        );
    }

    protected function aplicaSaldo(array $r): void
    {
        // Com MICROVIX_EMPRESA definida, só o saldo daquela empresa interessa.
        // Sem ela (fase de descoberta), aplica qualquer linha — a última vence.
        $empresa = config('microvix.empresa');
        if ($empresa !== null && $empresa !== '' && (int) ($r['empresa'] ?? 0) !== (int) $empresa) {
            return;
        }

        MicrovixProduct::updateOrCreate(
            ['codigoproduto' => (int) $r['codigoproduto']],
            ['saldo' => (float) ($r['saldo'] ?? 0), 'synced_at' => now()],
        );
    }

    protected function aplicaPreco(array $r): void
    {
        $tabela = config('microvix.id_tabela_preco');
        if ($tabela !== null && $tabela !== '' && (int) ($r['id_tabela'] ?? 0) !== (int) $tabela) {
            return;
        }

        MicrovixProduct::updateOrCreate(
            ['codigoproduto' => (int) $r['codigoproduto']],
            ['preco' => $this->decimalBr($r['precovenda'] ?? null), 'synced_at' => now()],
        );
    }

    /** SimpleXML devolve elemento vazio como array [] — normaliza para null. */
    protected function texto(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    /** A doc exemplifica MONEY com vírgula ("3,40") — aceita os dois formatos. */
    protected function decimalBr(mixed $valor): ?float
    {
        if ($valor === null || $valor === '' || is_array($valor)) {
            return null;
        }

        return (float) str_replace(',', '.', (string) $valor);
    }
}
