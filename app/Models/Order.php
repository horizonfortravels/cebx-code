<?php
namespace App\Models;
use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Order extends Model {
    use HasFactory, HasUuids, BelongsToAccount;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
}
