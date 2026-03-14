<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CurrencyTransaction extends Model
{
    use HasUuids, BelongsToAccount;
    protected $guarded = ['id'];
    protected $casts = ['source_amount' => 'decimal:4', 'target_amount' => 'decimal:4', 'exchange_rate' => 'decimal:6'];

    public function wallet() { return $this->belongsTo(Wallet::class); }
}
