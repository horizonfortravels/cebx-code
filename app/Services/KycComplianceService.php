<?php

namespace App\Services;

use App\Models\KycAuditLog;
use App\Models\VerificationCase;
use App\Models\VerificationDocument;
use App\Models\VerificationRestriction;
use App\Models\VerificationReview;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KycComplianceService — FR-KYC-001→008 (8 requirements)
 *
 * Extends the IAM KycService (FR-IAM-014/016) with full compliance workflow.
 *
 * FR-KYC-001: Default unverified status on registration
 * FR-KYC-002: Upload verification documents from account settings
 * FR-KYC-003: Verification status management with rejection reason
 * FR-KYC-004: Usage restrictions for unverified accounts
 * FR-KYC-005: Admin KYC review panel (accept/reject with reason)
 * FR-KYC-006: Notify user on decision + show restrictions in UI
 * FR-KYC-007: Secure document storage, no public links, RBAC access
 * FR-KYC-008: Dedicated KYC audit log for compliance export
 */
class KycComplianceService
{
    // ═══════════════════════════════════════════════════════════
    // FR-KYC-001: Create Verification Case (default unverified)
    // ═══════════════════════════════════════════════════════════

    public function createCase(string $accountId, string $accountType, array $data = []): VerificationCase
    {
        $requiredDocs = $accountType === 'organization'
            ? ['commercial_register', 'tax_certificate']
            : ['national_id'];

        return VerificationCase::create(array_merge([
            'account_id'         => $accountId,
            'case_number'        => VerificationCase::generateCaseNumber(),
            'account_type'       => $accountType,
            'status'             => VerificationCase::STATUS_UNVERIFIED,
            'required_documents' => $requiredDocs,
        ], $data));
    }

    public function getCase(string $caseId): VerificationCase
    {
        return VerificationCase::with('documents', 'reviews.reviewer')->findOrFail($caseId);
    }

    public function getCaseForAccount(string $accountId): ?VerificationCase
    {
        return VerificationCase::where('account_id', $accountId)->with('documents')->latest()->first();
    }

    public function getVerificationStatus(string $accountId): array
    {
        $case = $this->getCaseForAccount($accountId);
        $status = $case?->status ?? VerificationCase::STATUS_UNVERIFIED;

        return [
            'status'           => $status,
            'is_verified'      => $status === VerificationCase::STATUS_VERIFIED,
            'case_id'          => $case?->id,
            'case_number'      => $case?->case_number,
            'rejection_reason' => $case?->rejection_reason,
            'submitted_at'     => $case?->submitted_at,
            'restrictions'     => $this->getRestrictions($status),
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // FR-KYC-002: Upload Documents
    // ═══════════════════════════════════════════════════════════

    public function uploadDocument(string $caseId, array $data, string $uploadedBy): VerificationDocument
    {
        $case = VerificationCase::findOrFail($caseId);

        if ($case->status === VerificationCase::STATUS_VERIFIED) {
            throw new \RuntimeException('Account already verified');
        }

        $doc = VerificationDocument::create([
            'case_id'           => $caseId,
            'document_type'     => $data['document_type'],
            'original_filename' => $data['original_filename'],
            'stored_path'       => $data['stored_path'],
            'mime_type'         => $data['mime_type'],
            'file_size'         => $data['file_size'],
            'file_hash'         => $data['file_hash'] ?? hash('sha256', $data['stored_path'] . time()),
            'status'            => 'uploaded',
            'is_encrypted'      => true,
            'uploaded_at'       => now(),
            'uploaded_by'       => $uploadedBy,
        ]);

        $this->logAction(KycAuditLog::ACTION_DOCUMENT_UPLOAD, $uploadedBy, $caseId, $doc->id, [
            'document_type' => $data['document_type'], 'file_size' => $data['file_size'],
        ]);

        return $doc;
    }

    // ═══════════════════════════════════════════════════════════
    // FR-KYC-003: Submit for Review / Status Management
    // ═══════════════════════════════════════════════════════════

    public function submitForReview(string $caseId, string $submittedBy): VerificationCase
    {
        $case = VerificationCase::findOrFail($caseId);

        if (!in_array($case->status, [VerificationCase::STATUS_UNVERIFIED, VerificationCase::STATUS_REJECTED])) {
            throw new \RuntimeException('Cannot submit in current status: ' . $case->status);
        }

        $uploadedTypes = $case->documents()->pluck('document_type')->toArray();
        $missing = array_diff($case->required_documents ?? [], $uploadedTypes);
        if (!empty($missing)) {
            throw new \RuntimeException('Missing required documents: ' . implode(', ', $missing));
        }

        $case->submit();
        $this->logAction(KycAuditLog::ACTION_CASE_SUBMIT, $submittedBy, $caseId);

        return $case->fresh();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-KYC-004: Restrictions for Unverified Accounts
    // ═══════════════════════════════════════════════════════════

    public function getRestrictions(string $status): Collection
    {
        return VerificationRestriction::getForStatus($status);
    }

    public function checkRestriction(string $accountId, string $featureKey): array
    {
        $info = $this->getVerificationStatus($accountId);

        $blocked = $info['restrictions']->first(fn($r) =>
            $r->restriction_type === VerificationRestriction::TYPE_BLOCK_FEATURE
            && $r->feature_key === $featureKey
        );

        if ($blocked) {
            return ['allowed' => false, 'reason' => 'KYC_REQUIRED', 'restriction' => $blocked->name];
        }

        $quota = $info['restrictions']->first(fn($r) =>
            $r->restriction_type === VerificationRestriction::TYPE_QUOTA_LIMIT
            && $r->feature_key === $featureKey
        );

        return ['allowed' => true, 'quota_limit' => $quota?->quota_value];
    }

    public function createRestriction(array $data): VerificationRestriction
    {
        return VerificationRestriction::create($data);
    }

    public function listRestrictions(): Collection
    {
        return VerificationRestriction::active()->get();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-KYC-005: Admin Review Panel
    // ═══════════════════════════════════════════════════════════

    public function listPendingCases(int $perPage = 20)
    {
        return VerificationCase::pendingReview()
            ->with('documents')
            ->orderBy('submitted_at')
            ->paginate($perPage);
    }

    public function reviewCase(string $caseId, string $reviewerId, string $decision, ?string $reason = null, ?array $docDecisions = null): VerificationCase
    {
        return DB::transaction(function () use ($caseId, $reviewerId, $decision, $reason, $docDecisions) {
            $case = VerificationCase::findOrFail($caseId);

            VerificationReview::create([
                'case_id'            => $caseId,
                'reviewer_id'        => $reviewerId,
                'decision'           => $decision,
                'reason'             => $reason,
                'document_decisions' => $docDecisions,
            ]);

            if ($docDecisions) {
                foreach ($docDecisions as $docId => $dd) {
                    $doc = VerificationDocument::find($docId);
                    if (!$doc) continue;
                    if (($dd['status'] ?? '') === 'accepted') $doc->accept();
                    elseif (($dd['status'] ?? '') === 'rejected') $doc->reject($dd['note'] ?? '');
                }
            }

            match ($decision) {
                VerificationReview::DECISION_APPROVED       => $case->approve($reviewerId),
                VerificationReview::DECISION_REJECTED       => $case->reject($reviewerId, $reason ?? ''),
                VerificationReview::DECISION_NEEDS_MORE_INFO => $case->reject($reviewerId, $reason ?? 'Additional information needed'),
            };

            $this->logAction(KycAuditLog::ACTION_DECISION, $reviewerId, $caseId, null, [
                'decision' => $decision, 'reason' => $reason,
            ]);

            return $case->fresh();
        });
    }

    // ═══════════════════════════════════════════════════════════
    // FR-KYC-006: Status Display & Notifications
    // ═══════════════════════════════════════════════════════════

    public function getStatusDisplay(string $accountId): array
    {
        $info = $this->getVerificationStatus($accountId);

        $banner = match ($info['status']) {
            VerificationCase::STATUS_UNVERIFIED     => 'حسابك غير موثق. بعض الميزات مقيّدة. يرجى رفع مستندات التوثيق.',
            VerificationCase::STATUS_PENDING_REVIEW => 'مستنداتك قيد المراجعة. سيتم إشعارك بالنتيجة.',
            VerificationCase::STATUS_REJECTED       => 'تم رفض طلب التوثيق: ' . ($info['rejection_reason'] ?? ''),
            VerificationCase::STATUS_VERIFIED       => null,
            default                                 => null,
        };

        return [
            'status'         => $info['status'],
            'is_verified'    => $info['is_verified'],
            'banner_message' => $banner,
            'restrictions'   => $info['restrictions']->map(fn($r) => [
                'name' => $r->name, 'type' => $r->restriction_type, 'feature' => $r->feature_key,
            ]),
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // FR-KYC-007: Secure Document Access
    // ═══════════════════════════════════════════════════════════

    public function getDocumentDownloadUrl(string $documentId, string $requesterId): string
    {
        $doc = VerificationDocument::findOrFail($documentId);
        $this->logAction(KycAuditLog::ACTION_DOCUMENT_DOWNLOAD, $requesterId, $doc->case_id, $documentId);
        return $doc->getTemporaryUrl();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-KYC-008: Audit Logging & Export
    // ═══════════════════════════════════════════════════════════

    public function logAction(string $action, string $actorId, ?string $caseId = null, ?string $documentId = null, array $metadata = [], string $result = 'success'): void
    {
        KycAuditLog::create([
            'case_id'     => $caseId,
            'document_id' => $documentId,
            'actor_id'    => $actorId,
            'actor_type'  => 'user',
            'action'      => $action,
            'ip_address'  => request()?->ip(),
            'metadata'    => $metadata,
            'result'      => $result,
        ]);
    }

    public function getAuditLog(string $caseId): Collection
    {
        return KycAuditLog::where('case_id', $caseId)->orderByDesc('created_at')->get();
    }

    public function exportAuditLog(?string $caseId = null, ?string $from = null, ?string $to = null): Collection
    {
        $q = KycAuditLog::query();
        if ($caseId) $q->where('case_id', $caseId);
        if ($from) $q->where('created_at', '>=', $from);
        if ($to) $q->where('created_at', '<=', $to);
        return $q->orderByDesc('created_at')->get();
    }
}
