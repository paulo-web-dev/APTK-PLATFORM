<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'cpf',
        'maior_idade_em',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'maior_idade_em'    => 'datetime',
    ];

    /** Já declarou ter 18 anos ou mais (cadastro ou checkout)? */
    public function declarouMaioridade(): bool
    {
        return $this->maior_idade_em !== null;
    }

    /* ---------------- Relações ---------------- */

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /* ---------------- Papéis (role) ---------------- */

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function isB2b(): bool
    {
        return $this->role === 'b2b';
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }
}
