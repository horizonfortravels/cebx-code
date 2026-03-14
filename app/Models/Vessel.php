<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vessel extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    public function containers(): HasMany
    {
        return $this->hasMany(Container::class, 'vessel_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(VesselSchedule::class);
    }

    public function activeSchedules(): HasMany
    {
        return $this->schedules()->whereIn('status', ['scheduled', 'departed', 'in_transit']);
    }
}
