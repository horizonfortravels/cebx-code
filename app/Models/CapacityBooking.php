<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CapacityBooking extends Model
{
    use HasUuids, BelongsToAccount;
    protected $guarded = ['id'];
    protected $casts = ['booking_date' => 'date', 'metadata' => 'json'];

    public function capacityPool() { return $this->belongsTo(CapacityPool::class); }
    public function shipment() { return $this->belongsTo(Shipment::class); }
}
