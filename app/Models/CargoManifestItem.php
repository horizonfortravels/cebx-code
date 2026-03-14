<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CargoManifestItem extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected $casts = ['weight' => 'decimal:3', 'volume' => 'decimal:3', 'quantity' => 'integer'];

    public function manifest() { return $this->belongsTo(CargoManifest::class, 'cargo_manifest_id'); }
    public function shipment() { return $this->belongsTo(Shipment::class); }
}
