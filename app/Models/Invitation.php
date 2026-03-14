<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Schema;

class Invitation extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_sent_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected static array $columnCache = [];

    protected static function booted(): void
    {
        static::saving(function (self $invitation): void {
            $invitation->normalizeLegacySchemaAttributes();
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED || ($this->expires_at?->isPast() ?? false);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canResend(): bool
    {
        return $this->isPending() && !($this->expires_at?->isPast() ?? false);
    }

    public function isUsable(): bool
    {
        return $this->isPending() && !$this->isExpired();
    }

    public function resolvedRole(): ?Role
    {
        $roleId = trim((string) ($this->getAttribute('role_id') ?? ''));
        if ($roleId !== '') {
            return Role::withoutGlobalScopes()->find($roleId);
        }

        $roleName = trim((string) ($this->getAttribute('role_name') ?? ''));
        if ($roleName === '') {
            return null;
        }

        return Role::withoutGlobalScopes()
            ->where('account_id', $this->account_id)
            ->where(function ($query) use ($roleName): void {
                $query->where('name', $roleName)
                    ->orWhere('slug', $roleName)
                    ->orWhere('display_name', $roleName);
            })
            ->first();
    }

    public function resolvedInviter(): ?User
    {
        $invitedBy = trim((string) ($this->getAttribute('invited_by') ?? ''));
        if ($invitedBy === '') {
            return null;
        }

        return User::withoutGlobalScopes()->find($invitedBy);
    }

    private function normalizeLegacySchemaAttributes(): void
    {
        if (!$this->hasTableColumn('role_id')) {
            $roleId = trim((string) ($this->getAttribute('role_id') ?? ''));
            if ($roleId !== '' && $this->hasTableColumn('role_name')) {
                $role = Role::withoutGlobalScopes()->find($roleId);
                $this->attributes['role_name'] = $role?->name ?? $role?->slug ?? $role?->display_name ?? $this->attributes['role_name'] ?? null;
            }

            unset($this->attributes['role_id']);
        }

        foreach (['invited_by', 'accepted_by', 'accepted_at', 'cancelled_at', 'last_sent_at', 'send_count'] as $column) {
            if (!$this->hasTableColumn($column)) {
                unset($this->attributes[$column]);
            }
        }
    }

    private function hasTableColumn(string $column): bool
    {
        $table = $this->getTable();
        $cacheKey = $table . ':' . $column;

        if (!array_key_exists($cacheKey, self::$columnCache)) {
            self::$columnCache[$cacheKey] = Schema::hasColumn($table, $column);
        }

        return self::$columnCache[$cacheKey];
    }
}
