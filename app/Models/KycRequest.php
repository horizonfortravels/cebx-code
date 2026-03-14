<?php
namespace App\Models;
use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class KycRequest extends Model {
    use BelongsToAccount;

    protected $guarded = [];
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Account::class, 'account_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
}
