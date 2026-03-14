<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class RouteCostFactor extends Model
{
    use HasUuids, BelongsToAccount;
    protected $guarded = ['id'];
    protected $casts = ['base_cost' => 'decimal:2', 'fuel_surcharge' => 'decimal:4', 'customs_cost' => 'decimal:2', 'metadata' => 'json'];
}
