<?php
namespace App\Models;
use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerApiKey extends Model
{
    use HasUuids, BelongsToAccount, SoftDeletes;
    protected $guarded = ['id'];
    protected $casts = [
        'permissions' => 'array',
        'scopes' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
    protected $hidden = ['key_hash'];
}
