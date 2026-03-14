<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PromoCodeUsage — FR-PAY-007 tracking
 */
class PromoCodeUsage extends Model
{
    use HasUuids;

    protected $table = 'promo_code_usages';

    protected $fillable = [
        'promo_code_id', 'account_id', 'transaction_id', 'discount_applied',
    ];

    protected $casts = ['discount_applied' => 'decimal:2'];

    public function promoCode(): BelongsTo { return $this->belongsTo(PromoCode::class); }
}
