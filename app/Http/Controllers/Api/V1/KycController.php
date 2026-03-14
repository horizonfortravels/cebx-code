<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\KycDocument;
use App\Models\KycVerification;
use App\Services\KycService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * KycController — FR-IAM-014 + FR-IAM-016
 *
 * FR-IAM-014: KYC status display, capabilities, approve/reject
 * FR-IAM-016: Document access control, secure download, purge
 */
class KycController extends Controller
{
    public function __construct(
        protected KycService $kycService
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // FR-IAM-014: KYC Status & Capabilities
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/v1/kyc/status
     * Comprehensive KYC status with capabilities and display info.
     */
    public function status(Request $request): JsonResponse
    {
        $this->authorize('viewAny', KycVerification::class);

        $currentAccountId = $this->resolveCurrentAccountId($request);
        $status = $this->kycService->getKycStatus($currentAccountId);

        return response()->json([
            'success' => true,
            'data'    => $status,
        ]);
    }

    /**
     * POST /api/v1/kyc/approve
     * Approve a pending KYC submission. Requires kyc:manage.
     */
    public function approve(Request $request): JsonResponse
    {
        $this->authorize('update', KycVerification::class);

        $request->validate([
            'account_id' => ['required', 'uuid'],
            'notes'      => ['nullable', 'string', 'max:1000'],
            'level'      => ['nullable', 'string', 'in:basic,enhanced,full'],
        ]);

        $currentAccountId = $this->resolveCurrentAccountId($request);
        if (trim((string) $request->account_id) !== $currentAccountId) {
            abort(404);
        }

        Account::withoutGlobalScopes()
            ->where('id', $currentAccountId)
            ->firstOrFail();

        $kyc = $this->kycService->approveKyc(
            $currentAccountId,
            $request->user(),
            $request->notes,
            $request->level
        );

        return response()->json([
            'success' => true,
            'message' => 'تمت الموافقة على التحقق بنجاح.',
            'data'    => [
                'status'     => $kyc->status,
                'reviewed_at'=> $kyc->reviewed_at?->toISOString(),
                'expires_at' => $kyc->expires_at?->toISOString(),
            ],
        ]);
    }

    /**
     * POST /api/v1/kyc/reject
     * Reject a pending KYC submission. Requires kyc:manage.
     */
    public function reject(Request $request): JsonResponse
    {
        $this->authorize('update', KycVerification::class);

        $request->validate([
            'account_id' => ['required', 'uuid'],
            'reason'     => ['required', 'string', 'max:1000'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ]);

        $currentAccountId = $this->resolveCurrentAccountId($request);
        if (trim((string) $request->account_id) !== $currentAccountId) {
            abort(404);
        }

        Account::withoutGlobalScopes()
            ->where('id', $currentAccountId)
            ->firstOrFail();

        $kyc = $this->kycService->rejectKyc(
            $currentAccountId,
            $request->user(),
            $request->reason,
            $request->notes
        );

        return response()->json([
            'success' => true,
            'message' => 'تم رفض التحقق.',
            'data'    => [
                'status'           => $kyc->status,
                'rejection_reason' => $kyc->rejection_reason,
                'reviewed_at'      => $kyc->reviewed_at?->toISOString(),
            ],
        ]);
    }

    /**
     * POST /api/v1/kyc/resubmit
     * Re-submit after rejection/expiry.
     */
    public function resubmit(Request $request): JsonResponse
    {
        $this->authorize('update', KycVerification::class);

        $request->validate([
            'documents'   => ['required', 'array', 'min:1'],
            'documents.*' => ['string', 'max:500'],
        ]);

        $currentAccountId = $this->resolveCurrentAccountId($request);

        $kyc = $this->kycService->resubmitKyc(
            $currentAccountId,
            $request->documents,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إعادة تقديم الوثائق للمراجعة.',
            'data'    => [
                'status'       => $kyc->status,
                'submitted_at' => $kyc->submitted_at?->toISOString(),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-IAM-016: Document Access Control
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /api/v1/kyc/documents
     * List KYC documents (requires kyc:documents permission).
     */
    public function listDocuments(Request $request): JsonResponse
    {
        $this->authorize('viewDocuments', KycDocument::class);

        $currentAccountId = $this->resolveCurrentAccountId($request);
        $documents = $this->kycService->listDocuments(
            $currentAccountId,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data'    => $documents,
            'meta'    => ['count' => count($documents)],
        ]);
    }

    /**
     * POST /api/v1/kyc/documents/upload
     * Upload a KYC document.
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $this->authorize('manageDocuments', KycDocument::class);

        $request->validate([
            'kyc_verification_id' => ['required', 'uuid'],
            'document_type'       => ['required', 'string', 'max:100'],
            'filename'            => ['required', 'string', 'max:255'],
            'stored_path'         => ['required', 'string', 'max:500'],
            'mime_type'           => ['required', 'string', 'max:100'],
            'file_size'           => ['required', 'integer', 'min:1'],
            'is_sensitive'        => ['nullable', 'boolean'],
        ]);

        $currentAccountId = $this->resolveCurrentAccountId($request);

        KycVerification::withoutGlobalScopes()
            ->where('account_id', $currentAccountId)
            ->where('id', $request->kyc_verification_id)
            ->firstOrFail();

        $doc = $this->kycService->uploadDocument(
            $currentAccountId,
            $request->kyc_verification_id,
            $request->document_type,
            $request->filename,
            $request->stored_path,
            $request->mime_type,
            $request->file_size,
            $request->user(),
            $request->boolean('is_sensitive', true)
        );

        return response()->json([
            'success' => true,
            'message' => 'تم رفع الوثيقة بنجاح.',
            'data'    => [
                'id'            => $doc->id,
                'document_type' => $doc->document_type,
                'filename'      => $doc->original_filename,
            ],
        ], 201);
    }

    /**
     * GET /api/v1/kyc/documents/{id}/download
     * Get a temporary download URL for a KYC document.
     * Requires kyc:documents permission. Access is logged.
     */
    public function downloadDocument(Request $request, string $id): JsonResponse
    {
        $currentAccountId = $this->resolveCurrentAccountId($request);

        $document = KycDocument::withoutGlobalScopes()
            ->where('account_id', $currentAccountId)
            ->where('id', $id)
            ->firstOrFail();

        $this->authorize('viewDocuments', $document);

        $downloadInfo = $this->kycService->getDocumentDownloadUrl(
            $currentAccountId,
            $id,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data'    => $downloadInfo,
        ]);
    }

    /**
     * DELETE /api/v1/kyc/documents/{id}
     * Purge a KYC document (soft-delete content, keep metadata).
     * Requires kyc:manage permission.
     */
    public function purgeDocument(Request $request, string $id): JsonResponse
    {
        $currentAccountId = $this->resolveCurrentAccountId($request);

        $document = KycDocument::withoutGlobalScopes()
            ->where('account_id', $currentAccountId)
            ->where('id', $id)
            ->firstOrFail();

        $this->authorize('manageDocuments', $document);

        $doc = $this->kycService->purgeDocument(
            $currentAccountId,
            $id,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم حذف محتوى الوثيقة. البيانات الوصفية محفوظة للتدقيق.',
            'data'    => [
                'id'        => $doc->id,
                'is_purged' => true,
                'purged_at' => $doc->purged_at?->toISOString(),
            ],
        ]);
    }

    private function resolveCurrentAccountId(Request $request): string
    {
        $currentAccountId = app()->bound('current_account_id')
            ? trim((string) app('current_account_id'))
            : '';

        if ($currentAccountId !== '') {
            return $currentAccountId;
        }

        return trim((string) ($request->user()->account_id ?? ''));
    }
}
