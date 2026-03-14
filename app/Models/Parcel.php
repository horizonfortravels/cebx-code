<?php
// ═══════════════════════════════════════════════════════════════════
// This file contains 3 small models combined for efficiency.
// In production, split into separate files.
// ═══════════════════════════════════════════════════════════════════

// ── FILE: app/Models/Parcel.php ──────────────────────────────────
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parcel — FR-SH-003: Multi-parcel shipments.
 */
class Parcel extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'shipment_id', 'sequence', 'weight', 'length', 'width', 'height',
        'volumetric_weight', 'packaging_type', 'description', 'reference',
        'carrier_parcel_id', 'carrier_tracking', 'label_url',
    ];

    protected $casts = [
        'sequence'          => 'integer',
        'weight'            => 'decimal:3',
        'length'            => 'decimal:2',
        'width'             => 'decimal:2',
        'height'            => 'decimal:2',
        'volumetric_weight' => 'decimal:3',
    ];

    public const PACKAGING_BOX      = 'box';
    public const PACKAGING_ENVELOPE = 'envelope';
    public const PACKAGING_TUBE     = 'tube';
    public const PACKAGING_CUSTOM   = 'custom';

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function calculateVolumetricWeight(int $divisor = 5000): float
    {
        if ($this->length && $this->width && $this->height) {
            return round(($this->length * $this->width * $this->height) / $divisor, 3);
        }
        return (float) $this->weight;
    }

    public function chargeableWeight(int $divisor = 5000): float
    {
        return max((float) $this->weight, $this->calculateVolumetricWeight($divisor));
    }
}
