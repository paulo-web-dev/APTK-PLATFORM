<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'kicker',
        'price',
        'price_label',
        'interval',
        'perks',
        'featured',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'perks'      => 'array',
        'featured'   => 'boolean',
        'active'     => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** Tem preço → vende sozinho (checkout). Sem preço → só contato (ex.: Corporativo). */
    public function isSelfServe(): bool
    {
        return $this->price !== null;
    }

    /** Preço formatado para exibição (ou rótulo "Sob consulta"). */
    public function priceDisplay(): string
    {
        return $this->price !== null
            ? 'R$ '.number_format((float) $this->price, 0, ',', '.')
            : ($this->price_label ?: 'Sob consulta');
    }

    /** Sufixo do ciclo de cobrança. */
    public function intervalLabel(): string
    {
        return match ($this->interval) {
            'quarterly' => '/trimestre',
            'yearly'    => '/ano',
            default     => '/mês',
        };
    }
}
