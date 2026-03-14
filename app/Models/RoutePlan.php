<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class RoutePlan extends Model
{
    use HasUuids, BelongsToAccount;
    protected $guarded = ['id'];
    protected $casts = ['total_distance_km' => 'decimal:2', 'total_hours' => 'decimal:1', 'metadata' => 'json'];

    public function legs() { return $this->hasMany(RouteLeg::class); }
    public function shipment() { return $this->belongsTo(Shipment::class); }
}
