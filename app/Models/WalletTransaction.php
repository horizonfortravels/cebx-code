<?php
namespace App\Models;
use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class WalletTransaction extends Model {
    use HasUuids, BelongsToAccount;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];
    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
}
