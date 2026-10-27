<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CPF com dígito verificador. Aceita com ou sem pontuação; 11 dígitos
 * iguais (111.111.111-11) passam no cálculo mas não existem — recusados.
 */
class Cpf implements ValidationRule
{
    public const MENSAGEM = 'Informe um CPF válido (000.000.000-00).';

    public static function valido(?string $valor): bool
    {
        $cpf = preg_replace('/\D+/', '', (string) $valor);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        foreach ([9, 10] as $posicao) {
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cpf[$i] * ($posicao + 1 - $i);
            }
            $digito = ($soma * 10) % 11 % 10;

            if ((int) $cpf[$posicao] !== $digito) {
                return false;
            }
        }

        return true;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/', trim($value)) || ! self::valido($value)) {
            $fail(self::MENSAGEM);
        }
    }
}
