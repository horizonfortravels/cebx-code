<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RateQuote — FR-RT-001/005/006/007: Rate quote with options and TTL.
 */
class RateQuote extends Model
{
    use HasUuids, HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'account_id', 'shipment_id',
        'origin_country', 'origin_city', 'destination_country', 'destination_city',
        'total_weight', 'chargeable_weight', 'parcels_count',
        'is_cod', 'cod_amount', 'is_insured', 'insurance_value', 'currency',
        'status', 'options_count', 'expires_at', 'is_expired',
        'selected_option_id', 'correlation_id',
        'request_metadata', 'error_message', 'requested_by',
    ];

    protected $casts = [
        'total_weight'      => 'decimal:3',
        'chargeable_weight' => 'decimal:3',
        'parcels_count'     => 'integer',
        'options_count'     => 'integer',
        'is_cod'            => 'boolean',
        'cod_amount'        => 'decimal:2',
        'is_insured'        => 'boolean',
        'insurance_value'   => 'decimal:2',
        'is_expired'        => 'boolean',
        'expires_at'        => 'datetime',
        'request_metadata'  => 'array',
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_SELECTED  = 'selected';

    public const DEFAULT_TTL_MINUTES = 15;

    // ─── Relationships ───────────────────────────────────────────

    public function account(): BelongsTo  { return $this->belongsTo(Account::class); }
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }

    public function options(): HasMany
    {
        return $this->hasMany(RateOption::class)->orderBy('retail_rate');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(RateOption::class, 'selected_option_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function isExpired(): bool
    {
        if ($this->is_expired) return true;
        if ($this->expires_at && now()->gt($this->expires_at)) {
            $this->update(['is_expired' => true, 'status' => self::STATUS_EXPIRED]);
            return true;
        }
        return false;
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_COMPLETED && !$this->isExpired();
    }

    public function cheapestOption(): ?RateOption
    {
        return $this->options->where('is_available', true)->sortBy('retail_rate')->first();
    }

    public function fastestOption(): ?RateOption
    {
        return $this->options->where('is_available', true)->sortBy('estimated_days_min')->first();
    }

    public function scopeValid($q)
    {
        return $q->where('status', self::STATUS_COMPLETED)
                 ->where('is_expired', false)
                 ->where('expires_at', '>', now());
    }
}
