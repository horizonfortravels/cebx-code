<?php

namespace App\Models;

use App\Services\Auth\PermissionResolver;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = [
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'user_type' => 'string',
    ];

    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function shipments(): HasMany { return $this->hasMany(Shipment::class); }
    public function tickets(): HasMany { return $this->hasMany(SupportTicket::class); }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role')
            ->withPivot(['assigned_by', 'assigned_at']);
    }

    public function hasPermission(string $permission): bool
    {
        try {
            return app(PermissionResolver::class)->can($this, $permission);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    public function allPermissions(): array
    {
        try {
            return app(PermissionResolver::class)->all($this);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    public function getAllPermissions(): array
    {
        return $this->allPermissions();
    }
}
