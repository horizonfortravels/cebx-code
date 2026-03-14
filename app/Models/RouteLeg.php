<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class RouteLeg extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected $casts = ['sequence' => 'integer', 'distance_km' => 'decimal:2', 'estimated_hours' => 'decimal:1'];

    public function routePlan() { return $this->belongsTo(RoutePlan::class); }
}
