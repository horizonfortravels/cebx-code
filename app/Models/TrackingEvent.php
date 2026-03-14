<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * TrackingEvent — FR-TR-001/003/005
 *
 * Stores every tracking event received from carriers (raw + normalized).
 * Supports deduplication, ordering, and timeline display.
 */
class TrackingEvent extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'shipment_id', 'account_id', 'carrier_code', 'tracking_number',
        'raw_status', 'raw_description', 'raw_status_code',
        'unified_status', 'unified_description',
        'event_time', 'location_city', 'location_country',
        'location_code', 'location_description', 'signatory',
        'source', 'dedup_key', 'sequence_number', 'webhook_id',
        'is_processed', 'notified_store', 'notified_subscribers',
        'is_exception', 'raw_payload',
    ];

    protected $casts = [
        'event_time'            => 'datetime',
        'is_processed'          => 'boolean',
        'notified_store'        => 'boolean',
        'notified_subscribers'  => 'boolean',
        'is_exception'          => 'boolean',
        'raw_payload'           => 'array',
    ];

    // ── Unified Status Constants ─────────────────────────────
    const STATUS_LABEL_CREATED     = 'label_created';
    const STATUS_PICKED_UP         = 'picked_up';
    const STATUS_IN_TRANSIT        = 'in_transit';
    const STATUS_OUT_FOR_DELIVERY  = 'out_for_delivery';
    const STATUS_DELIVERED         = 'delivered';
    const STATUS_EXCEPTION         = 'exception';
    const STATUS_RETURNED          = 'returned';
    const STATUS_ON_HOLD           = 'on_hold';
    const STATUS_CUSTOMS           = 'customs';
    const STATUS_CUSTOMS_RELEASED  = 'customs_released';
    const STATUS_FAILED_ATTEMPT    = 'failed_attempt';
    const STATUS_CANCELLED         = 'cancelled';
    const STATUS_LOST              = 'lost';
    const STATUS_UNKNOWN           = 'unknown';

    const TERMINAL_STATUSES = [
        self::STATUS_DELIVERED,
        self::STATUS_RETURNED,
        self::STATUS_CANCELLED,
        self::STATUS_LOST,
    ];

    const EXCEPTION_STATUSES = [
        self::STATUS_EXCEPTION,
        self::STATUS_FAILED_ATTEMPT,
        self::STATUS_ON_HOLD,
    ];

    // ── Source Constants ──────────────────────────────────────
    const SOURCE_WEBHOOK = 'webhook';
    const SOURCE_POLLING = 'polling';
    const SOURCE_MANUAL  = 'manual';
    const SOURCE_API     = 'api';

    // ── Relationships ────────────────────────────────────────

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function exception(): HasOne
    {
        return $this->hasOne(ShipmentException::class);
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * FR-TR-003: Generate dedup key for this event.
     */
    public static function generateDedupKey(
        string $trackingNumber,
        string $rawStatus,
        string $eventTime,
        ?string $locationCode = null
    ): string {
        $parts = "{$trackingNumber}:{$rawStatus}:{$eventTime}";
        if ($locationCode) {
            $parts .= ":{$locationCode}";
        }
        return hash('sha256', $parts);
    }

    /**
     * Check if this is a terminal event.
     */
    public function isTerminal(): bool
    {
        return in_array($this->unified_status, self::TERMINAL_STATUSES);
    }

    /**
     * Check if this is an exception event.
     */
    public function isException(): bool
    {
        return in_array($this->unified_status, self::EXCEPTION_STATUSES) || $this->is_exception;
    }

    /**
     * Format for timeline display (FR-TR-005).
     */
    public function toTimeline(): array
    {
        return [
            'id'               => $this->id,
            'status'           => $this->unified_status,
            'description'      => $this->unified_description ?? $this->raw_description,
            'event_time'       => $this->event_time->toIso8601String(),
            'location'         => trim("{$this->location_city}, {$this->location_country}", ', ') ?: null,
            'location_city'    => $this->location_city,
            'location_country' => $this->location_country,
            'source'           => $this->source,
            'is_exception'     => $this->is_exception,
            'signatory'        => $this->signatory,
        ];
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeForShipment($query, string $shipmentId)
    {
        return $query->where('shipment_id', $shipmentId)->orderBy('event_time', 'desc');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('unified_status', $status);
    }

    public function scopeExceptions($query)
    {
        return $query->where('is_exception', true);
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('is_processed', false);
    }

    public function scopeTimeline($query, string $shipmentId)
    {
        return $query->where('shipment_id', $shipmentId)->orderBy('event_time', 'asc');
    }
}
