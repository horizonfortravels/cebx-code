<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class FraudSignal extends Model
{
    use HasUuids, BelongsToAccount;
    protected $guarded = ['id'];
    protected $casts = ['score' => 'decimal:4', 'signals' => 'json', 'resolved_at' => 'datetime'];

    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function user() { return $this->belongsTo(User::class); }
}
