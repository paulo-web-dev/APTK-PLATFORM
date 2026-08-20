<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de toda chamada ao WS B2C (request com credenciais mascaradas).
 */
class MicrovixLog extends Model
{
    protected $fillable = [
        'direction',
        'method',
        'request',
        'response',
        'http_status',
        'success',
        'error',
        'duration_ms',
    ];

    protected $casts = [
        'success' => 'boolean',
    ];
}
