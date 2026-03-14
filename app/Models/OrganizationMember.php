<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OrganizationMember — FR-ORG-003/005/006/007
 */
class OrganizationMember extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id', 'user_id', 'role_id', 'membership_role', 'status',
        'can_view_financial',
        'joined_at', 'suspended_at', 'suspended_reason',
    ];

    protected $casts = [
        'can_view_financial'  => 'boolean',
        'joined_at'           => 'datetime',
        'suspended_at'        => 'datetime',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function role(): BelongsTo { return $this->belongsTo(Role::class); }

    public function isOwner(): bool { return $this->membership_role === 'owner'; }
    public function isAdmin(): bool { return in_array($this->membership_role, ['owner', 'admin']); }
    public function isActive(): bool { return $this->status === 'active'; }

    public function suspend(string $reason): void
    {
        $this->update([
            'status' => 'suspended', 'suspended_at' => now(), 'suspended_reason' => $reason,
        ]);
    }

    public function activate(): void
    {
        $this->update(['status' => 'active', 'suspended_at' => null, 'suspended_reason' => null]);
    }

    public function remove(): void
    {
        $this->update(['status' => 'removed']);
    }

    /**
     * FR-ORG-006: Check unified permission across UI/API/Export.
     */
    public function hasPermission(string $permissionKey): bool
    {
        // Owner has all permissions
        if ($this->isOwner()) return true;

        // Check role-based permissions
        if ($this->role && $this->role->permissions) {
            return $this->role->permissions->contains('key', $permissionKey);
        }

        return false;
    }

    public function scopeActive($query) { return $query->where('status', 'active'); }
}
