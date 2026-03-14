<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Schedule extends Model {
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];
    protected $casts = ['departure_date' => 'datetime', 'arrival_date' => 'datetime'];
    public function vessel(): BelongsTo { return $this->belongsTo(Vessel::class); }
}
