<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'status',
        'price',
        'interval',
        'recipient_name',
        'shipping_address',
        'payment_method',
        'started_at',
        'next_renewal_at',
        'paused_at',
        'cancelled_at',
        'notes',
    ];

    protected $casts = [
        'price'           => 'decimal:2',
        'started_at'      => 'datetime',
        'next_renewal_at' => 'date',
        'paused_at'       => 'datetime',
        'cancelled_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function statusLabel(): string
    {
        return [
            'active'    => 'Ativa',
            'paused'    => 'Pausada',
            'cancelled' => 'Cancelada',
        ][$this->status] ?? $this->status;
    }
}
