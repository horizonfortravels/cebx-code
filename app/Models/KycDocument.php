<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\BelongsToAccount;

/**
 * KycDocument — Secure KYC document record with access control.
 *
 * FR-IAM-016: Document access restriction
 */
class KycDocument extends Model
{
    use HasUuids, HasFactory, BelongsToAccount;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'account_id',
        'kyc_verification_id',
        'document_type',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
        'file_hash',
        'uploaded_by',
        'is_sensitive',
        'is_purged',
        'purged_at',
    ];

    protected $casts = [
        'is_sensitive' => 'boolean',
        'is_purged'    => 'boolean',
        'file_size'    => 'integer',
        'purged_at'    => 'datetime',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function kycVerification(): BelongsTo
    {
        return $this->belongsTo(KycVerification::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function isPurged(): bool
    {
        return $this->is_purged;
    }

    public function isSensitive(): bool
    {
        return $this->is_sensitive;
    }

    /**
     * Get human-readable file size.
     */
    public function humanFileSize(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if document type is an identity document (extra sensitive).
     */
    public function isIdentityDocument(): bool
    {
        return in_array($this->document_type, [
            'national_id', 'passport', 'residence_permit', 'driving_license',
        ]);
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeNotPurged($query)
    {
        return $query->where('is_purged', false);
    }

    public function scopeForVerification($query, string $kycVerificationId)
    {
        return $query->where('kyc_verification_id', $kycVerificationId);
    }
}
