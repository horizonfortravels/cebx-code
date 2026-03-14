<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KycAuditLog;
use App\Models\VerificationCase;
use App\Models\VerificationDocument;
use App\Services\KycComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * KycComplianceController — FR-KYC-001→008
 */
class KycComplianceController extends Controller
{
    public function __construct(private KycComplianceService $service) {}

    // ═══════════════ FR-KYC-001: Create/Get Case ═════════════

    public function createCase(Request $request): JsonResponse
    {
        $this->authorize('create', VerificationCase::class);

        $data = $request->validate([
            'account_type'     => 'required|in:individual,organization',
            'organization_id'  => 'nullable|uuid',
            'applicant_name'   => 'nullable|string|max:300',
            'applicant_email'  => 'nullable|email',
            'country_code'     => 'nullable|string|size:2',
        ]);

        $case = $this->service->createCase($this->resolveCurrentAccountId($request), $data['account_type'], $data);
        return response()->json(['status' => 'success', 'data' => $case], 201);
    }

    public function getCase(Request $request, string $caseId): JsonResponse
    {
        $case = VerificationCase::withoutGlobalScopes()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('id', $caseId)
            ->firstOrFail();

        $this->authorize('view', $case);

        return response()->json(['status' => 'success', 'data' => $this->service->getCase((string) $case->id)]);
    }

    public function getStatus(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VerificationCase::class);

        return response()->json(['status' => 'success', 'data' => $this->service->getVerificationStatus($this->resolveCurrentAccountId($request))]);
    }

    // ═══════════════ FR-KYC-002: Upload Documents ════════════

    public function uploadDocument(Request $request, string $caseId): JsonResponse
    {
        $case = VerificationCase::withoutGlobalScopes()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('id', $caseId)
            ->firstOrFail();

        $this->authorize('manageDocuments', $case);

        $data = $request->validate([
            'document_type'     => 'required|in:national_id,passport,commercial_register,tax_certificate,bank_statement,utility_bill,other',
            'original_filename' => 'required|string|max:500',
            'stored_path'       => 'required|string|max:1000',
            'mime_type'         => 'required|string|max:100',
            'file_size'         => 'required|integer|max:10485760',
        ]);
        $doc = $this->service->uploadDocument((string) $case->id, $data, $request->user()->id);
        return response()->json(['status' => 'success', 'data' => $doc], 201);
    }

    // ═══════════════ FR-KYC-003: Submit for Review ═══════════

    public function submit(Request $request, string $caseId): JsonResponse
    {
        $case = VerificationCase::withoutGlobalScopes()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('id', $caseId)
            ->firstOrFail();

        $this->authorize('update', $case);

        return response()->json(['status' => 'success', 'data' => $this->service->submitForReview((string) $case->id, $request->user()->id)]);
    }

    // ═══════════════ FR-KYC-004: Restrictions ════════════════

    public function checkRestriction(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VerificationCase::class);

        $data = $request->validate(['feature_key' => 'required|string']);
        return response()->json(['status' => 'success', 'data' => $this->service->checkRestriction($this->resolveCurrentAccountId($request), $data['feature_key'])]);
    }

    public function listRestrictions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VerificationCase::class);

        return response()->json(['status' => 'success', 'data' => $this->service->listRestrictions()]);
    }

    public function createRestriction(Request $request): JsonResponse
    {
        $this->authorize('update', VerificationCase::class);

        $data = $request->validate([
            'name' => 'required|string|max:200', 'restriction_key' => 'required|string|max:100|unique:verification_restrictions',
            'applies_to_statuses' => 'required|array', 'restriction_type' => 'required|in:block_feature,quota_limit',
            'quota_value' => 'nullable|integer|min:1', 'feature_key' => 'nullable|string|max:100',
        ]);
        return response()->json(['status' => 'success', 'data' => $this->service->createRestriction($data)], 201);
    }

    // ═══════════════ FR-KYC-005: Admin Review ════════════════

    public function listPending(Request $request): JsonResponse
    {
        $this->authorize('update', VerificationCase::class);

        $pending = VerificationCase::query()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->pendingReview()
            ->with('documents')
            ->orderBy('submitted_at')
            ->paginate(20);

        return response()->json(['status' => 'success', 'data' => $pending]);
    }

    public function review(Request $request, string $caseId): JsonResponse
    {
        $case = VerificationCase::withoutGlobalScopes()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('id', $caseId)
            ->firstOrFail();

        $this->authorize('review', $case);

        $data = $request->validate([
            'decision' => 'required|in:approved,rejected,needs_more_info',
            'reason'   => 'nullable|string', 'document_decisions' => 'nullable|array',
        ]);
        $reviewed = $this->service->reviewCase((string) $case->id, $request->user()->id, $data['decision'], $data['reason'] ?? null, $data['document_decisions'] ?? null);
        return response()->json(['status' => 'success', 'data' => $reviewed]);
    }

    // ═══════════════ FR-KYC-006: Status Display ══════════════

    public function statusDisplay(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VerificationCase::class);

        return response()->json(['status' => 'success', 'data' => $this->service->getStatusDisplay($this->resolveCurrentAccountId($request))]);
    }

    // ═══════════════ FR-KYC-007: Document Download ═══════════

    public function downloadDocument(Request $request, string $documentId): JsonResponse
    {
        $document = VerificationDocument::withoutGlobalScopes()
            ->where('id', $documentId)
            ->whereHas('case', function ($query) use ($request): void {
                $query->where('account_id', $this->resolveCurrentAccountId($request));
            })
            ->firstOrFail();

        $this->authorize('viewDocuments', $document);

        return response()->json(['status' => 'success', 'data' => ['url' => $this->service->getDocumentDownloadUrl((string) $document->id, $request->user()->id)]]);
    }

    // ═══════════════ FR-KYC-008: Audit Log ═══════════════════

    public function auditLog(Request $request, string $caseId): JsonResponse
    {
        $case = VerificationCase::withoutGlobalScopes()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('id', $caseId)
            ->firstOrFail();

        $this->authorize('audit', $case);

        return response()->json(['status' => 'success', 'data' => $this->service->getAuditLog((string) $case->id)]);
    }

    public function exportAuditLog(Request $request): JsonResponse
    {
        $this->authorize('exportAudit', VerificationCase::class);

        $data = $request->validate(['case_id' => 'nullable|uuid', 'from' => 'nullable|date', 'to' => 'nullable|date']);
        $accountId = $this->resolveCurrentAccountId($request);

        $caseIdsQuery = VerificationCase::query()
            ->where('account_id', $accountId)
            ->select('id');

        if (!empty($data['case_id'])) {
            $caseIdsQuery->where('id', $data['case_id']);
        }

        $query = KycAuditLog::query()->whereIn('case_id', $caseIdsQuery);
        if (!empty($data['from'])) {
            $query->where('created_at', '>=', $data['from']);
        }
        if (!empty($data['to'])) {
            $query->where('created_at', '<=', $data['to']);
        }

        return response()->json(['status' => 'success', 'data' => $query->orderByDesc('created_at')->get()]);
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
