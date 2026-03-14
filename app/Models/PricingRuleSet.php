<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PricingRuleSet — FR-BRP-001/008
 */
class PricingRuleSet extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'account_id', 'name', 'version', 'status', 'is_default',
        'description', 'activated_at', 'archived_at', 'created_by',
    ];

    protected $casts = [
        'is_default'   => 'boolean',
        'activated_at' => 'datetime',
        'archived_at'  => 'datetime',
    ];

    const STATUS_DRAFT    = 'draft';
    const STATUS_ACTIVE   = 'active';
    const STATUS_ARCHIVED = 'archived';

    public function rules(): HasMany
    {
        return $this->hasMany(PricingRule::class, 'rule_set_id')->orderBy('priority');
    }

    public function activeRules(): HasMany
    {
        return $this->rules()->where('is_active', true);
    }

    public function activate(): void
    {
        // Deactivate other sets for same account
        self::where('account_id', $this->account_id)
            ->where('id', '!=', $this->id)
            ->where('status', self::STATUS_ACTIVE)
            ->update(['status' => self::STATUS_ARCHIVED, 'archived_at' => now()]);

        $this->update(['status' => self::STATUS_ACTIVE, 'activated_at' => now()]);
    }

    public function archive(): void
    {
        $this->update(['status' => self::STATUS_ARCHIVED, 'archived_at' => now()]);
    }

    public function newVersion(): self
    {
        $clone = $this->replicate(['id', 'status', 'activated_at']);
        $clone->version = $this->version + 1;
        $clone->status = self::STATUS_DRAFT;
        $clone->save();

        foreach ($this->rules as $rule) {
            $newRule = $rule->replicate(['id']);
            $newRule->rule_set_id = $clone->id;
            $newRule->save();
        }

        return $clone;
    }

    public static function getActiveForAccount(?string $accountId): ?self
    {
        // Account-specific first, then platform default
        if ($accountId) {
            $set = self::where('account_id', $accountId)->where('status', self::STATUS_ACTIVE)->first();
            if ($set) return $set;
        }
        return self::whereNull('account_id')->where('status', self::STATUS_ACTIVE)->where('is_default', true)->first()
            ?? self::where('status', self::STATUS_ACTIVE)->where('is_default', true)->first();
    }
}
