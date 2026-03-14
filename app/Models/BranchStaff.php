<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BranchStaff extends Model
{
    use HasUuids;

    protected $table = 'branch_staff';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['branch_id', 'user_id', 'role', 'assigned_at', 'released_at', 'is_primary'];

    protected $casts = [
        'assigned_at' => 'date',
        'released_at' => 'date',
        'is_primary' => 'boolean',
    ];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function user() { return $this->belongsTo(User::class); }
}
