<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CarrierShipment — FR-CR-001/003/006
 *
 * Represents the carrier-side record of a shipment.
 * Tracks DHL API creation, idempotency, and cancellation.
 */
class CarrierShipment extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected static function booted(): void
    {
        static::creating(function (self $carrierShipment): void {
            if (trim((string) $carrierShipment->carrier_code) === '') {
                throw new InvalidArgumentException('CarrierShipment requires an explicit carrier_code.');
            }

            if (trim((string) $carrierShipment->carrier_name) === '') {
                throw new InvalidArgumentException('CarrierShipment requires an explicit carrier_name.');
            }
        });
    }

    protected $fillable = [
        'shipment_id', 'account_id', 'carrier_code', 'carrier_name',
        'carrier_shipment_id', 'tracking_number', 'awb_number',
        'dispatch_confirmation_number', 'service_code', 'service_name',
        'product_code', 'status', 'idempotency_key', 'attempt_count',
        'last_attempt_at', 'label_format', 'label_size',
        'is_cancellable', 'cancellation_deadline', 'cancellation_id',
        'cancellation_reason', 'cancelled_at',
        'request_payload', 'response_payload', 'carrier_metadata',
        'correlation_id',
    ];

    protected $casts = [
        'request_payload'       => 'array',
        'response_payload'      => 'array',
        'carrier_metadata'      => 'array',
        'is_cancellable'        => 'boolean',
        'last_attempt_at'       => 'datetime',
        'cancellation_deadline' => 'datetime',
        'cancelled_at'          => 'datetime',
    ];

    // ── Status Constants ─────────────────────────────────────
    const STATUS_PENDING       = 'pending';
    const STATUS_CREATING      = 'creating';
    const STATUS_CREATED       = 'created';
    const STATUS_LABEL_PENDING = 'label_pending';
    const STATUS_LABEL_READY   = 'label_ready';
    const STATUS_CANCEL_PENDING = 'cancel_pending';
    const STATUS_CANCELLED     = 'cancelled';
    const STATUS_CANCEL_FAILED = 'cancel_failed';
    const STATUS_FAILED        = 'failed';

    // ── Carrier Constants ────────────────────────────────────
    const CARRIER_DHL = 'dhl';
    const CARRIER_FEDEX = 'fedex';

    // ── Label Formats ────────────────────────────────────────
    const FORMAT_PDF = 'pdf';
    const FORMAT_ZPL = 'zpl';
    const FORMAT_PNG = 'png';
    const FORMAT_EPL = 'epl';

    // ── Relationships ────────────────────────────────────────

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CarrierDocument::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(CarrierError::class);
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * FR-CR-003: Generate idempotency key for this shipment.
     */
    public static function generateIdempotencyKey(string $shipmentId, string $operation = 'create'): string
    {
        return hash('sha256', "{$shipmentId}:{$operation}:" . date('Y-m-d'));
    }

    /**
     * Check if the carrier shipment can be cancelled (FR-CR-006).
     */
    public function canCancel(): bool
    {
        if (!$this->is_cancellable) {
            return false;
        }

        if (in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_CANCEL_PENDING, self::STATUS_FAILED, self::STATUS_PENDING])) {
            return false;
        }

        if ($this->cancellation_deadline && now()->isAfter($this->cancellation_deadline)) {
            return false;
        }

        return in_array($this->status, [self::STATUS_CREATED, self::STATUS_LABEL_READY, self::STATUS_LABEL_PENDING]);
    }

    /**
     * Check if creation was successful.
     */
    public function isCreated(): bool
    {
        return in_array($this->status, [
            self::STATUS_CREATED, self::STATUS_LABEL_PENDING, self::STATUS_LABEL_READY,
        ]);
    }

    /**
     * Check if label is available.
     */
    public function hasLabel(): bool
    {
        return $this->status === self::STATUS_LABEL_READY;
    }

    /**
     * Check if we should retry (FR-CR-003).
     */
    public function canRetry(int $maxRetries = 3): bool
    {
        return $this->status === self::STATUS_FAILED && $this->attempt_count < $maxRetries;
    }

    /**
     * Increment attempt counter.
     */
    public function incrementAttempt(): void
    {
        $this->increment('attempt_count');
        $this->update(['last_attempt_at' => now()]);
    }

    /**
     * Get the label document.
     */
    public function getLabel()
    {
        return $this->documents()->where('type', 'label')->first();
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeByCarrier($query, string $carrierCode)
    {
        return $query->where('carrier_code', $carrierCode);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeRetriable($query, int $maxRetries = 3)
    {
        return $query->where('status', self::STATUS_FAILED)
                     ->where('attempt_count', '<', $maxRetries);
    }
}
