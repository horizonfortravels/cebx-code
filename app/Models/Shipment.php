<?php
namespace App\Models;
use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};
class Shipment extends Model {
    use HasFactory, HasUuids, BelongsToAccount;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_KYC_BLOCKED = 'kyc_blocked';
    public const STATUS_READY_FOR_RATES = 'ready_for_rates';
    public const STATUS_RATED = 'rated';
    public const STATUS_OFFER_SELECTED = 'offer_selected';
    public const STATUS_DECLARATION_REQUIRED = 'declaration_required';
    public const STATUS_DECLARATION_COMPLETE = 'declaration_complete';
    public const STATUS_REQUIRES_ACTION = 'requires_action';
    public const STATUS_PAYMENT_PENDING = 'payment_pending';
    public const STATUS_PURCHASED = 'purchased';
    public const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';
    public const STATUS_PICKED_UP = 'picked_up';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_EXCEPTION = 'exception';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';

    public const SOURCE_DIRECT = 'direct';
    public const SOURCE_ORDER = 'order';
    public const SOURCE_BULK = 'bulk';
    public const SOURCE_RETURN = 'return';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];
    protected $casts = [
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'actual_delivery_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'label_created_at' => 'datetime',
        'is_cod' => 'boolean',
        'is_international' => 'boolean',
        'is_insured' => 'boolean',
        'is_return' => 'boolean',
        'has_dangerous_goods' => 'boolean',
        'kyc_verified' => 'boolean',
        'metadata' => 'array',
        'rule_evaluation_log' => 'array',
    ];

    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function parcels(): HasMany { return $this->hasMany(Parcel::class)->orderBy('sequence'); }
    public function events(): HasMany { return $this->hasMany(ShipmentEvent::class)->orderByDesc('event_at'); }
    public function trackingEvents(): HasMany { return $this->hasMany(TrackingEvent::class)->orderByDesc('event_time'); }
    public function items(): HasMany { return $this->hasMany(ShipmentItem::class); }
    public function deliveryAssignment(): HasOne { return $this->hasOne(DeliveryAssignment::class)->latestOfMany(); }
    public function carrierShipment(): HasOne { return $this->hasOne(CarrierShipment::class)->latestOfMany(); }
    public function statusHistory(): HasMany { return $this->hasMany(ShipmentStatusHistory::class)->orderByDesc('created_at'); }
    public function claims(): HasMany { return $this->hasMany(Claim::class); }
    public function carrierDocuments(): HasMany { return $this->hasMany(CarrierDocument::class); }
    public function rateQuote(): BelongsTo { return $this->belongsTo(RateQuote::class, 'rate_quote_id'); }
    public function selectedRateOption(): BelongsTo { return $this->belongsTo(RateOption::class, 'selected_rate_option_id'); }
    public function balanceReservation(): BelongsTo { return $this->belongsTo(WalletHold::class, 'balance_reservation_id'); }
    public function contentDeclaration(): HasOne { return $this->hasOne(ContentDeclaration::class, 'shipment_id'); }

    public static function generateRef(): string {
        return 'SHP-' . date('Y') . str_pad(static::count() + 1, 4, '0', STR_PAD_LEFT);
    }

    public static function generateReference(): string
    {
        return static::generateRef();
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function hasLabel(): bool
    {
        return filled($this->label_url);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_VALIDATED,
            self::STATUS_KYC_BLOCKED,
            self::STATUS_READY_FOR_RATES,
            self::STATUS_RATED,
            self::STATUS_OFFER_SELECTED,
            self::STATUS_DECLARATION_REQUIRED,
            self::STATUS_DECLARATION_COMPLETE,
            self::STATUS_REQUIRES_ACTION,
            self::STATUS_PAYMENT_PENDING,
            self::STATUS_PURCHASED,
            self::STATUS_READY_FOR_PICKUP,
        ], true);
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $allowedTransitions = [
            self::STATUS_DRAFT => [
                self::STATUS_VALIDATED,
                self::STATUS_KYC_BLOCKED,
                self::STATUS_CANCELLED,
                self::STATUS_FAILED,
            ],
            self::STATUS_VALIDATED => [
                self::STATUS_DRAFT,
                self::STATUS_KYC_BLOCKED,
                self::STATUS_READY_FOR_RATES,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_KYC_BLOCKED => [
                self::STATUS_DRAFT,
                self::STATUS_VALIDATED,
                self::STATUS_READY_FOR_RATES,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_READY_FOR_RATES => [
                self::STATUS_RATED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_RATED => [
                self::STATUS_OFFER_SELECTED,
                self::STATUS_DECLARATION_REQUIRED,
                self::STATUS_PAYMENT_PENDING,
                self::STATUS_PURCHASED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_OFFER_SELECTED => [
                self::STATUS_DECLARATION_REQUIRED,
                self::STATUS_DECLARATION_COMPLETE,
                self::STATUS_REQUIRES_ACTION,
                self::STATUS_PAYMENT_PENDING,
                self::STATUS_PURCHASED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_DECLARATION_REQUIRED => [
                self::STATUS_DECLARATION_COMPLETE,
                self::STATUS_REQUIRES_ACTION,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_DECLARATION_COMPLETE => [
                self::STATUS_PAYMENT_PENDING,
                self::STATUS_PURCHASED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_REQUIRES_ACTION => [
                self::STATUS_DECLARATION_REQUIRED,
                self::STATUS_DECLARATION_COMPLETE,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_PAYMENT_PENDING => [
                self::STATUS_PURCHASED,
                self::STATUS_CANCELLED,
                self::STATUS_FAILED,
            ],
            self::STATUS_PURCHASED => [
                self::STATUS_READY_FOR_PICKUP,
                self::STATUS_CANCELLED,
                self::STATUS_FAILED,
            ],
            self::STATUS_READY_FOR_PICKUP => [
                self::STATUS_PICKED_UP,
                self::STATUS_CANCELLED,
                self::STATUS_FAILED,
            ],
            self::STATUS_PICKED_UP => [
                self::STATUS_IN_TRANSIT,
                self::STATUS_EXCEPTION,
            ],
            self::STATUS_IN_TRANSIT => [
                self::STATUS_OUT_FOR_DELIVERY,
                self::STATUS_DELIVERED,
                self::STATUS_RETURNED,
                self::STATUS_EXCEPTION,
            ],
            self::STATUS_OUT_FOR_DELIVERY => [
                self::STATUS_DELIVERED,
                self::STATUS_RETURNED,
                self::STATUS_EXCEPTION,
            ],
            self::STATUS_EXCEPTION => [
                self::STATUS_IN_TRANSIT,
                self::STATUS_RETURNED,
                self::STATUS_CANCELLED,
                self::STATUS_FAILED,
            ],
            self::STATUS_DELIVERED => [
                self::STATUS_RETURNED,
            ],
            self::STATUS_RETURNED => [],
            self::STATUS_CANCELLED => [],
            self::STATUS_FAILED => [],
        ];

        return in_array($newStatus, $allowedTransitions[$this->status] ?? [], true);
    }

    public function recalculateWeights(): void
    {
        $parcels = $this->parcels()->get();
        $actualWeight = round((float) $parcels->sum(fn (Parcel $parcel) => (float) $parcel->weight), 3);
        $volumetricWeight = round((float) $parcels->sum(fn (Parcel $parcel) => (float) ($parcel->volumetric_weight ?? $parcel->calculateVolumetricWeight())), 3);
        $chargeableWeight = max($actualWeight, $volumetricWeight);

        $this->update([
            'total_weight' => $actualWeight,
            'weight' => $actualWeight,
            'volumetric_weight' => $volumetricWeight,
            'chargeable_weight' => $chargeableWeight,
            'parcels_count' => max(1, $parcels->count()),
            'pieces' => max(1, $parcels->count()),
        ]);
    }
}
