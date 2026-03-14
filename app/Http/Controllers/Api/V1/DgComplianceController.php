<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\ContentDeclaration;
use App\Services\DgComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DgComplianceController — FR-DG-001→009
 *
 * API endpoints for dangerous goods compliance and content declaration.
 */
class DgComplianceController extends Controller
{
    public function __construct(private DgComplianceService $service) {}

    // ── FR-DG-001: Create Declaration ────────────────────────

    public function create(Request $request): JsonResponse
    {
        $this->authorize('create', ContentDeclaration::class);

        $request->validate([
            'shipment_id' => 'required|string|max:100',
            'locale'      => 'nullable|string|in:ar,en',
        ]);

        $declaration = $this->service->createDeclaration(
            accountId:  $this->resolveCurrentAccountId($request),
            shipmentId: $request->shipment_id,
            declaredBy: $request->user()->id,
            locale:     $request->locale ?? 'ar',
            ipAddress:  $request->ip(),
            userAgent:  $request->userAgent(),
        );

        return response()->json(['data' => $this->declarationPayload($declaration)], 201);
    }

    // ── FR-DG-002: Set DG Flag ──────────────────────────────

    public function setDgFlag(Request $request, string $declarationId): JsonResponse
    {
        $declaration = ContentDeclaration::withoutGlobalScopes()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('id', $declarationId)
            ->firstOrFail();

        $this->authorize('manage', $declaration);

        $request->validate([
            'contains_dangerous_goods' => 'required|boolean',
        ]);

        $declaration = $this->service->setDgFlag(
            declarationId: (string) $declaration->id,
            containsDg:    $request->boolean('contains_dangerous_goods'),
            actorId:       $request->user()->id,
            ipAddress:     $request->ip(),
        );

        return response()->json(['data' => $this->declarationPayload($declaration)]);
    }

    // ── FR-DG-004: Accept Waiver ────────────────────────────

    public function acceptWaiver(Request $request, string $declarationId): JsonResponse
    {
        $declaration = ContentDeclaration::withoutGlobalScopes()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('id', $declarationId)
            ->firstOrFail();

        $this->authorize('manage', $declaration);

        $request->validate([
            'locale' => 'nullable|string|in:ar,en',
        ]);

        $declaration = $this->service->acceptWaiver(
            declarationId: (string) $declaration->id,
            actorId:       $request->user()->id,
            locale:        $request->locale,
            ipAddress:     $request->ip(),
        );

        return response()->json(['data' => $this->declarationPayload($declaration)]);
    }

    // ── FR-DG-007: Validate for Issuance ────────────────────

    public function validateForIssuance(Request $request): JsonResponse
    {
        $this->authorize('manage', ContentDeclaration::class);

        $request->validate([
            'shipment_id' => 'required|string|max:100',
        ]);

        try {
            $declaration = $this->service->validateForIssuance(
                $request->shipment_id,
                $this->resolveCurrentAccountId($request),
            );
            return response()->json(['data' => ['valid' => true, 'declaration_id' => $declaration->id]]);
        } catch (BusinessException $e) {
            $payload = [
                'success' => false,
                'valid' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ];

            if ($e->getContext() !== []) {
                $payload['context'] = $e->getContext();
            }

            return response()->json($payload, $e->getStatusCode());
        }
    }

    // ── FR-DG-003: Hold Info ────────────────────────────────

    public function holdInfo(Request $request, string $declarationId): JsonResponse
    {
        $declaration = ContentDeclaration::withoutGlobalScopes()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('id', $declarationId)
            ->firstOrFail();

        $this->authorize('view', $declaration);

        $info = $this->service->getHoldInfo((string) $declaration->id);
        return response()->json(['data' => $info]);
    }

    // ── FR-DG-008: Get Declaration (RBAC-aware) ─────────────

    public function show(Request $request, string $declarationId): JsonResponse
    {
        $declaration = ContentDeclaration::withoutGlobalScopes()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('id', $declarationId)
            ->firstOrFail();

        $this->authorize('view', $declaration);

        // TODO: Check permissions for full detail vs summary
        $fullDetail = true; // Simplified for now
        $data = $this->service->getDeclaration((string) $declaration->id, $fullDetail, $request->user()->id);
        return response()->json(['data' => array_merge($data, [
            'shipment_workflow_state' => $declaration->shipment?->status,
            'is_blocked' => $declaration->isBlocked(),
            'declaration_complete' => $declaration->isReadyForIssuance(),
            'requires_disclaimer' => (bool) ($declaration->dg_flag_declared && ! $declaration->contains_dangerous_goods && ! $declaration->waiver_accepted),
            'next_action' => $this->nextActionForDeclaration($declaration),
        ])]);
    }

    public function forShipment(Request $request, string $shipmentId): JsonResponse
    {
        $this->authorize('viewAny', ContentDeclaration::class);

        $declaration = $this->service->getDeclarationForShipment($shipmentId, $this->resolveCurrentAccountId($request));

        if (!$declaration) {
            return response()->json([
                'success' => false,
                'error_code' => 'ERR_DECLARATION_NOT_FOUND',
                'message' => 'No declaration found for this shipment.',
                'next_action' => 'Select an offer for the shipment first to start the declaration step.',
            ], 404);
        }

        return response()->json(['data' => $this->declarationPayload($declaration)]);
    }

    // ── FR-DG-009: Save DG Metadata ─────────────────────────

    public function saveDgMetadata(Request $request, string $declarationId): JsonResponse
    {
        $declaration = ContentDeclaration::withoutGlobalScopes()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('id', $declarationId)
            ->firstOrFail();

        $this->authorize('manage', $declaration);

        $request->validate([
            'un_number'            => 'nullable|string|max:10',
            'dg_class'             => 'nullable|string|max:20',
            'packing_group'        => 'nullable|string|in:I,II,III',
            'proper_shipping_name' => 'nullable|string|max:300',
            'quantity'             => 'nullable|numeric|min:0',
            'quantity_unit'        => 'nullable|string|max:20',
            'description'          => 'nullable|string|max:1000',
        ]);

        $metadata = $this->service->saveDgMetadata(
            (string) $declaration->id,
            $request->only(['un_number', 'dg_class', 'packing_group', 'proper_shipping_name', 'quantity', 'quantity_unit', 'description']),
            $request->user()->id,
            $request->ip(),
        );

        return response()->json(['data' => $metadata]);
    }

    // ── FR-DG-006: Waiver Version Management ────────────────

    public function publishWaiver(Request $request): JsonResponse
    {
        $this->authorize('manage', ContentDeclaration::class);

        $request->validate([
            'version'     => 'required|string|max:20',
            'locale'      => 'required|string|in:ar,en',
            'waiver_text' => 'required|string',
        ]);

        $waiver = $this->service->publishWaiverVersion(
            $request->version,
            $request->locale,
            $request->waiver_text,
            $request->user()->id,
        );

        return response()->json(['data' => $waiver], 201);
    }

    public function activeWaiver(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ContentDeclaration::class);

        $locale = $request->query('locale', 'ar');
        $waiver = $this->service->getActiveWaiver($locale);

        if (!$waiver) {
            return response()->json(['error' => 'No active waiver found'], 404);
        }

        return response()->json(['data' => $waiver]);
    }

    public function listWaiverVersions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ContentDeclaration::class);

        $locale = $request->query('locale', 'ar');
        $versions = $this->service->listWaiverVersions($locale);
        return response()->json(['data' => $versions]);
    }

    // ── FR-DG-005: Audit Log ────────────────────────────────

    public function auditLog(Request $request, string $declarationId): JsonResponse
    {
        $declaration = ContentDeclaration::withoutGlobalScopes()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('id', $declarationId)
            ->firstOrFail();

        $this->authorize('audit', $declaration);

        // FR-DG-008: Log the view action
        $log = $this->service->getAuditLog((string) $declaration->id);
        return response()->json($log);
    }

    public function shipmentAuditLog(Request $request, string $shipmentId): JsonResponse
    {
        $declaration = ContentDeclaration::withoutGlobalScopes()
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('shipment_id', $shipmentId)
            ->firstOrFail();

        $this->authorize('audit', $declaration);

        $log = $this->service->getShipmentAuditLog($shipmentId);
        return response()->json($log);
    }

    public function exportAuditLog(Request $request): JsonResponse
    {
        $this->authorize('exportAudit', ContentDeclaration::class);

        $filters = $request->only(['from', 'to', 'action']);
        $export = $this->service->exportAuditLog($this->resolveCurrentAccountId($request), $filters);
        return response()->json(['data' => $export]);
    }

    // ── Listing ─────────────────────────────────────────────

    public function list(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ContentDeclaration::class);

        $filters = $request->only(['status', 'contains_dg']);
        $declarations = $this->service->listDeclarations($this->resolveCurrentAccountId($request), $filters);
        return response()->json($declarations);
    }

    public function listBlocked(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ContentDeclaration::class);

        $blocked = $this->service->listBlockedShipments($this->resolveCurrentAccountId($request));
        return response()->json(['data' => $blocked]);
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

    /**
     * @return array<string, mixed>
     */
    private function declarationPayload(ContentDeclaration $declaration): array
    {
        $declaration->loadMissing(['shipment', 'waiverVersion']);

        return array_merge($declaration->toDetailArray(), [
            'shipment_workflow_state' => $declaration->shipment?->status,
            'is_blocked' => $declaration->isBlocked(),
            'declaration_complete' => $declaration->isReadyForIssuance(),
            'requires_disclaimer' => (bool) ($declaration->dg_flag_declared && ! $declaration->contains_dangerous_goods && ! $declaration->waiver_accepted),
            'next_action' => $this->nextActionForDeclaration($declaration),
            'waiver_version' => $declaration->waiverVersion?->version,
        ]);
    }

    private function nextActionForDeclaration(ContentDeclaration $declaration): string
    {
        if (! $declaration->dg_flag_declared) {
            return 'Declare whether the shipment contains dangerous goods before the workflow can continue.';
        }

        if ($declaration->contains_dangerous_goods) {
            return 'Contact support for manual dangerous goods handling. Normal automated issuance cannot continue.';
        }

        if (! $declaration->waiver_accepted) {
            return 'Accept the legal disclaimer for a non-dangerous-goods shipment before the workflow can continue.';
        }

        return 'The declaration gate is complete. The shipment is ready for the next workflow phase when it is enabled.';
    }
}
