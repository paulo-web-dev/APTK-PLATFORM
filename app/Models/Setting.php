<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    /**
     * Lê uma configuração pelo key (com fallback).
     *
     *   Setting::get('store_name', 'APTK');
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Grava ou atualiza uma configuração.
     *
     *   Setting::set('store_name', 'APTK Spirits', 'loja');
     */
    public static function set(string $key, $value, string $group = 'general'): void
    {
        static::query()->updateOrCreate(
            ['key'   => $key],
            ['value' => $value, 'group' => $group],
        );
    }
}
