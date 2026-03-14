<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProofOfDelivery extends Model
{
    use HasUuids;

    protected $table = 'proof_of_deliveries';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'assignment_id', 'shipment_id', 'pod_type',
        'recipient_name', 'recipient_relation', 'recipient_id_number',
        'signature_data', 'otp_code', 'otp_verified',
        'photo_url', 'photo_thumbnail',
        'latitude', 'longitude', 'captured_at', 'notes', 'metadata',
    ];

    protected $casts = [
        'otp_verified' => 'boolean',
        'captured_at' => 'datetime',
        'latitude' => 'decimal:7', 'longitude' => 'decimal:7',
        'metadata' => 'json',
    ];

    public function assignment() { return $this->belongsTo(DeliveryAssignment::class); }
    public function shipment() { return $this->belongsTo(Shipment::class); }
}
