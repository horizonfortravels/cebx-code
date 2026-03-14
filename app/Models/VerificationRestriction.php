<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * VerificationRestriction — FR-KYC-004
 */
class VerificationRestriction extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'restriction_key', 'description', 'applies_to_statuses',
        'restriction_type', 'quota_value', 'feature_key', 'is_active',
    ];

    protected $casts = [
        'applies_to_statuses' => 'array',
        'is_active'           => 'boolean',
    ];

    const TYPE_BLOCK_FEATURE = 'block_feature';
    const TYPE_QUOTA_LIMIT   = 'quota_limit';

    /**
     * Check if restriction applies to a given verification status.
     */
    public function appliesTo(string $status): bool
    {
        return in_array($status, $this->applies_to_statuses ?? []);
    }

    /**
     * Get all active restrictions for a status.
     */
    public static function getForStatus(string $status): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('is_active', true)
            ->get()
            ->filter(fn($r) => $r->appliesTo($status));
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
