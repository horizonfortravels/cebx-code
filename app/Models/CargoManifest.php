<?php
namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CargoManifest extends Model
{
    use HasUuids, BelongsToAccount;
    protected $guarded = ['id'];
    protected $casts = ['manifest_date' => 'date', 'metadata' => 'json'];

    public function items() { return $this->hasMany(CargoManifestItem::class); }
    public function vessel() { return $this->belongsTo(Vessel::class); }
    public function container() { return $this->belongsTo(Container::class); }
}
