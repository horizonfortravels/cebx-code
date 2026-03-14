<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RouteSuggestion extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'shipment_id', 'rank', 'carrier_code', 'service_code', 'transport_mode',
        'route_legs', 'estimated_days', 'estimated_cost', 'currency',
        'reliability_score', 'carbon_footprint_kg',
        'is_recommended', 'is_selected', 'metadata',
    ];

    protected $casts = [
        'route_legs' => 'json', 'estimated_cost' => 'decimal:2',
        'reliability_score' => 'decimal:2', 'carbon_footprint_kg' => 'decimal:2',
        'is_recommended' => 'boolean', 'is_selected' => 'boolean',
        'metadata' => 'json',
    ];

    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function scopeRecommended($q) { return $q->where('is_recommended', true); }
}
