<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class AuditLog extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var array<string, bool>
     */
    protected static array $columnExistsCache = [];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
    ];

    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';

    public const CATEGORY_AUTH = 'auth';
    public const CATEGORY_USERS = 'users';
    public const CATEGORY_ROLES = 'roles';
    public const CATEGORY_ACCOUNT = 'account';
    public const CATEGORY_INVITATION = 'invitation';
    public const CATEGORY_KYC = 'kyc';
    public const CATEGORY_FINANCIAL = 'financial';
    public const CATEGORY_TRACKING = 'tracking';
    public const CATEGORY_SETTINGS = 'settings';
    public const CATEGORY_EXPORT = 'export';
    public const CATEGORY_SYSTEM = 'system';

    protected static function booted(): void
    {
        static::creating(function (AuditLog $auditLog): void {
            $action = $auditLog->getAttributes()['action'] ?? null;
            $event = $auditLog->getAttributes()['event'] ?? null;

            if (self::auditColumnExists('event') && $event === null && $action !== null) {
                $auditLog->setAttribute('event', $action);
            }

            if (self::auditColumnExists('action') && $action === null && $event !== null) {
                $auditLog->setAttribute('action', $event);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getActionAttribute($value): ?string
    {
        return $value ?? ($this->attributes['event'] ?? null);
    }

    public function getEntityTypeAttribute($value): ?string
    {
        return $value ?? ($this->attributes['auditable_type'] ?? null);
    }

    public function getEntityIdAttribute($value): ?string
    {
        $resolved = $value ?? ($this->attributes['auditable_id'] ?? null);

        return $resolved === null ? null : (string) $resolved;
    }

    /**
     * @return array<int, string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_AUTH,
            self::CATEGORY_USERS,
            self::CATEGORY_ROLES,
            self::CATEGORY_ACCOUNT,
            self::CATEGORY_INVITATION,
            self::CATEGORY_KYC,
            self::CATEGORY_FINANCIAL,
            self::CATEGORY_TRACKING,
            self::CATEGORY_SETTINGS,
            self::CATEGORY_EXPORT,
            self::CATEGORY_SYSTEM,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function severities(): array
    {
        return [
            self::SEVERITY_INFO,
            self::SEVERITY_WARNING,
            self::SEVERITY_CRITICAL,
        ];
    }

    private static function auditColumnExists(string $column): bool
    {
        $cacheKey = 'audit_logs.' . $column;

        if (!array_key_exists($cacheKey, self::$columnExistsCache)) {
            self::$columnExistsCache[$cacheKey] = Schema::hasTable('audit_logs')
                && Schema::hasColumn('audit_logs', $column);
        }

        return self::$columnExistsCache[$cacheKey];
    }
}
