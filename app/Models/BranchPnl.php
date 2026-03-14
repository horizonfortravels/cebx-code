<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BranchPnl extends Model
{
    use HasUuids, BelongsToAccount;
    protected $table = 'branch_pnl';
    protected $guarded = ['id'];
    protected $casts = ['period_start' => 'date', 'period_end' => 'date', 'revenue' => 'decimal:2', 'cost' => 'decimal:2', 'profit' => 'decimal:2'];

    public function branch() { return $this->belongsTo(Branch::class); }
}
