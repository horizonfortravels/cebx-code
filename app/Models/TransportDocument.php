<?php
namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TransportDocument extends Model
{
    use HasUuids, BelongsToAccount;
    protected $guarded = ['id'];
    protected $casts = ['metadata' => 'json', 'issue_date' => 'date', 'expiry_date' => 'date'];

    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function container() { return $this->belongsTo(Container::class); }
}
