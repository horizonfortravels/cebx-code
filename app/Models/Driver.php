<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereNotIn('status', ['delivered', 'failed', 'returned', 'cancelled']);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function getSuccessRate(): float
    {
        $total = (int) ($this->total_deliveries ?? $this->deliveries_count ?? 0);
        $success = (int) ($this->successful_deliveries ?? $this->deliveries_count ?? 0);

        if ($total <= 0) {
            return 100.0;
        }

        return round(($success / $total) * 100, 1);
    }
}
