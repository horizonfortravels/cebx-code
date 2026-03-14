<?php
namespace App\Models;
use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
class Store extends Model {
    use HasFactory, HasUuids, BelongsToAccount;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];
    protected $casts = ['last_sync_at' => 'datetime'];
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
}
