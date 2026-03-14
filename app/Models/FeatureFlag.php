<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FeatureFlag — FR-ADM-010
 */
class FeatureFlag extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'key', 'name', 'description', 'is_enabled',
        'rollout_percentage', 'target_accounts', 'target_plans', 'created_by',
    ];

    protected $casts = [
        'is_enabled'         => 'boolean',
        'rollout_percentage' => 'integer',
        'target_accounts'    => 'array',
        'target_plans'       => 'array',
    ];

    public function isEnabledFor(?string $accountId = null, ?string $planSlug = null): bool
    {
        if (!$this->is_enabled) return false;

        // Check account targeting
        if ($this->target_accounts && $accountId && !in_array($accountId, $this->target_accounts)) {
            return false;
        }

        // Check plan targeting
        if ($this->target_plans && $planSlug && !in_array($planSlug, $this->target_plans)) {
            return false;
        }

        // Rollout percentage
        if ($this->rollout_percentage < 100 && $accountId) {
            $hash = crc32($this->key . $accountId);
            return ($hash % 100) < $this->rollout_percentage;
        }

        return true;
    }

    public static function isEnabled(string $key, ?string $accountId = null): bool
    {
        $flag = self::where('key', $key)->first();
        return $flag ? $flag->isEnabledFor($accountId) : false;
    }
}
