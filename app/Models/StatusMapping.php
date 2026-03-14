<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * StatusMapping — FR-TR-004/006
 *
 * Configurable carrier→unified status mapping.
 * Controls store notification triggers and exception flagging.
 */
class StatusMapping extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'carrier_code', 'carrier_status', 'carrier_status_code',
        'unified_status', 'unified_description',
        'notify_store', 'store_status',
        'is_terminal', 'is_exception', 'requires_action',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'notify_store'    => 'boolean',
        'is_terminal'     => 'boolean',
        'is_exception'    => 'boolean',
        'requires_action' => 'boolean',
        'is_active'       => 'boolean',
    ];

    /**
     * Look up the unified status for a carrier's raw status.
     */
    public static function resolve(string $carrierCode, string $rawStatus, ?string $statusCode = null): ?self
    {
        $query = self::where('carrier_code', $carrierCode)
            ->where('is_active', true);

        // Try exact code match first
        if ($statusCode) {
            $exact = (clone $query)->where('carrier_status_code', $statusCode)->first();
            if ($exact) return $exact;
        }

        // Fall back to status string match
        return $query->where('carrier_status', $rawStatus)->first();
    }

    public function scopeForCarrier($query, string $carrierCode)
    {
        return $query->where('carrier_code', $carrierCode)->where('is_active', true)->orderBy('sort_order');
    }

    public function scopeStoreNotifiable($query)
    {
        return $query->where('notify_store', true);
    }
}
