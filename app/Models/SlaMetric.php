<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SlaMetric extends Model
{
    use HasUuids, BelongsToAccount;
    protected $guarded = ['id'];
    protected $casts = ['target_hours' => 'decimal:1', 'actual_hours' => 'decimal:1', 'met' => 'boolean', 'measured_at' => 'datetime'];

    public function shipment() { return $this->belongsTo(Shipment::class); }
}
