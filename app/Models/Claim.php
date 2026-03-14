<?php
namespace App\Models;
use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Claim extends Model {
    use HasUuids, BelongsToAccount;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function filer(): BelongsTo { return $this->belongsTo(User::class, 'filed_by'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function documents(): HasMany { return $this->hasMany(ClaimDocument::class); }
    public function history(): HasMany { return $this->hasMany(ClaimHistory::class)->orderByDesc('created_at'); }

    public static function generateNumber(): string
    {
        return 'CLM-' . now()->format('Ymd') . '-' . strtoupper(substr((string) static::count() + 1000, -4));
    }
}
