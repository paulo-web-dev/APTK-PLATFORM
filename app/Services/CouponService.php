<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * Regras de cupom num lugar só: carrinho, checkout, link ?cupom= e o
 * fechamento do pedido (OrderService::placeFromCart, com lock) chamam
 * avaliar(). A sessão guarda só o código: tudo é reavaliado a cada uso,
 * então inativar/expirar vale na hora.
 *
 * - Desconto só sobre os produtos (subtotal dos itens, garrafa custom
 *   incluída), nunca sobre o frete. Percentual arredonda em 2 casas; valor
 *   fixo no máximo o subtotal.
 * - Uso = pedido com o cupom que não foi cancelado (Pix/boleto pendente
 *   segura o uso; cancelado/expirado libera).
 * - Por cliente: mesmos pedidos, com o mesmo CPF (ship_cpf) ou o mesmo
 *   e-mail (snapshot do pedido ou e-mail da conta), normalizados.
 * - Primeira compra: recusa se houver pedido pago/em andamento com o mesmo
 *   CPF ou e-mail. Antes do checkout só se conhece o e-mail; o CPF entra
 *   no fechamento.
 */
class CouponService
{
    public const SESSAO = 'cupom';

    /** Status de pedido que contam como compra feita (primeira compra). */
    public const STATUS_COMPRA = ['processing', 'paid', 'shipped', 'delivered'];

    public const MOTIVOS = [
        'inexistente'     => 'Cupom não encontrado.',
        'inativo'         => 'Este cupom não está ativo.',
        'agendado'        => 'Este cupom ainda não começou a valer.',
        'expirado'        => 'Este cupom expirou.',
        'minimo'          => 'Este cupom vale para pedidos a partir de :valor em produtos.',
        'esgotado'        => 'Este cupom atingiu o limite de usos.',
        'por_cliente'     => 'Você já usou este cupom.',
        'primeira_compra' => 'Este cupom é só para a primeira compra.',
    ];

    public static function normalizar(?string $codigo): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $codigo));
    }

    public static function emailNormalizado(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    public static function cpfNormalizado(?string $cpf): ?string
    {
        $cpf = preg_replace('/\D+/', '', (string) $cpf);

        return $cpf === '' ? null : $cpf;
    }

    /* ---------------------------------------------------------------
     | Sessão
     --------------------------------------------------------------- */

    public function codigoNaSessao(): ?string
    {
        $codigo = session(self::SESSAO);

        return filled($codigo) ? (string) $codigo : null;
    }

    public function guardar(string $codigo): void
    {
        session([self::SESSAO => self::normalizar($codigo)]);
    }

    public function remover(): void
    {
        session()->forget(self::SESSAO);
    }

    /**
     * Cupom da sessão avaliado contra um subtotal de produtos. Null quando
     * não há cupom na sessão.
     */
    public function daSessao(float $subtotal, ?string $email = null, ?string $cpf = null): ?CupomAvaliacao
    {
        $codigo = $this->codigoNaSessao();

        return $codigo ? $this->avaliar($codigo, $subtotal, $email, $cpf) : null;
    }

    /* ---------------------------------------------------------------
     | Regra
     --------------------------------------------------------------- */

    /**
     * @param  Coupon|null  $coupon  já carregado (ex.: com lockForUpdate no fechamento)
     */
    public function avaliar(string $codigo, float $subtotal, ?string $email = null, ?string $cpf = null, ?Coupon $coupon = null): CupomAvaliacao
    {
        $codigo = self::normalizar($codigo);
        $coupon ??= $this->buscar($codigo);

        if (! $coupon) {
            return CupomAvaliacao::recusado($codigo, 'inexistente');
        }

        $situacao = $coupon->situacao();
        if ($situacao !== 'ativo') {
            return CupomAvaliacao::recusado($coupon->code, $situacao, $coupon);
        }

        if ((float) $coupon->min_order > 0 && round($subtotal, 2) < (float) $coupon->min_order) {
            return CupomAvaliacao::recusado($coupon->code, 'minimo', $coupon);
        }

        if (! is_null($coupon->max_uses) && $coupon->usos()->count() >= $coupon->max_uses) {
            return CupomAvaliacao::recusado($coupon->code, 'esgotado', $coupon);
        }

        $email = self::emailNormalizado($email);
        $cpf = self::cpfNormalizado($cpf);

        if (($email || $cpf) && ! is_null($coupon->max_per_customer)) {
            $usosDoCliente = $this->doCliente($coupon->usos(), $email, $cpf)->count();
            if ($usosDoCliente >= $coupon->max_per_customer) {
                return CupomAvaliacao::recusado($coupon->code, 'por_cliente', $coupon);
            }
        }

        if ($coupon->first_purchase_only && ($email || $cpf)) {
            $comprou = $this->doCliente(Order::query()->whereIn('status', self::STATUS_COMPRA), $email, $cpf)->exists();
            if ($comprou) {
                return CupomAvaliacao::recusado($coupon->code, 'primeira_compra', $coupon);
            }
        }

        return CupomAvaliacao::aplicado($coupon, $this->desconto($coupon, $subtotal));
    }

    public function desconto(Coupon $coupon, float $subtotal): float
    {
        $subtotal = max(0.0, round($subtotal, 2));

        $valor = $coupon->type === 'percent'
            ? round($subtotal * (float) $coupon->value / 100, 2)
            : round((float) $coupon->value, 2);

        return min($valor, $subtotal);
    }

    public function buscar(string $codigo): ?Coupon
    {
        $codigo = self::normalizar($codigo);

        return $codigo === '' ? null : Coupon::where('code', $codigo)->first();
    }

    /** Pedidos do mesmo cliente: CPF do pedido ou e-mail (snapshot ou da conta). */
    protected function doCliente($query, ?string $email, ?string $cpf): Builder|\Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $query->where(function ($q) use ($email, $cpf) {
            if ($cpf) {
                $q->orWhere('ship_cpf', $cpf);
            }
            if ($email) {
                $q->orWhere('customer_email', $email)
                  ->orWhereHas('user', fn ($u) => $u->whereRaw('LOWER(TRIM(email)) = ?', [$email]));
            }
        });
    }
}
