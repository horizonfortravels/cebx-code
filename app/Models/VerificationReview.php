<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * VerificationReview — FR-KYC-005
 */
class VerificationReview extends Model
{
    use HasUuids;

    protected $fillable = [
        'case_id', 'reviewer_id', 'decision', 'reason',
        'internal_notes', 'document_decisions',
    ];

    protected $casts = ['document_decisions' => 'array'];

    const DECISION_APPROVED       = 'approved';
    const DECISION_REJECTED       = 'rejected';
    const DECISION_NEEDS_MORE_INFO = 'needs_more_info';

    public function case(): BelongsTo { return $this->belongsTo(VerificationCase::class, 'case_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
}
