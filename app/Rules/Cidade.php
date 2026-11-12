<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nome de cidade por extenso: pelo menos 3 letras e nada de sigla ("VP",
 * "SBC", "BH"). A Linx cadastra o cliente com o texto como veio, e sigla
 * quebra o faturamento (pedido #58 chegou com "VP"). Uma palavra só, em
 * maiúsculas e com até 3 letras, é tratada como sigla — quem mora em Ubá
 * escreve "Ubá" e passa.
 */
class Cidade implements ValidationRule
{
    public const MENSAGEM = 'Escreva o nome da cidade por extenso, sem abreviação.';

    public static function valida(?string $valor): bool
    {
        $cidade = trim((string) $valor);
        $letras = preg_replace('/[^\p{L}]+/u', '', $cidade) ?? '';

        if (mb_strlen($letras) < 3) {
            return false;
        }

        $sigla = ! preg_match('/\s/u', $cidade)
            && mb_strlen($letras) <= 3
            && $letras === mb_strtoupper($letras)
            && preg_match('/^[A-Z]+$/', $letras) === 1;

        return ! $sigla;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::valida(is_string($value) ? $value : null)) {
            $fail(self::MENSAGEM);
        }
    }
}
