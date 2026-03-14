<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SavedQuote extends Model
{
    use HasUuids, BelongsToAccount;
    protected $guarded = ['id'];
    protected $casts = ['quote_data' => 'json', 'expires_at' => 'datetime', 'total_cost' => 'decimal:2'];

    public function rateQuote() { return $this->belongsTo(RateQuote::class); }
}
