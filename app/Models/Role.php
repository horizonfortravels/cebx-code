<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Traits\BelongsToAccount;

class Role extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToAccount;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'account_id',
        'name',
        'slug',
        'display_name',
        'description',
        'is_system',
        'template',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission')
                    ->withPivot('granted_at');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_role')
                    ->withPivot(['assigned_by', 'assigned_at']);
    }

    // ─── Helpers ──────────────────────────────────────────────────

    public function hasPermission(string $permissionKey): bool
    {
        return $this->permissions->contains('key', $permissionKey);
    }

    public function grantPermissions(array $permissionIds): void
    {
        $this->permissions()->syncWithoutDetaching(
            collect($permissionIds)->mapWithKeys(fn ($id) => [
                $id => ['granted_at' => now()]
            ])->toArray()
        );
    }

    public function revokePermissions(array $permissionIds): void
    {
        $this->permissions()->detach($permissionIds);
    }

    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync(
            collect($permissionIds)->mapWithKeys(fn ($id) => [
                $id => ['granted_at' => now()]
            ])->toArray()
        );
    }
}
