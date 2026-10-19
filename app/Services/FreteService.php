<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Frete estimado (leva 08): envio próprio a partir de SP capital, valor da
 * tabela de referência dos Correios (config/frete.php) + margem.
 *
 * Usado na cotação do checkout (FreteController) e de novo no fechamento do
 * pedido (OrderService) — o servidor SEMPRE recalcula a partir do CEP e da
 * opção escolhida; o valor que o front mostrou nunca é aceito.
 *
 * Contas em gramas e centavos (inteiros) para não depender de float.
 */
class FreteService
{
    /** Código (orders.shipping_method) da opção de frete zero do carrinho isento. */
    public const CODIGO_ISENTO = 'teste';

    public const NOME_ISENTO = 'Teste (sem frete)';

    /**
     * Opções para o carrinho e o CEP, no formato de transportadora:
     * [['codigo' => 'pac', 'nome' => 'PAC', 'valor' => 31.20, 'prazo_dias' => 5], ...]
     *
     * Carrinho 100% de produtos isentos (config frete.isentos): uma opção só,
     * "teste", R$ 0,00. Com qualquer item não isento, cálculo normal.
     *
     * @param  Collection<int, object>  $items  linhas do CartService::items()
     *
     * @throws ValidationException  carrinho vazio, CEP inválido ou fora das faixas
     */
    public function cotar(Collection $items, string $cep): array
    {
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Seu carrinho está vazio.']);
        }

        $regiao = $this->regiao($cep); // CEP valida igual, isento ou não

        if ($this->todosIsentos($items)) {
            return [[
                'codigo'     => self::CODIGO_ISENTO,
                'nome'       => self::NOME_ISENTO,
                'valor'      => 0.0,
                'prazo_dias' => 0,
            ]];
        }
        $kg = $this->pesoKg($items);
        $opcoes = [];

        foreach (config('frete.servicos') as $codigo => $nome) {
            [$base, $adicional, $prazo] = config("frete.tabela.{$regiao}.{$codigo}");

            $centavos = ($base * 100) + ($adicional * 100) * ($kg - 1);
            $comMargem = (int) ceil(round($centavos * (1 + (float) config('frete.margem')), 6));

            $opcoes[] = [
                'codigo'     => $codigo,
                'nome'       => $nome,
                'valor'      => round($comMargem / 100, 2), // sempre float, mesmo inteiro
                'prazo_dias' => $prazo + (int) config('frete.dias_manuseio'),
            ];
        }

        return $opcoes;
    }

    /**
     * Uma opção específica (fechamento do pedido).
     *
     * @throws ValidationException  além dos casos de cotar(), opção inexistente
     */
    public function opcao(Collection $items, string $cep, ?string $codigo): array
    {
        foreach ($this->cotar($items, $cep) as $opcao) {
            if ($opcao['codigo'] === $codigo) {
                return $opcao;
            }
        }

        throw ValidationException::withMessages(['shipping_method' => 'Escolha uma opção de frete.']);
    }

    /** Todos os itens são de produtos com frete isento (config frete.isentos)? */
    public function todosIsentos(Collection $items): bool
    {
        $isentos = (array) config('frete.isentos', []);

        return $isentos !== []
            && $items->isNotEmpty()
            && $items->every(fn ($row) => in_array($row->product->slug ?? null, $isentos, true));
    }

    /** Peso total do carrinho em kg, arredondado PRA CIMA ao kg inteiro (mínimo 1). */
    public function pesoKg(Collection $items): int
    {
        $embalagem = (int) round((float) config('frete.embalagem_kg') * 1000);
        $porItem = config('frete.embalagem_por') !== 'pedido';
        $gramas = $porItem ? 0 : $embalagem;

        foreach ($items as $row) {
            $liquido = $this->ml($row->size) ?? (int) round((float) config('frete.peso_padrao_kg') * 1000);
            $gramas += ($liquido + ($porItem ? $embalagem : 0)) * (int) $row->qty;
        }

        return max(1, (int) ceil($gramas / 1000));
    }

    /**
     * ml do texto do volume: "375 ml" → 375, "1,5 L" → 1500. Ilegível → null
     * (cai no peso padrão).
     */
    public function ml(?string $volume): ?int
    {
        if ($volume === null || ! preg_match('/(\d+(?:[.,]\d+)?)\s*(ml|l)\b/i', $volume, $m)) {
            return null;
        }

        $n = (float) str_replace(',', '.', $m[1]);

        return (int) round(strtolower($m[2]) === 'l' ? $n * 1000 : $n);
    }

    /** Região da tabela pelo CEP de destino. */
    public function regiao(string $cep): string
    {
        $digitos = preg_replace('/\D+/', '', $cep);

        if (strlen($digitos) !== 8) {
            throw ValidationException::withMessages(['zipcode' => 'Informe um CEP válido (00000-000).']);
        }

        $prefixo = substr($digitos, 0, 5);
        $uf = null;

        foreach (config('frete.faixas_uf') as [$ini, $fim, $sigla]) {
            if ($prefixo >= $ini && $prefixo <= $fim) {
                $uf = $sigla;
                break;
            }
        }

        if ($uf === 'SP') {
            foreach (config('frete.regioes_sp') as $regiao => [$ini, $fim]) {
                if ($prefixo >= $ini && $prefixo <= $fim) {
                    return $regiao;
                }
            }
        }

        foreach (config('frete.regioes_uf') as $regiao => $ufs) {
            if (in_array($uf, $ufs, true)) {
                return $regiao;
            }
        }

        throw ValidationException::withMessages(['zipcode' => 'Não encontramos esse CEP na nossa área de entrega. Confira o número.']);
    }
}
