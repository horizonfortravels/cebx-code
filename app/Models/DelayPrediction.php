<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DelayPrediction extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected $casts = ['predicted_delay_hours' => 'decimal:1', 'confidence' => 'decimal:4', 'factors' => 'json', 'predicted_at' => 'datetime'];

    public function shipment() { return $this->belongsTo(Shipment::class); }
}
