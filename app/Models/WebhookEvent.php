<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WebhookEvent — Tracks incoming webhooks for dedup and audit.
 *
 * FR-ST-002: Webhook registration & event tracking
 * FR-ST-005: Deduplication via external_event_id
 */
class WebhookEvent extends Model
{
    use HasUuids, BelongsToAccount;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'account_id', 'store_id', 'platform', 'event_type',
        'external_event_id', 'external_resource_id',
        'status', 'payload', 'error_message', 'retry_count', 'processed_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'retry_count'  => 'integer',
        'processed_at' => 'datetime',
    ];

    public const STATUS_RECEIVED   = 'received';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED  = 'processed';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_DUPLICATE  = 'duplicate';
    public const STATUS_IGNORED    = 'ignored';

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function markProcessed(): void
    {
        $this->update([
            'status'       => self::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status'        => self::STATUS_FAILED,
            'error_message' => $error,
            'retry_count'   => $this->retry_count + 1,
        ]);
    }

    public function markDuplicate(): void
    {
        $this->update(['status' => self::STATUS_DUPLICATE]);
    }
}
