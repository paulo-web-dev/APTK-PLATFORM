<?php

namespace App\Services;

use App\Models\Coupon;

/** Resultado de CouponService::avaliar: aplicado (com o valor) ou recusado (com o motivo). */
final class CupomAvaliacao
{
    private function __construct(
        public readonly bool $aplicado,
        public readonly string $codigo,
        public readonly float $desconto,
        public readonly ?string $motivoChave,
        public readonly ?Coupon $coupon,
    ) {
    }

    public static function aplicado(Coupon $coupon, float $desconto): self
    {
        return new self(true, $coupon->code, $desconto, null, $coupon);
    }

    public static function recusado(string $codigo, string $motivo, ?Coupon $coupon = null): self
    {
        return new self(false, $codigo, 0.0, $motivo, $coupon);
    }

    /** Motivo da recusa em português (null se aplicado). */
    public function motivo(): ?string
    {
        if ($this->aplicado) {
            return null;
        }

        $texto = CouponService::MOTIVOS[$this->motivoChave] ?? 'Cupom não aplicado.';

        return str_replace(':valor', moeda_br($this->coupon?->min_order ?? 0), $texto);
    }

    /** "Cupom MITRE15 aplicado: 15% off" ou "Cupom MITRE15 não aplicado: <motivo>". */
    public function mensagem(): string
    {
        return $this->aplicado
            ? "Cupom {$this->codigo} aplicado: ".$this->coupon->rotuloValor().' off'
            : ($this->codigo !== '' ? "Cupom {$this->codigo} não aplicado: " : '').$this->motivo();
    }
}
