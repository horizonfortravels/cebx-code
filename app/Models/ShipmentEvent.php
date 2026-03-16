<?php
namespace App\Models;
use App\Support\CanonicalShipmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ShipmentEvent extends Model {
    use HasFactory, HasUuids;

    public const SOURCE_CARRIER = 'carrier';
    public const SOURCE_SYSTEM = 'system';
    public const SOURCE_USER = 'user';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];
    protected $casts = [
        'event_at' => 'datetime',
        'payload' => 'array',
    ];
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }

    /**
     * @return array<string, mixed>
     */
    public function toTimelineItem(): array
    {
        $normalizedStatus = CanonicalShipmentStatus::normalize((string) ($this->normalized_status ?? $this->status ?? ''));

        return [
            'id' => (string) $this->id,
            'event_type' => (string) ($this->event_type ?? 'shipment.updated'),
            'event_type_label' => $this->eventTypeLabel(),
            'status' => $normalizedStatus,
            'status_label' => CanonicalShipmentStatus::label($normalizedStatus),
            'description' => (string) ($this->description ?? $this->eventTypeLabel()),
            'location' => $this->location,
            'event_time' => optional($this->event_at)?->toIso8601String(),
            'source' => (string) ($this->source ?? self::SOURCE_SYSTEM),
            'source_label' => $this->sourceLabel(),
            'correlation_id' => $this->correlation_id,
            'payload' => $this->payload ?? [],
        ];
    }

    public function eventTypeLabel(): string
    {
        return match ((string) $this->event_type) {
            'shipment.purchased' => 'تم إصدار الشحنة لدى الناقل',
            'carrier.documents_available' => 'أصبحت مستندات الشحنة متاحة',
            'tracking.status_updated' => 'تم تحديث حالة التتبع',
            default => (string) ($this->description ?? 'تم تسجيل حدث على الشحنة'),
        };
    }

    public function sourceLabel(): string
    {
        return match ((string) ($this->source ?? self::SOURCE_SYSTEM)) {
            self::SOURCE_CARRIER => 'الناقل',
            self::SOURCE_USER => 'المستخدم',
            default => 'النظام',
        };
    }
}
