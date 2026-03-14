<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\BelongsToAccount;

class CustomsBroker extends Model
{
    use HasUuids, HasFactory, SoftDeletes, BelongsToAccount;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'account_id', 'name', 'license_number', 'country', 'city',
        'phone', 'email', 'company_name', 'commission_rate', 'fixed_fee',
        'currency', 'status', 'rating', 'total_clearances',
        'specializations', 'metadata',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2', 'fixed_fee' => 'decimal:2',
        'rating' => 'decimal:2',
        'specializations' => 'json', 'metadata' => 'json',
    ];

    public function declarations() { return $this->hasMany(CustomsDeclaration::class, 'broker_id'); }
    public function scopeActive($q) { return $q->where('status', 'active'); }
}
