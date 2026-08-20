<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Estado do sync incremental, POR MÉTODO do WS.
 * last_timestamp é o TIMESTAMP do SQL Server (contador, não é tempo).
 */
class MicrovixSyncState extends Model
{
    protected $table = 'microvix_sync_state';

    protected $fillable = [
        'method',
        'last_timestamp',
        'last_run_at',
        'last_error',
        'records_synced',
    ];

    protected $casts = [
        'last_timestamp' => 'integer',
        'last_run_at'    => 'datetime',
    ];
}
