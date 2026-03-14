<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * ContentDeclaration — FR-DG-001/002/003/004/007
 *
 * Core record binding a shipment to its DG declaration.
 * Must be completed before label issuance or carrier API call.
 */
class ContentDeclaration extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'account_id', 'shipment_id',
        'contains_dangerous_goods', 'dg_flag_declared', 'status', 'hold_reason',
        'waiver_accepted', 'waiver_version_id', 'waiver_hash_snapshot', 'waiver_text_snapshot', 'waiver_accepted_at',
        'declared_by', 'ip_address', 'user_agent', 'locale', 'declared_at',
    ];

    protected $casts = [
        'contains_dangerous_goods' => 'boolean',
        'dg_flag_declared'        => 'boolean',
        'waiver_accepted'         => 'boolean',
        'declared_at'             => 'datetime',
        'waiver_accepted_at'      => 'datetime',
    ];

    // ── Status Constants ─────────────────────────────────────
    const STATUS_PENDING          = 'pending';
    const STATUS_COMPLETED        = 'completed';
    const STATUS_HOLD_DG          = 'hold_dg';
    const STATUS_REQUIRES_ACTION  = 'requires_action';
    const STATUS_EXPIRED          = 'expired';

    // ── Relationships ────────────────────────────────────────

    public function waiverVersion(): BelongsTo
    {
        return $this->belongsTo(WaiverVersion::class);
    }

    public function dgMetadata(): HasOne
    {
        return $this->hasOne(DgMetadata::class, 'declaration_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(DgAuditLog::class, 'declaration_id');
    }

    // ── FR-DG-002: Set DG flag ──────────────────────────────

    public function setDgFlag(bool $containsDg): void
    {
        $this->contains_dangerous_goods = $containsDg;
        $this->dg_flag_declared = true;

        if ($containsDg) {
            // FR-DG-003: Block in MVP
            $this->status = self::STATUS_HOLD_DG;
            $this->hold_reason = 'المواد الخطرة غير مدعومة في الإصدار الحالي. يرجى التواصل مع الدعم أو اختيار خدمة خاصة.';
        } else {
            $this->status = self::STATUS_PENDING;
            $this->hold_reason = null;
        }

        $this->save();
    }

    // ── FR-DG-004: Accept waiver ────────────────────────────

    public function acceptWaiver(WaiverVersion $waiverVersion): void
    {
        $this->waiver_accepted        = true;
        $this->waiver_version_id      = $waiverVersion->id;
        $this->waiver_hash_snapshot   = $waiverVersion->waiver_hash;
        $this->waiver_text_snapshot   = $waiverVersion->waiver_text;
        $this->waiver_accepted_at     = now();

        if (!$this->contains_dangerous_goods) {
            $this->status = self::STATUS_COMPLETED;
        }

        $this->save();
    }

    // ── FR-DG-007: Check if ready for carrier call ──────────

    public function isReadyForIssuance(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && $this->waiver_accepted;
    }

    public function isBlocked(): bool
    {
        return in_array($this->status, [self::STATUS_HOLD_DG, self::STATUS_REQUIRES_ACTION]);
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopeForShipment($query, string $shipmentId)
    {
        return $query->where('shipment_id', $shipmentId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeBlocked($query)
    {
        return $query->whereIn('status', [self::STATUS_HOLD_DG, self::STATUS_REQUIRES_ACTION]);
    }

    // ── FR-DG-008: Sanitized view for unauthorized roles ────

    public function toSummaryArray(): array
    {
        return [
            'id'                        => $this->id,
            'shipment_id'               => $this->shipment_id,
            'contains_dangerous_goods'  => $this->contains_dangerous_goods,
            'dg_flag_declared'          => $this->dg_flag_declared,
            'status'                    => $this->status,
            'waiver_accepted'           => $this->waiver_accepted,
            'declared_at'               => $this->declared_at?->toISOString(),
        ];
    }

    public function toDetailArray(): array
    {
        return array_merge($this->toSummaryArray(), [
            'hold_reason'            => $this->hold_reason,
            'waiver_version_id'      => $this->waiver_version_id,
            'waiver_hash_snapshot'   => $this->waiver_hash_snapshot,
            'ip_address'             => $this->ip_address,
            'user_agent'             => $this->user_agent,
            'locale'                 => $this->locale,
            'declared_by'            => $this->declared_by,
        ]);
    }
}
