<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * TrackingWebhook — FR-TR-001/002
 *
 * Logs every inbound tracking webhook for security audit and debugging.
 */
class TrackingWebhook extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'carrier_code', 'signature', 'signature_valid', 'message_reference',
        'replay_token', 'source_ip', 'user_agent', 'headers',
        'event_type', 'tracking_number', 'payload', 'payload_size',
        'status', 'rejection_reason', 'events_extracted', 'processing_time_ms',
    ];

    protected $casts = [
        'headers'         => 'array',
        'payload'         => 'array',
        'signature_valid' => 'boolean',
    ];

    const STATUS_RECEIVED  = 'received';
    const STATUS_VALIDATED = 'validated';
    const STATUS_PROCESSED = 'processed';
    const STATUS_REJECTED  = 'rejected';
    const STATUS_FAILED    = 'failed';

    public function markValidated(): void
    {
        $this->update(['status' => self::STATUS_VALIDATED, 'signature_valid' => true]);
    }

    public function markRejected(string $reason): void
    {
        $this->update(['status' => self::STATUS_REJECTED, 'rejection_reason' => $reason, 'signature_valid' => false]);
    }

    public function markProcessed(int $eventsCount, int $processingMs): void
    {
        $this->update([
            'status'             => self::STATUS_PROCESSED,
            'events_extracted'   => $eventsCount,
            'processing_time_ms' => $processingMs,
        ]);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }
}
