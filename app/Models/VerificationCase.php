<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * VerificationCase — FR-KYC-001/003
 */
class VerificationCase extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'account_id', 'organization_id', 'case_number', 'account_type', 'status',
        'applicant_name', 'applicant_email', 'applicant_phone', 'country_code',
        'rejection_reason', 'reviewed_by', 'submitted_at', 'reviewed_at',
        'verified_at', 'expires_at', 'submission_count', 'required_documents',
    ];

    protected $casts = [
        'submitted_at'       => 'datetime',
        'reviewed_at'        => 'datetime',
        'verified_at'        => 'datetime',
        'expires_at'         => 'datetime',
        'required_documents' => 'array',
    ];

    const STATUS_UNVERIFIED     = 'unverified';
    const STATUS_PENDING_REVIEW = 'pending_review';
    const STATUS_UNDER_REVIEW   = 'under_review';
    const STATUS_VERIFIED       = 'verified';
    const STATUS_REJECTED       = 'rejected';
    const STATUS_EXPIRED        = 'expired';

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function documents(): HasMany { return $this->hasMany(VerificationDocument::class, 'case_id'); }
    public function reviews(): HasMany { return $this->hasMany(VerificationReview::class, 'case_id'); }
    public function auditLogs(): HasMany { return $this->hasMany(KycAuditLog::class, 'case_id'); }

    public static function generateCaseNumber(): string
    {
        return 'KYC-' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function isVerified(): bool { return $this->status === self::STATUS_VERIFIED; }
    public function isPending(): bool { return in_array($this->status, [self::STATUS_PENDING_REVIEW, self::STATUS_UNDER_REVIEW]); }

    public function submit(): void
    {
        $this->update([
            'status'           => self::STATUS_PENDING_REVIEW,
            'submitted_at'     => now(),
            'submission_count' => $this->submission_count + 1,
        ]);
    }

    public function approve(string $reviewerId): void
    {
        $this->update([
            'status'      => self::STATUS_VERIFIED,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'verified_at' => now(),
        ]);
    }

    public function reject(string $reviewerId, string $reason): void
    {
        $this->update([
            'status'           => self::STATUS_REJECTED,
            'reviewed_by'      => $reviewerId,
            'reviewed_at'      => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function scopePendingReview($q) { return $q->where('status', self::STATUS_PENDING_REVIEW); }
    public function scopeByStatus($q, string $status) { return $q->where('status', $status); }
}
