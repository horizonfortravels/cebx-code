<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ContainerShipment extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'container_id', 'shipment_id', 'packages_count', 'weight',
        'volume_cbm', 'loading_position', 'loaded_at', 'unloaded_at',
    ];

    protected $casts = [
        'weight' => 'decimal:3', 'volume_cbm' => 'decimal:4',
        'loaded_at' => 'datetime', 'unloaded_at' => 'datetime',
    ];

    public function container() { return $this->belongsTo(Container::class); }
    public function shipment() { return $this->belongsTo(Shipment::class); }
}
