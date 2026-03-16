<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class Notification extends Model
{
    use HasFactory;
    use HasUuids;
    use BelongsToAccount;

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_IN_APP = 'in_app';
    public const CHANNEL_WEBHOOK = 'webhook';
    public const CHANNEL_SLACK = 'slack';

    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_BOUNCED = 'bounced';
    public const STATUS_RETRYING = 'retrying';
    public const STATUS_DLQ = 'dlq';

    public const EVENT_ORDER_CREATED = 'order.created';
    public const EVENT_SHIPMENT_CREATED = 'shipment.created';
    public const EVENT_SHIPMENT_PURCHASED = 'shipment.purchased';
    public const EVENT_SHIPMENT_DOCUMENTS_AVAILABLE = 'shipment.documents_available';
    public const EVENT_LABEL_CREATED = self::EVENT_SHIPMENT_DOCUMENTS_AVAILABLE;
    public const EVENT_SHIPMENT_IN_TRANSIT = 'shipment.in_transit';
    public const EVENT_SHIPMENT_OUT_FOR_DELIVERY = 'shipment.out_for_delivery';
    public const EVENT_SHIPMENT_DELIVERED = 'shipment.delivered';
    public const EVENT_SHIPMENT_EXCEPTION = 'shipment.exception';
    public const EVENT_SHIPMENT_CANCELLED = 'shipment.cancelled';
    public const EVENT_SHIPMENT_RETURNED = 'shipment.returned';

    public const CORE_EVENTS = [
        self::EVENT_SHIPMENT_PURCHASED,
        self::EVENT_SHIPMENT_DOCUMENTS_AVAILABLE,
        self::EVENT_SHIPMENT_IN_TRANSIT,
        self::EVENT_SHIPMENT_OUT_FOR_DELIVERY,
        self::EVENT_SHIPMENT_DELIVERED,
        self::EVENT_SHIPMENT_EXCEPTION,
        self::EVENT_SHIPMENT_CANCELLED,
        self::EVENT_SHIPMENT_RETURNED,
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'event_data' => 'array',
        'provider_response' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'is_batched' => 'boolean',
        'is_throttled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $notification): void {
            if (self::hasColumn('type') && blank($notification->getAttribute('type'))) {
                $notification->setAttribute('type', (string) ($notification->getAttribute('event_type') ?: 'system'));
            }

            if (self::hasColumn('title') && blank($notification->getAttribute('title'))) {
                $notification->setAttribute(
                    'title',
                    (string) (
                        $notification->getAttribute('subject')
                        ?: data_get($notification->getAttribute('event_data'), 'title')
                        ?: $notification->getAttribute('event_type')
                        ?: 'إشعار جديد'
                    )
                );
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeRetryable($query)
    {
        return $query
            ->whereIn('status', [self::STATUS_FAILED, self::STATUS_RETRYING])
            ->whereColumn('retry_count', '<', 'max_retries')
            ->where(function ($inner): void {
                $inner->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    public function markSent(?string $externalId = null): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'external_id' => $externalId,
            'sent_at' => now(),
            'failure_reason' => null,
            'next_retry_at' => null,
        ]);
    }

    public function markDelivered(): void
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    public function markFailed(string $reason): void
    {
        $nextRetryCount = (int) $this->retry_count + 1;
        $maxRetries = max(0, (int) $this->max_retries);

        if ($nextRetryCount >= $maxRetries) {
            $this->update([
                'status' => self::STATUS_DLQ,
                'retry_count' => $nextRetryCount,
                'failure_reason' => $reason,
                'next_retry_at' => null,
            ]);

            return;
        }

        $this->update([
            'status' => self::STATUS_RETRYING,
            'retry_count' => $nextRetryCount,
            'failure_reason' => $reason,
            'next_retry_at' => now()->addMinutes(max(1, 2 ** max(0, $nextRetryCount - 1))),
        ]);
    }

    public function canRetry(): bool
    {
        return (int) $this->retry_count < (int) $this->max_retries
            && $this->status !== self::STATUS_DLQ;
    }

    private static function hasColumn(string $column): bool
    {
        static $cache = [];

        if (! array_key_exists($column, $cache)) {
            $cache[$column] = Schema::hasColumn('notifications', $column);
        }

        return $cache[$column];
    }
}
