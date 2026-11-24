<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cupom de desconto. As regras de aplicação moram no CouponService (um
 * lugar só); aqui ficam os campos, a exibição e os pedidos.
 *
 * Datas (starts_at / expires_at) em UTC no banco; o admin digita e lê em
 * America/Sao_Paulo. Desconto sempre sobre os produtos, nunca sobre o frete.
 * used_count é legado: os usos são contados nos pedidos (usos()).
 */
class Coupon extends Model
{
    public const FUSO = 'America/Sao_Paulo';

    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'min_order',
        'max_uses',
        'max_per_customer',
        'used_count',
        'starts_at',
        'expires_at',
        'first_purchase_only',
        'active',
    ];

    protected $casts = [
        'value'               => 'decimal:2',
        'min_order'           => 'decimal:2',
        'max_uses'            => 'integer',
        'max_per_customer'    => 'integer',
        'used_count'          => 'integer',
        'starts_at'           => 'datetime',
        'expires_at'          => 'datetime',
        'first_purchase_only' => 'boolean',
        'active'              => 'boolean',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Pedidos que contam como uso: todo pedido com o cupom que não foi cancelado. */
    public function usos(): HasMany
    {
        return $this->orders()->where('status', '!=', 'cancelled');
    }

    /** "15%" ou "R$ 20,00". */
    public function rotuloValor(): string
    {
        return $this->type === 'percent'
            ? rtrim(rtrim(number_format((float) $this->value, 2, ',', '.'), '0'), ',').'%'
            : moeda_br($this->value);
    }

    /** Situação para o admin: ativo, inativo, agendado ou expirado. */
    public function situacao(): string
    {
        if (! $this->active) {
            return 'inativo';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'agendado';
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'expirado';
        }

        return 'ativo';
    }

    /**
     * Compatibilidade: cupom utilizável agora, sem olhar cliente/carrinho.
     * A regra completa é CouponService::avaliar.
     */
    public function isValid(): bool
    {
        return $this->situacao() === 'ativo'
            && (is_null($this->max_uses) || $this->usos()->count() < $this->max_uses);
    }

    /** Data para o campo do admin: "dd/mm/aaaa hh:mm" em São Paulo. */
    public static function paraCampo(?\DateTimeInterface $data): ?string
    {
        return $data ? \Illuminate\Support\Carbon::instance($data)->setTimezone(self::FUSO)->format('d/m/Y H:i') : null;
    }
}
