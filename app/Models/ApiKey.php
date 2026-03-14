<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * ApiKey — FR-ADM-009
 */
class ApiKey extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'account_id', 'created_by', 'name', 'key_prefix', 'key_hash',
        'scopes', 'allowed_ips', 'last_used_at', 'expires_at', 'revoked_at', 'is_active',
    ];

    protected $casts = [
        'scopes'       => 'array',
        'allowed_ips'  => 'array',
        'is_active'    => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    protected $hidden = ['key_hash'];

    /**
     * Generate a new API key pair.
     */
    public static function generate(string $accountId, string $createdBy, string $name, array $scopes = []): array
    {
        $rawKey = 'sgw_' . Str::random(40);
        $prefix = substr($rawKey, 0, 8);
        $hash = hash('sha256', $rawKey);

        $apiKey = self::create([
            'account_id' => $accountId, 'created_by' => $createdBy,
            'name' => $name, 'key_prefix' => $prefix, 'key_hash' => $hash,
            'scopes' => $scopes, 'is_active' => true,
        ]);

        return ['api_key' => $apiKey, 'raw_key' => $rawKey];
    }

    public static function findByKey(string $rawKey): ?self
    {
        return self::where('key_hash', hash('sha256', $rawKey))->where('is_active', true)->first();
    }

    public function revoke(): void
    {
        $this->update(['is_active' => false, 'revoked_at' => now()]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function hasScope(string $scope): bool
    {
        return !$this->scopes || in_array($scope, $this->scopes);
    }

    public function recordUsage(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function scopeActive($query) { return $query->where('is_active', true); }
}
