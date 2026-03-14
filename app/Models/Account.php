<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class Account extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    public function users(): HasMany { return $this->hasMany(User::class); }
    public function shipments(): HasMany { return $this->hasMany(Shipment::class); }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
    public function stores(): HasMany { return $this->hasMany(Store::class); }
    public function wallet(): HasOne { return $this->hasOne(Wallet::class); }
    public function addresses(): HasMany { return $this->hasMany(Address::class); }
    public function tickets(): HasMany { return $this->hasMany(SupportTicket::class); }
    public function notifications(): HasMany { return $this->hasMany(Notification::class); }
    public function invitations(): HasMany { return $this->hasMany(Invitation::class); }
    public function claims(): HasMany { return $this->hasMany(Claim::class); }
    public function kycRequests(): HasMany { return $this->hasMany(KycRequest::class); }
    public function organizationProfile(): HasOne { return $this->hasOne(OrganizationProfile::class); }
    public function kycVerification(): HasOne { return $this->hasOne(KycVerification::class)->latestOfMany(); }
    public function kycVerifications(): HasMany { return $this->hasMany(KycVerification::class); }

    public function isIndividual(): bool
    {
        return ($this->type ?? 'individual') === 'individual';
    }

    public function isOrganization(): bool
    {
        return ($this->type ?? '') === 'organization';
    }

    public function allowsTeamManagement(): bool
    {
        return $this->isOrganization();
    }

    public function externalUserCount(): int
    {
        $query = $this->users()->withoutGlobalScopes();

        if (Schema::hasColumn('users', 'user_type')) {
            $query->where('user_type', 'external');
        }

        return $query->count();
    }

    public function hasActiveUsage(): bool
    {
        if ($this->externalUserCount() > 1) {
            return true;
        }

        foreach ([
            $this->shipments(),
            $this->orders(),
            $this->stores(),
            $this->invitations(),
            $this->claims(),
            $this->kycRequests(),
        ] as $relation) {
            if ($relation->exists()) {
                return true;
            }
        }

        return false;
    }
}
