<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ShipmentException — FR-TR-007
 *
 * Exception management: "Requires Action" with reasons & suggested actions.
 */
class ShipmentException extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'shipment_id', 'tracking_event_id', 'account_id',
        'exception_code', 'reason', 'carrier_reason', 'suggested_action',
        'status', 'resolution_notes', 'resolved_by',
        'resolved_at', 'acknowledged_at', 'escalated_at',
        'priority', 'requires_customer_action',
    ];

    protected $casts = [
        'requires_customer_action' => 'boolean',
        'resolved_at'              => 'datetime',
        'acknowledged_at'          => 'datetime',
        'escalated_at'             => 'datetime',
    ];

    const STATUS_OPEN         = 'open';
    const STATUS_ACKNOWLEDGED = 'acknowledged';
    const STATUS_IN_PROGRESS  = 'in_progress';
    const STATUS_RESOLVED     = 'resolved';
    const STATUS_ESCALATED    = 'escalated';
    const STATUS_CLOSED       = 'closed';

    const PRIORITY_LOW      = 'low';
    const PRIORITY_MEDIUM   = 'medium';
    const PRIORITY_HIGH     = 'high';
    const PRIORITY_CRITICAL = 'critical';

    // ── Exception Codes & Suggested Actions ──────────────────
    const EXCEPTION_MAP = [
        'DELIVERY_FAILED'       => ['priority' => 'high',   'action' => 'Verify recipient address and phone. Contact carrier for re-delivery attempt.'],
        'ADDRESS_ISSUE'         => ['priority' => 'high',   'action' => 'Update the delivery address and request re-delivery.'],
        'CUSTOMS_HOLD'          => ['priority' => 'medium', 'action' => 'Provide missing customs documentation or pay required duties.'],
        'DAMAGED_PACKAGE'       => ['priority' => 'critical','action' => 'File a claim with the carrier. Contact customer about replacement.'],
        'REFUSED_BY_RECIPIENT'  => ['priority' => 'medium', 'action' => 'Contact recipient to confirm refusal. Process return if confirmed.'],
        'MISSING_DOCUMENTATION' => ['priority' => 'medium', 'action' => 'Upload the required documents via shipment management.'],
        'SECURITY_HOLD'         => ['priority' => 'high',   'action' => 'Contact carrier security department for clearance.'],
        'WEATHER_DELAY'         => ['priority' => 'low',    'action' => 'No action required. Delivery will resume when conditions improve.'],
        'CAPACITY_DELAY'        => ['priority' => 'low',    'action' => 'No action required. Carrier capacity constraints are temporary.'],
        'OTHER'                 => ['priority' => 'medium', 'action' => 'Review carrier details and contact support if needed.'],
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function trackingEvent(): BelongsTo
    {
        return $this->belongsTo(TrackingEvent::class);
    }

    /**
     * Create from a tracking event using exception map.
     */
    public static function fromTrackingEvent(
        TrackingEvent $event,
        string $exceptionCode = 'OTHER',
        ?string $carrierReason = null
    ): self {
        $mapped = self::EXCEPTION_MAP[$exceptionCode] ?? self::EXCEPTION_MAP['OTHER'];

        return self::create([
            'shipment_id'        => $event->shipment_id,
            'tracking_event_id'  => $event->id,
            'account_id'         => $event->account_id,
            'exception_code'     => $exceptionCode,
            'reason'             => $event->raw_description ?? $event->unified_description ?? 'Shipment exception occurred',
            'carrier_reason'     => $carrierReason ?? $event->raw_description,
            'suggested_action'   => $mapped['action'],
            'priority'           => $mapped['priority'],
            'requires_customer_action' => in_array($exceptionCode, ['ADDRESS_ISSUE', 'CUSTOMS_HOLD', 'MISSING_DOCUMENTATION']),
        ]);
    }

    public function acknowledge(): void
    {
        $this->update(['status' => self::STATUS_ACKNOWLEDGED, 'acknowledged_at' => now()]);
    }

    public function resolve(string $notes, string $resolvedBy): void
    {
        $this->update([
            'status'           => self::STATUS_RESOLVED,
            'resolution_notes' => $notes,
            'resolved_by'      => $resolvedBy,
            'resolved_at'      => now(),
        ]);
    }

    public function escalate(): void
    {
        $this->update(['status' => self::STATUS_ESCALATED, 'escalated_at' => now(), 'priority' => self::PRIORITY_CRITICAL]);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED, self::STATUS_IN_PROGRESS]);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED, self::STATUS_IN_PROGRESS]);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }
}
