<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrackingSubscription — FR-TR-004
 *
 * Subscribers (email/SMS/webhook) to shipment status changes.
 */
class TrackingSubscription extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'shipment_id', 'account_id', 'channel', 'destination',
        'subscriber_name', 'event_types', 'language', 'is_active',
        'notifications_sent', 'last_notified_at',
    ];

    protected $casts = [
        'event_types'      => 'array',
        'is_active'        => 'boolean',
        'last_notified_at' => 'datetime',
    ];

    const CHANNEL_EMAIL   = 'email';
    const CHANNEL_SMS     = 'sms';
    const CHANNEL_WEBHOOK = 'webhook';
    const CHANNEL_IN_APP  = 'in_app';

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * Check if subscriber wants this event type.
     */
    public function wantsEvent(string $unifiedStatus): bool
    {
        if (!$this->is_active) return false;
        if (empty($this->event_types)) return true; // null = all events
        return in_array($unifiedStatus, $this->event_types);
    }

    public function recordNotification(): void
    {
        $this->increment('notifications_sent');
        $this->update(['last_notified_at' => now()]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForShipment($query, string $shipmentId)
    {
        return $query->where('shipment_id', $shipmentId)->active();
    }
}
