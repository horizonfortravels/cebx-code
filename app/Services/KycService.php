<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\KycDocument;
use App\Models\KycVerification;
use App\Models\User;
use App\Support\Kyc\AccountKycStatusMapper;
use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * KycService — Comprehensive KYC management with document access control.
 *
 * FR-IAM-014: KYC status display, capabilities, and status transitions
 * FR-IAM-016: KYC document access restriction, secure storage, audit logging
 */
class KycService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // FR-IAM-014: KYC Status & Capabilities
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get comprehensive KYC status with capabilities and display info.
     */
    public function getKycStatus(string $accountId): array
    {
        $account = Account::with('kycVerification')->findOrFail($accountId);
        $kyc = $account->kycVerification;

        if (!$kyc) {
            $default = new KycVerification([
                'status' => KycVerification::STATUS_UNVERIFIED,
                'verification_type' => $account->type,
            ]);

            return [
                'status'              => 'unverified',
                'status_display'      => $default->statusDisplay(),
                'verification_type'   => $account->type,
                'verification_level'  => 'basic',
                'capabilities'        => $default->capabilities(),
                'required_documents'  => KycVerification::requiredDocumentsFor($account->type),
                'submitted_documents' => [],
                'rejection_reason'    => null,
                'review_notes'        => null,
                'submitted_at'        => null,
                'reviewed_at'         => null,
                'expires_at'          => null,
                'documents_count'     => 0,
            ];
        }

        return [
            'status'              => $kyc->status,
            'status_display'      => $kyc->statusDisplay(),
            'verification_type'   => $kyc->verification_type,
            'verification_level'  => $kyc->verification_level ?? 'basic',
            'capabilities'        => $kyc->capabilities(),
            'required_documents'  => $kyc->required_documents ?? [],
            'submitted_documents' => $kyc->submitted_documents ?? [],
            'rejection_reason'    => $kyc->rejection_reason,
            'review_notes'        => $kyc->review_notes,
            'submitted_at'        => $kyc->submitted_at?->toISOString(),
            'reviewed_at'         => $kyc->reviewed_at?->toISOString(),
            'expires_at'          => $kyc->expires_at?->toISOString(),
            'review_count'        => $kyc->review_count ?? 0,
            'documents_count'     => $kyc->documents()->notPurged()->count(),
        ];
    }

    /**
     * Review KYC: Approve submission.
     * Only Owner or users with kyc:manage permission.
     */
    public function approveKyc(
        string $accountId,
        User   $reviewer,
        ?string $notes = null,
        ?string $level = null
    ): KycVerification {
        $this->assertCanManageKyc($reviewer);

        $account = Account::findOrFail($accountId);
        $kyc = $account->kycVerification;

        if (!$kyc) {
            throw new BusinessException('سجل التحقق غير موجود.', 'ERR_KYC_NOT_FOUND', 404);
        }

        if (!$kyc->isPending()) {
            throw new BusinessException(
                'لا يمكن الموافقة إلا على طلبات قيد المراجعة.',
                'ERR_KYC_STATUS_INVALID', 422
            );
        }

        return DB::transaction(function () use ($kyc, $account, $reviewer, $notes, $level) {
            $oldStatus = $kyc->status;

            $kyc->update([
                'status'             => KycVerification::STATUS_APPROVED,
                'reviewed_by'        => $reviewer->id,
                'reviewed_at'        => now(),
                'review_notes'       => $notes,
                'verification_level' => $level ?? $kyc->verification_level,
                'review_count'       => $kyc->review_count + 1,
                'expires_at'         => now()->addYear(), // KYC valid for 1 year
            ]);

            $account->update([
                'kyc_status' => AccountKycStatusMapper::fromVerificationStatus(KycVerification::STATUS_APPROVED),
            ]);

            $this->auditService->info(
                $account->id, $reviewer->id,
                'kyc.approved', AuditLog::CATEGORY_KYC,
                'KycVerification', $kyc->id,
                ['status' => $oldStatus],
                ['status' => KycVerification::STATUS_APPROVED, 'level' => $level],
                ['review_notes' => $notes]
            );

            return $kyc->fresh();
        });
    }

    /**
     * Review KYC: Reject submission.
     */
    public function rejectKyc(
        string $accountId,
        User   $reviewer,
        string $reason,
        ?string $notes = null
    ): KycVerification {
        $this->assertCanManageKyc($reviewer);

        $account = Account::findOrFail($accountId);
        $kyc = $account->kycVerification;

        if (!$kyc) {
            throw new BusinessException('سجل التحقق غير موجود.', 'ERR_KYC_NOT_FOUND', 404);
        }

        if (!$kyc->isPending()) {
            throw new BusinessException(
                'لا يمكن رفض إلا طلبات قيد المراجعة.',
                'ERR_KYC_STATUS_INVALID', 422
            );
        }

        return DB::transaction(function () use ($kyc, $account, $reviewer, $reason, $notes) {
            $oldStatus = $kyc->status;

            $kyc->update([
                'status'           => KycVerification::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'review_notes'     => $notes,
                'reviewed_by'      => $reviewer->id,
                'reviewed_at'      => now(),
                'review_count'     => $kyc->review_count + 1,
            ]);

            $account->update([
                'kyc_status' => AccountKycStatusMapper::fromVerificationStatus(KycVerification::STATUS_REJECTED),
            ]);

            $this->auditService->warning(
                $account->id, $reviewer->id,
                'kyc.rejected', AuditLog::CATEGORY_KYC,
                'KycVerification', $kyc->id,
                ['status' => $oldStatus],
                ['status' => KycVerification::STATUS_REJECTED],
                ['reason' => $reason, 'notes' => $notes]
            );

            return $kyc->fresh();
        });
    }

    /**
     * Re-submit KYC after rejection. Resets status to pending.
     */
    public function resubmitKyc(
        string $accountId,
        array  $documents,
        User   $performer
    ): KycVerification {
        $account = Account::findOrFail($accountId);
        $kyc = $account->kycVerification;

        if (!$kyc) {
            throw new BusinessException('سجل التحقق غير موجود.', 'ERR_KYC_NOT_FOUND', 404);
        }

        if (!in_array($kyc->status, [KycVerification::STATUS_REJECTED, KycVerification::STATUS_EXPIRED])) {
            throw new BusinessException(
                'إعادة التقديم متاحة فقط للطلبات المرفوضة أو المنتهية.',
                'ERR_KYC_STATUS_INVALID', 422
            );
        }

        return DB::transaction(function () use ($kyc, $account, $documents, $performer) {
            $oldStatus = $kyc->status;

            $kyc->update([
                'status'               => KycVerification::STATUS_PENDING,
                'submitted_documents'  => $documents,
                'submitted_at'         => now(),
                'rejection_reason'     => null,
                'reviewed_at'          => null,
                'reviewed_by'          => null,
            ]);

            $account->update([
                'kyc_status' => AccountKycStatusMapper::fromVerificationStatus(KycVerification::STATUS_PENDING),
            ]);

            $this->auditService->info(
                $account->id, $performer->id,
                'kyc.resubmitted', AuditLog::CATEGORY_KYC,
                'KycVerification', $kyc->id,
                ['status' => $oldStatus],
                ['status' => KycVerification::STATUS_PENDING],
                ['documents_count' => count($documents)]
            );

            return $kyc->fresh();
        });
    }

    /**
     * Expire KYC verifications that have passed their expiry date.
     */
    public function expireStaleVerifications(): int
    {
        $stale = KycVerification::where('status', KycVerification::STATUS_APPROVED)
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;
        foreach ($stale as $kyc) {
            $kyc->update(['status' => KycVerification::STATUS_EXPIRED]);
            $kyc->account->update([
                'kyc_status' => AccountKycStatusMapper::fromVerificationStatus(KycVerification::STATUS_EXPIRED),
            ]);

            $this->auditService->warning(
                $kyc->account_id, null,
                'kyc.expired', AuditLog::CATEGORY_KYC,
                'KycVerification', $kyc->id,
                ['status' => KycVerification::STATUS_APPROVED],
                ['status' => KycVerification::STATUS_EXPIRED]
            );
            $count++;
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-IAM-016: KYC Document Access Control
    // ═══════════════════════════════════════════════════════════════

    /**
     * Upload a KYC document.
     */
    public function uploadDocument(
        string $accountId,
        string $kycVerificationId,
        string $documentType,
        string $filename,
        string $storedPath,
        string $mimeType,
        int    $fileSize,
        User   $uploader,
        bool   $isSensitive = true
    ): KycDocument {
        // Verify the KYC belongs to this account
        $kyc = KycVerification::where('id', $kycVerificationId)
            ->where('account_id', $accountId)
            ->firstOrFail();

        $doc = KycDocument::create([
            'account_id'          => $accountId,
            'kyc_verification_id' => $kycVerificationId,
            'document_type'       => $documentType,
            'original_filename'   => $filename,
            'stored_path'         => $storedPath,
            'mime_type'           => $mimeType,
            'file_size'           => $fileSize,
            'file_hash'           => null, // Set by storage service
            'uploaded_by'         => $uploader->id,
            'is_sensitive'        => $isSensitive,
        ]);

        $this->auditService->info(
            $accountId, $uploader->id,
            'kyc.document_uploaded', AuditLog::CATEGORY_KYC,
            'KycDocument', $doc->id,
            null,
            ['document_type' => $documentType, 'filename' => $filename],
            ['file_size' => $fileSize, 'is_sensitive' => $isSensitive]
        );

        return $doc;
    }

    /**
     * List documents for a KYC verification (permission-gated).
     */
    public function listDocuments(string $accountId, User $requester): array
    {
        $this->assertCanViewKycDocuments($requester);

        $account = Account::findOrFail($accountId);
        $kyc = $account->kycVerification;

        if (!$kyc) {
            return [];
        }

        $documents = $kyc->documents()
            ->notPurged()
            ->with('uploader:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        // Log document list access
        $this->auditService->info(
            $accountId, $requester->id,
            'kyc.documents_listed', AuditLog::CATEGORY_KYC,
            'KycVerification', $kyc->id,
            null, null,
            ['documents_count' => $documents->count()]
        );

        return $documents->map(fn ($doc) => [
            'id'                => $doc->id,
            'document_type'     => $doc->document_type,
            'original_filename' => $doc->original_filename,
            'mime_type'         => $doc->mime_type,
            'file_size'         => $doc->file_size,
            'human_size'        => $doc->humanFileSize(),
            'is_sensitive'      => $doc->is_sensitive,
            'uploaded_by'       => $doc->uploader ? [
                'id'   => $doc->uploader->id,
                'name' => $doc->uploader->name,
            ] : null,
            'created_at'        => $doc->created_at?->toISOString(),
        ])->toArray();
    }

    /**
     * Generate a temporary download URL for a KYC document.
     * Returns the stored path (in production, this would be a signed URL).
     *
     * CRITICAL: Every access is logged.
     */
    public function getDocumentDownloadUrl(
        string $accountId,
        string $documentId,
        User   $requester
    ): array {
        $this->assertCanViewKycDocuments($requester);

        $doc = KycDocument::where('id', $documentId)
            ->where('account_id', $accountId)
            ->notPurged()
            ->firstOrFail();

        if ($doc->isPurged()) {
            throw new BusinessException(
                'تم حذف محتوى هذه الوثيقة.',
                'ERR_DOCUMENT_PURGED', 410
            );
        }

        // Log EVERY document access
        $this->auditService->info(
            $accountId, $requester->id,
            'kyc.document_accessed', AuditLog::CATEGORY_KYC,
            'KycDocument', $doc->id,
            null, null,
            [
                'document_type' => $doc->document_type,
                'is_sensitive'  => $doc->is_sensitive,
                'requester_ip'  => request()->ip(),
            ]
        );

        // In production: generate signed URL with TTL
        $expiresAt = now()->addMinutes(15);

        return [
            'document_id'       => $doc->id,
            'document_type'     => $doc->document_type,
            'original_filename' => $doc->original_filename,
            'mime_type'         => $doc->mime_type,
            'download_url'      => $doc->stored_path, // Production: signed URL
            'expires_at'        => $expiresAt->toISOString(),
            'ttl_minutes'       => 15,
        ];
    }

    /**
     * Purge (soft-delete content of) sensitive documents.
     * Metadata is retained for audit trail, but file content is removed.
     */
    public function purgeDocument(
        string $accountId,
        string $documentId,
        User   $performer
    ): KycDocument {
        $this->assertCanManageKyc($performer);

        $doc = KycDocument::where('id', $documentId)
            ->where('account_id', $accountId)
            ->firstOrFail();

        if ($doc->isPurged()) {
            throw new BusinessException(
                'الوثيقة محذوفة بالفعل.',
                'ERR_DOCUMENT_ALREADY_PURGED', 422
            );
        }

        $doc->update([
            'is_purged'   => true,
            'purged_at'   => now(),
            'stored_path' => '[PURGED]',
        ]);

        $this->auditService->warning(
            $accountId, $performer->id,
            'kyc.document_purged', AuditLog::CATEGORY_KYC,
            'KycDocument', $doc->id,
            ['stored_path' => '[REDACTED]'],
            ['is_purged' => true],
            ['document_type' => $doc->document_type, 'reason' => 'Manual purge']
        );

        return $doc->fresh();
    }

    // ─── Permission Checks ───────────────────────────────────────

    private function assertCanManageKyc(User $user): void
    {
        if (!$user->hasPermission('kyc.manage')) {
            $this->auditService->warning(
                $user->account_id, $user->id,
                'kyc.access_denied', AuditLog::CATEGORY_KYC,
                null, null, null, null,
                ['attempted_action' => 'manage_kyc']
            );
            throw BusinessException::permissionDenied();
        }
    }

    private function assertCanViewKycDocuments(User $user): void
    {
        if (!$user->hasPermission('kyc.documents')) {
            $this->auditService->warning(
                $user->account_id, $user->id,
                'kyc.document_access_denied', AuditLog::CATEGORY_KYC,
                null, null, null, null,
                ['attempted_action' => 'view_kyc_documents']
            );
            throw new BusinessException(
                'لا تملك صلاحية الوصول لوثائق KYC.',
                'ERR_UNAUTHORIZED_ACCESS', 403
            );
        }
    }
}
