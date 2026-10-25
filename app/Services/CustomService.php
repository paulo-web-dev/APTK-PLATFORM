<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Custom Simples (leva 11): garrafa 375 ml com rótulo personalizado.
 *
 * Fonte única das regras (config/custom.php). A personalização é o JSON
 * {bebida, texto, fonte, cor} exatamente como o cliente enviou: aqui ela é
 * aceita ou recusada, nunca corrigida. Valida no add-to-cart e de novo no
 * checkout (a config pode ter mudado com o item no carrinho).
 */
class CustomService
{
    /** Campos da personalização, nessa ordem (é a ordem do hash da linha). */
    public const CAMPOS = ['bebida', 'texto', 'fonte', 'cor'];

    /**
     * Letras (com acento), números, espaço e pontuação simples. Sem emoji
     * (as fontes do rótulo não têm o glifo), sem < > (HTML) e sem quebra de
     * linha (a quebra é automática).
     */
    private const CARACTERES = '/^[\p{L}\p{M}\p{N} .,;:!?¡¿\'"’“”&@#()\/+\-–—]+$/u';

    public function preco(): float
    {
        return (float) config('custom.preco');
    }

    public function volume(): string
    {
        return (string) config('custom.volume');
    }

    public function limite(): int
    {
        return (int) config('custom.limite');
    }

    /** @return array<string, array<string, mixed>> slug => config, na ordem da config */
    public function bebidas(): array
    {
        return config('custom.bebidas', []);
    }

    /**
     * Fontes oferecidas agora: a que tem `arquivos` na config só entra se um
     * deles existir em public/ (a Blacksword aparece sozinha quando o arquivo
     * chegar, sem deploy de código).
     *
     * @return array<string, array<string, mixed>> chave => config, na ordem da config
     */
    public function fontes(): array
    {
        return array_filter(config('custom.fontes', []), function ($f) {
            if (empty($f['arquivos'])) {
                return true;
            }
            foreach ((array) $f['arquivos'] as $arquivo) {
                if (is_file(public_path($arquivo))) {
                    return true;
                }
            }

            return false;
        });
    }

    /** Cor do texto: fixa (config custom.cor), sem seletor. */
    public function cor(): string
    {
        return (string) config('custom.cor.chave');
    }

    /**
     * Nome da bebida no Custom (config `nome`; a caipirinha-arazul aparece só
     * como "Caipirinha"). Vale também pras retiradas, pra pedido antigo.
     * Null se a bebida não está na config.
     */
    public function nomeBebida(?string $bebida): ?string
    {
        return $this->configBebida($bebida)['nome'] ?? null;
    }

    /** Config da bebida: elegível ou retirada (só pra exibir). */
    private function configBebida(?string $bebida): ?array
    {
        if ($bebida === null || $bebida === '') {
            return null;
        }

        return config('custom.bebidas')[$bebida] ?? config('custom.retiradas', [])[$bebida] ?? null;
    }

    /** Produto da bebida, se ela é elegível e existe no catálogo (ativo ou não). */
    public function produto(?string $bebida): ?Product
    {
        if ($bebida === null || ! array_key_exists($bebida, $this->bebidas())) {
            return null;
        }

        return Product::where('slug', $bebida)->first();
    }

    /**
     * Aceita ou recusa a personalização. Devolve os 4 campos como vieram;
     * `cor` não vem do formulário (sem a chave, entra a fixa) e é recusada se
     * vier diferente — caso de linha antiga no carrinho, com outra cor.
     *
     * @throws ValidationException
     */
    public function validar(array $dados): array
    {
        $p = [];
        foreach (self::CAMPOS as $campo) {
            $p[$campo] = $dados[$campo] ?? null;
        }
        $p['cor'] ??= $this->cor();

        Validator::make($p, [
            'bebida' => ['bail', 'required', 'string',
                function ($attr, $valor, $fail) {
                    if (array_key_exists($valor, config('custom.retiradas', []))) {
                        $fail('Essa bebida não está mais disponível para personalização.');
                    } elseif (! $this->produto($valor)) {
                        $fail('Essa bebida não está disponível para personalização.');
                    }
                }],
            'fonte'  => ['required', 'string', Rule::in(array_keys($this->fontes()))],
            'cor'    => ['required', 'string', Rule::in([$this->cor()])],
            'texto'  => ['required', 'string', 'max:'.$this->limite(), 'not_regex:/[\r\n]/', 'regex:'.self::CARACTERES],
        ], [
            'bebida.required' => 'Escolha a bebida.',
            'fonte.required'  => 'Escolha o estilo do rótulo.',
            'fonte.in'        => 'Escolha um dos estilos disponíveis.',
            'cor.in'          => 'O texto do rótulo é sempre preto.',
            'texto.required'  => 'Escreva o texto do rótulo.',
            'texto.max'       => 'O texto do rótulo pode ter até :max caracteres.',
            'texto.not_regex' => 'O texto do rótulo é de uma linha só: a quebra é automática.',
            'texto.regex'     => 'Use só letras, números e pontuação simples no texto do rótulo.',
        ])->validate();

        return $p;
    }

    /**
     * O que o pedido grava: os 4 campos como vieram + o nome exibido da
     * bebida naquele momento (a config pode mudar depois).
     */
    public function paraPedido(array $p): array
    {
        $gravar = [];
        foreach (self::CAMPOS as $campo) {
            $gravar[$campo] = $p[$campo] ?? null;
        }

        return $gravar + ['bebida_nome' => $this->nomeBebida($p['bebida'] ?? null)];
    }

    /** Motivo da recusa (primeira mensagem do validar), ou null se a personalização vale. */
    public function recusa(array $p): ?string
    {
        try {
            $this->validar($p);

            return null;
        } catch (ValidationException $e) {
            return collect($e->errors())->flatten()->first();
        }
    }

    /** Hash curto da personalização: entra na chave da linha do carrinho. */
    public function hash(array $p): string
    {
        $ordenado = [];
        foreach (self::CAMPOS as $campo) {
            $ordenado[$campo] = (string) ($p[$campo] ?? '');
        }

        return substr(sha1(json_encode($ordenado, JSON_UNESCAPED_UNICODE)), 0, 10);
    }

    /**
     * Dados de exibição (prévia, carrinho, pedido, admin). Tolera chave que
     * saiu da config: mostra o valor cru em vez de quebrar a página. O nome
     * da bebida gravado no pedido (bebida_nome) vence o da config.
     */
    public function exibir(array $p): array
    {
        $bebida = $this->configBebida($p['bebida'] ?? null);
        $fonte = config('custom.fontes.'.($p['fonte'] ?? ''));
        $cor = ($p['cor'] ?? null) === $this->cor() ? config('custom.cor') : null;

        return [
            'texto'       => (string) ($p['texto'] ?? ''),
            'bebida'      => $p['bebida'] ?? null,
            'bebida_nome' => $p['bebida_nome'] ?? $bebida['nome'] ?? null,
            'imagem'      => $bebida ? asset($bebida['imagem']) : null,
            'proporcao'   => $bebida ? $bebida['largura'].' / '.$bebida['altura'] : '1 / 1',
            'area'        => $bebida['area'] ?? [30, 50, 40, 25],
            'tamanho'     => ($bebida['fonte'] ?? 3) * ($fonte['escala'] ?? 1),
            'fonte'       => $p['fonte'] ?? null,
            'fonte_nome'  => $fonte['nome'] ?? ($p['fonte'] ?? ''),
            'fonte_classe' => $fonte['classe'] ?? 'cst-f-pprader',
            'cor'         => $p['cor'] ?? null,
            'cor_nome'    => $cor['nome'] ?? ($p['cor'] ?? ''),
            'cor_token'   => $cor['token'] ?? '--color-ink',
            'cor_hex'     => $cor['hex'] ?? null,
        ];
    }
}
