<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StoreSyncLog — Tracks sync operations per store.
 *
 * FR-ST-003: Polling fallback
 * FR-ST-010: Retry/backoff tracking
 */
class StoreSyncLog extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'account_id', 'store_id', 'sync_type', 'status',
        'orders_found', 'orders_imported', 'orders_skipped', 'orders_failed',
        'errors', 'retry_count', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'orders_found'    => 'integer',
        'orders_imported' => 'integer',
        'orders_skipped'  => 'integer',
        'orders_failed'   => 'integer',
        'errors'          => 'array',
        'retry_count'     => 'integer',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
    ];

    public const SYNC_WEBHOOK = 'webhook';
    public const SYNC_POLLING = 'polling';
    public const SYNC_MANUAL  = 'manual';

    public const STATUS_STARTED   = 'started';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_PARTIAL   = 'partial';

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function complete(int $imported, int $skipped, int $failed): void
    {
        $status = $failed > 0 ? ($imported > 0 ? self::STATUS_PARTIAL : self::STATUS_FAILED) : self::STATUS_COMPLETED;

        $this->update([
            'status'          => $status,
            'orders_imported' => $imported,
            'orders_skipped'  => $skipped,
            'orders_failed'   => $failed,
            'completed_at'    => now(),
        ]);
    }
}
