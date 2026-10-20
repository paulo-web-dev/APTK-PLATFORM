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

    /** Produto da bebida, se ela é elegível e existe no catálogo (ativo ou não). */
    public function produto(?string $bebida): ?Product
    {
        if ($bebida === null || ! array_key_exists($bebida, $this->bebidas())) {
            return null;
        }

        return Product::where('slug', $bebida)->first();
    }

    /**
     * Aceita ou recusa a personalização. Devolve os 4 campos como vieram.
     *
     * @throws ValidationException
     */
    public function validar(array $dados): array
    {
        $p = [];
        foreach (self::CAMPOS as $campo) {
            $p[$campo] = $dados[$campo] ?? null;
        }

        Validator::make($p, [
            'bebida' => ['required', 'string', Rule::in(array_keys($this->bebidas())),
                function ($attr, $valor, $fail) {
                    if (! $this->produto($valor)) {
                        $fail('Essa bebida não está disponível para personalização.');
                    }
                }],
            'fonte'  => ['required', 'string', Rule::in(array_keys(config('custom.fontes', [])))],
            'cor'    => ['required', 'string', Rule::in(array_keys(config('custom.cores', [])))],
            'texto'  => ['required', 'string', 'max:'.$this->limite(), 'not_regex:/[\r\n]/', 'regex:'.self::CARACTERES],
        ], [
            'bebida.required' => 'Escolha a bebida.',
            'bebida.in'       => 'Essa bebida não está disponível para personalização.',
            'fonte.required'  => 'Escolha o estilo do rótulo.',
            'fonte.in'        => 'Escolha um dos estilos disponíveis.',
            'cor.required'    => 'Escolha a cor do texto.',
            'cor.in'          => 'Escolha uma das cores disponíveis.',
            'texto.required'  => 'Escreva o texto do rótulo.',
            'texto.max'       => 'O texto do rótulo pode ter até :max caracteres.',
            'texto.not_regex' => 'O texto do rótulo é de uma linha só: a quebra é automática.',
            'texto.regex'     => 'Use só letras, números e pontuação simples no texto do rótulo.',
        ])->validate();

        return $p;
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
     * saiu da config: mostra o valor cru em vez de quebrar a página.
     */
    public function exibir(array $p): array
    {
        $bebida = config('custom.bebidas.'.($p['bebida'] ?? ''));
        $fonte = config('custom.fontes.'.($p['fonte'] ?? ''));
        $cor = config('custom.cores.'.($p['cor'] ?? ''));

        return [
            'texto'       => (string) ($p['texto'] ?? ''),
            'bebida'      => $p['bebida'] ?? null,
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
