<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ShipmentEvent extends Model {
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];
    protected $casts = ['event_at' => 'datetime'];
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
}
