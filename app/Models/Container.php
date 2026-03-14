<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Container extends Model {
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];
    public function vesselSchedule(): BelongsTo { return $this->belongsTo(VesselSchedule::class, 'vessel_schedule_id'); }
    public function originBranch(): BelongsTo { return $this->belongsTo(Branch::class, 'origin_branch_id'); }
    public function destinationBranch(): BelongsTo { return $this->belongsTo(Branch::class, 'destination_branch_id'); }
    public function shipments(): BelongsToMany { return $this->belongsToMany(Shipment::class, 'container_shipments')->withPivot(['id', 'packages_count', 'weight', 'volume_cbm', 'loading_position', 'loaded_at', 'unloaded_at']); }
}
