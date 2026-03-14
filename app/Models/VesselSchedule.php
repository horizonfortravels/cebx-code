<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToAccount;

class VesselSchedule extends Model
{
    use HasUuids, BelongsToAccount;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'account_id', 'vessel_id', 'voyage_number', 'service_route',
        'port_of_loading', 'port_of_loading_name',
        'port_of_discharge', 'port_of_discharge_name',
        'etd', 'eta', 'atd', 'ata', 'cut_off_date',
        'transit_days', 'status', 'port_calls', 'metadata',
    ];

    protected $casts = [
        'etd' => 'datetime', 'eta' => 'datetime',
        'atd' => 'datetime', 'ata' => 'datetime',
        'cut_off_date' => 'datetime',
        'port_calls' => 'json', 'metadata' => 'json',
    ];

    public function vessel() { return $this->belongsTo(Vessel::class); }
    public function containers() { return $this->hasMany(Container::class); }

    public function scopeUpcoming($q) { return $q->where('etd', '>=', now())->orderBy('etd'); }
    public function scopeRoute($q, $pol, $pod) { return $q->where('port_of_loading', $pol)->where('port_of_discharge', $pod); }
}
