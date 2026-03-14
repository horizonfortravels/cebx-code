<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * VerificationDocument — FR-KYC-002/007
 */
class VerificationDocument extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'case_id', 'document_type', 'original_filename', 'stored_path',
        'mime_type', 'file_size', 'file_hash', 'status', 'rejection_note',
        'is_encrypted', 'encryption_key_id', 'uploaded_at', 'uploaded_by',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'uploaded_at'  => 'datetime',
    ];

    // Document types
    const TYPE_NATIONAL_ID         = 'national_id';
    const TYPE_PASSPORT            = 'passport';
    const TYPE_COMMERCIAL_REGISTER = 'commercial_register';
    const TYPE_TAX_CERTIFICATE     = 'tax_certificate';
    const TYPE_BANK_STATEMENT      = 'bank_statement';
    const TYPE_UTILITY_BILL        = 'utility_bill';
    const TYPE_OTHER               = 'other';

    // Allowed MIME types (FR-KYC-002)
    const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    public function case(): BelongsTo { return $this->belongsTo(VerificationCase::class, 'case_id'); }

    public function isAccepted(): bool { return $this->status === 'accepted'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }

    public function accept(): void { $this->update(['status' => 'accepted']); }

    public function reject(string $note): void
    {
        $this->update(['status' => 'rejected', 'rejection_note' => $note]);
    }

    /**
     * FR-KYC-007: Generate temporary signed URL for secure download.
     */
    public function getTemporaryUrl(int $expiryMinutes = 15): string
    {
        // In production: Storage::disk('s3')->temporaryUrl($this->stored_path, now()->addMinutes($expiryMinutes))
        return url("/api/v1/kyc/documents/{$this->id}/download?expires=" . now()->addMinutes($expiryMinutes)->timestamp);
    }
}
