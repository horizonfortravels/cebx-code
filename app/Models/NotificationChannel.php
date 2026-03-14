<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * NotificationChannel — FR-NTF-002/009
 *
 * Account-level channel configuration (email provider, SMS, Slack, webhooks).
 */
class NotificationChannel extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'account_id', 'channel', 'provider', 'name',
        'config', 'webhook_url', 'webhook_secret',
        'is_active', 'is_verified', 'verified_at',
    ];

    protected $casts = [
        'config'      => 'encrypted:array',
        'is_active'   => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    protected $hidden = ['config', 'webhook_secret'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel)->active();
    }
}
