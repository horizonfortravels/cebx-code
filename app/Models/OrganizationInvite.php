<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * OrganizationInvite — FR-ORG-003
 */
class OrganizationInvite extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id', 'invited_by', 'email', 'phone', 'token',
        'role_id', 'membership_role', 'status',
        'expires_at', 'accepted_at', 'cancelled_at', 'resend_count',
    ];

    protected $casts = [
        'expires_at'   => 'datetime',
        'accepted_at'  => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    const STATUS_PENDING   = 'pending';
    const STATUS_ACCEPTED  = 'accepted';
    const STATUS_EXPIRED   = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    const TTL_HOURS = 72;

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function inviter(): BelongsTo { return $this->belongsTo(User::class, 'invited_by'); }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }
    public function isExpired(): bool { return $this->expires_at->isPast(); }

    public function accept(string $userId): void
    {
        if (!$this->isPending()) throw new \RuntimeException('Invite is not pending');
        if ($this->isExpired()) throw new \RuntimeException('Invite has expired');

        $this->update(['status' => self::STATUS_ACCEPTED, 'accepted_at' => now()]);

        OrganizationMember::create([
            'organization_id' => $this->organization_id,
            'user_id'         => $userId,
            'role_id'         => $this->role_id,
            'membership_role' => $this->membership_role,
            'status'          => 'active',
            'joined_at'       => now(),
        ]);
    }

    public function cancel(): void
    {
        if (!$this->isPending()) throw new \RuntimeException('Can only cancel pending invites');
        $this->update(['status' => self::STATUS_CANCELLED, 'cancelled_at' => now()]);
    }

    public function resend(): void
    {
        if (!$this->isPending()) throw new \RuntimeException('Can only resend pending invites');
        $this->update([
            'expires_at'   => now()->addHours(self::TTL_HOURS),
            'resend_count' => $this->resend_count + 1,
        ]);
    }

    public function scopePending($query) { return $query->where('status', self::STATUS_PENDING); }
}
