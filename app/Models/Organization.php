<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Organization — FR-ORG-001/002/008
 */
class Organization extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'account_id', 'legal_name', 'trade_name', 'registration_number',
        'tax_number', 'country_code', 'billing_address', 'billing_email',
        'phone', 'website', 'logo_path',
        'verification_status', 'verified_at', 'rejection_reason',
        'default_currency', 'timezone', 'locale',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    const STATUS_UNVERIFIED     = 'unverified';
    const STATUS_PENDING_REVIEW = 'pending_review';
    const STATUS_VERIFIED       = 'verified';
    const STATUS_REJECTED       = 'rejected';

    public function members(): HasMany { return $this->hasMany(OrganizationMember::class); }
    public function invites(): HasMany { return $this->hasMany(OrganizationInvite::class); }
    public function wallet(): HasOne { return $this->hasOne(OrganizationWallet::class); }

    public function activeMembers(): HasMany
    {
        return $this->hasMany(OrganizationMember::class)->where('status', 'active');
    }

    public function isVerified(): bool { return $this->verification_status === self::STATUS_VERIFIED; }

    public function verify(): void
    {
        $this->update(['verification_status' => self::STATUS_VERIFIED, 'verified_at' => now()]);
    }

    public function reject(string $reason): void
    {
        $this->update(['verification_status' => self::STATUS_REJECTED, 'rejection_reason' => $reason]);
    }

    public function getOwner(): ?OrganizationMember
    {
        return $this->members()->where('membership_role', 'owner')->first();
    }
}
