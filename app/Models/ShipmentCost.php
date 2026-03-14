<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ShipmentCost extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected $casts = ['carrier_cost' => 'decimal:2', 'customer_price' => 'decimal:2', 'margin' => 'decimal:2', 'tax' => 'decimal:2', 'insurance' => 'decimal:2', 'surcharges' => 'json'];

    public function shipment() { return $this->belongsTo(Shipment::class); }
}
