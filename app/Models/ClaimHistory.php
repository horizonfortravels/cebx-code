<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClaimHistory extends Model
{
    use HasUuids;
    protected $table = 'claim_history';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['claim_id', 'from_status', 'to_status', 'changed_by', 'notes'];

    public function claim() { return $this->belongsTo(Claim::class); }
    public function user() { return $this->belongsTo(User::class, 'changed_by'); }
}
