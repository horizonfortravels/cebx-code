<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToAccount;

class DeliveryAssignment extends Model
{
    use HasUuids, BelongsToAccount;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'account_id', 'shipment_id', 'driver_id', 'branch_id',
        'assignment_number', 'type', 'status',
        'attempt_number', 'max_attempts',
        'scheduled_at', 'accepted_at', 'picked_up_at', 'delivered_at',
        'failure_reason', 'delivery_notes', 'special_instructions',
        'pickup_lat', 'pickup_lng', 'delivery_lat', 'delivery_lng',
        'distance_km', 'estimated_minutes', 'metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime', 'accepted_at' => 'datetime',
        'picked_up_at' => 'datetime', 'delivered_at' => 'datetime',
        'pickup_lat' => 'decimal:7', 'pickup_lng' => 'decimal:7',
        'delivery_lat' => 'decimal:7', 'delivery_lng' => 'decimal:7',
        'distance_km' => 'decimal:2',
        'metadata' => 'json',
    ];

    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function driver() { return $this->belongsTo(Driver::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function proofOfDelivery() { return $this->hasOne(ProofOfDelivery::class, 'assignment_id'); }

    public static function generateNumber(): string { return 'DLV-' . strtoupper(substr(uniqid(), -8)); }
}
