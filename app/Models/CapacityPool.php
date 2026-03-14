<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CapacityPool extends Model
{
    use HasUuids, BelongsToAccount;
    protected $guarded = ['id'];
    protected $casts = ['available_capacity' => 'integer', 'total_capacity' => 'integer', 'valid_from' => 'date', 'valid_to' => 'date'];

    public function bookings() { return $this->hasMany(CapacityBooking::class); }
}
