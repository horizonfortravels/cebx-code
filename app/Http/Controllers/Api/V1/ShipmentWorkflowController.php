<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Container;
use App\Models\Shipment;
use App\Models\Vessel;
use App\Services\AIDelayService;
use App\Services\AuditService;
use App\Services\DutyCalculationService;
use App\Services\SLAEngineService;
use App\Services\StatusTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ShipmentWorkflowController extends Controller
{
    public function __construct(
        protected StatusTransitionService $engine,
        protected DutyCalculationService $duty,
        protected SLAEngineService $sla,
        protected AIDelayService $ai,
        protected AuditService $audit,
    ) {}

    public function statuses(): JsonResponse
    {
        $this->authorize('viewAny', self::class);

        return response()->json(['data' => $this->engine->getAllStatuses()]);
    }

    public function nextStatuses(string $id): JsonResponse
    {
        $shipment = $this->findShipmentForCurrentAccount($id);
        $this->authorize('view', [self::class, $shipment]);

        return response()->json([
            'data' => [
                'current_status' => $shipment->status,
                'available_transitions' => $this->engine->getNextStatuses((string) $shipment->status),
            ],
        ]);
    }

    public function transition(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|string',
            'notes' => 'nullable|string|max:500',
            'location' => 'nullable|string|max:200',
            'branch_id' => 'nullable|uuid|exists:branches,id',
            'metadata' => 'nullable|array',
        ]);

        $shipment = $this->findShipmentForCurrentAccount($id);
        $this->authorize('manage', [self::class, $shipment]);

        $branchId = null;
        if (!empty($data['branch_id'])) {
            $branchId = $this->findBranchForCurrentAccount($data['branch_id'])->id;
        }

        $shipment = $this->engine->transition($shipment, $data['status'], [
            'user_id' => (string) $request->user()->id,
            'notes' => $data['notes'] ?? null,
            'location' => $data['location'] ?? null,
            'branch_id' => $branchId,
            'metadata' => $data['metadata'] ?? null,
        ]);

        $this->audit->info(
            $this->currentAccountId(),
            (string) $request->user()->id,
            'shipment.transitioned',
            AuditLog::CATEGORY_TRACKING,
            Shipment::class,
            (string) $shipment->id,
            null,
            ['new_status' => $data['status']]
        );

        return response()->json([
            'data' => $shipment->load('trackingEvents'),
            'message' => 'تم تحديث الحالة بنجاح',
        ]);
    }

    public function receiveAtOrigin(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'actual_weight' => 'required|numeric|min:0.1',
            'actual_length' => 'nullable|numeric',
            'actual_width' => 'nullable|numeric',
            'actual_height' => 'nullable|numeric',
            'branch_id' => 'required|uuid|exists:branches,id',
            'notes' => 'nullable|string',
        ]);

        $shipment = $this->findShipmentForCurrentAccount($id);
        $this->authorize('manage', [self::class, $shipment]);
        $branch = $this->findBranchForCurrentAccount($data['branch_id']);

        $volumetric = 0.0;
        if (($data['actual_length'] ?? 0) && ($data['actual_width'] ?? 0) && ($data['actual_height'] ?? 0)) {
            $volumetric = ($data['actual_length'] * $data['actual_width'] * $data['actual_height']) / 5000;
        }

        $chargeableWeight = max((float) $data['actual_weight'], $volumetric);
        $weightChanged = abs($chargeableWeight - (float) ($shipment->chargeable_weight ?? 0)) > 0.1;

        $shipment->update(array_filter([
            'actual_weight' => $data['actual_weight'],
            'chargeable_weight' => $chargeableWeight,
            'total_volume' => $volumetric,
        ], static fn ($value): bool => $value !== null));

        $this->engine->transition($shipment, 'at_origin_hub', [
            'user_id' => (string) $request->user()->id,
            'branch_id' => $branch->id,
            'location' => 'origin_hub',
            'notes' => $weightChanged
                ? sprintf(
                    'Weight verified: %s kg, chargeable weight %s kg',
                    $data['actual_weight'],
                    $chargeableWeight
                )
                : ($data['notes'] ?? null),
        ]);

        return response()->json([
            'data' => $shipment->fresh(),
            'weight_changed' => $weightChanged,
            'chargeable_weight' => $chargeableWeight,
            'message' => 'تم استلام الشحنة في مركز الأصل',
        ]);
    }

    public function exportClearance(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'declaration_number' => 'nullable|string',
            'cleared' => 'required|boolean',
            'notes' => 'nullable|string',
        ]);

        $shipment = $this->findShipmentForCurrentAccount($id);
        $this->authorize('manage', [self::class, $shipment]);

        $newStatus = $data['cleared'] ? 'cleared_export' : 'held_customs';

        $this->engine->transition($shipment, $newStatus, [
            'user_id' => (string) $request->user()->id,
            'notes' => $data['notes'] ?? ($data['cleared'] ? 'Export cleared' : 'Held by customs'),
        ]);

        return response()->json([
            'data' => $shipment->fresh(),
            'message' => 'تم تحديث حالة التخليص',
        ]);
    }

    public function loadToTransit(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'vessel_id' => 'nullable|uuid',
            'container_id' => 'nullable|uuid',
            'flight_number' => 'nullable|string|max:20',
            'truck_number' => 'nullable|string|max:30',
            'eta' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $shipment = $this->findShipmentForCurrentAccount($id);
        $this->authorize('manage', [self::class, $shipment]);

        $payload = [];
        if (!empty($data['vessel_id'])) {
            $payload['vessel_id'] = $this->findVesselForCurrentAccount($data['vessel_id'])->id;
        }
        if (!empty($data['container_id'])) {
            $payload['container_id'] = $this->findContainerForCurrentAccount($data['container_id'])->id;
        }
        foreach (['flight_number', 'truck_number', 'eta'] as $column) {
            if (!empty($data[$column]) && Schema::hasColumn('shipments', $column)) {
                $payload[$column] = $data[$column];
            }
        }

        if ($payload !== []) {
            $shipment->update($payload);
        }

        $this->engine->transition($shipment, 'in_transit', [
            'user_id' => (string) $request->user()->id,
            'notes' => $data['notes'] ?? 'Shipment loaded for transit',
            'vessel_id' => $payload['vessel_id'] ?? null,
        ]);

        return response()->json([
            'data' => $shipment->fresh(),
            'message' => 'الشحنة قيد الشحن',
        ]);
    }

    public function importClearance(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'cleared' => 'required|boolean',
            'declaration_number' => 'nullable|string',
            'inspection_required' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $shipment = $this->findShipmentForCurrentAccount($id, ['items']);
        $this->authorize('manage', [self::class, $shipment]);

        $dutyCalc = $this->duty->calculate([
            'origin_country' => $shipment->origin_country,
            'destination_country' => $shipment->destination_country,
            'declared_value' => $shipment->declared_value,
            'currency' => 'SAR',
            'incoterm' => $shipment->incoterm_code ?? $shipment->incoterm ?? 'DAP',
            'inspection_required' => $data['inspection_required'] ?? false,
            'items' => $shipment->items->map(static fn ($item): array => [
                'hs_code' => $item->hs_code,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'weight' => $item->weight,
                'value' => $item->total_value ?? $item->unit_value ?? 0,
            ])->all(),
        ]);

        $this->duty->storeAsCharges((string) $shipment->id, $dutyCalc);

        $newStatus = $data['cleared'] ? 'cleared_import' : 'held_customs_dest';

        $this->engine->transition($shipment, $newStatus, [
            'user_id' => (string) $request->user()->id,
            'notes' => $data['notes'] ?? ($data['cleared'] ? 'Import cleared' : 'Held at destination customs'),
        ]);

        return response()->json([
            'data' => $shipment->fresh(),
            'duty_calculation' => $dutyCalc,
            'message' => 'تم تحديث حالة التخليص الجمركي',
        ]);
    }

    public function checkSLA(string $id): JsonResponse
    {
        $shipment = $this->findShipmentForCurrentAccount($id);
        $this->authorize('view', [self::class, $shipment]);

        return response()->json(['data' => $this->sla->checkSLA($shipment)]);
    }

    public function predictDelay(string $id): JsonResponse
    {
        $shipment = $this->findShipmentForCurrentAccount($id);
        $this->authorize('view', [self::class, $shipment]);

        return response()->json(['data' => $this->ai->predict($shipment)]);
    }

    private function findShipmentForCurrentAccount(string $id, array $with = []): Shipment
    {
        return Shipment::query()
            ->where('account_id', $this->currentAccountId())
            ->with($with)
            ->where('id', $id)
            ->firstOrFail();
    }

    private function findBranchForCurrentAccount(string $id): Branch
    {
        return Branch::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $id)
            ->firstOrFail();
    }

    private function findVesselForCurrentAccount(string $id): Vessel
    {
        return Vessel::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $id)
            ->firstOrFail();
    }

    private function findContainerForCurrentAccount(string $id): Container
    {
        return Container::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $id)
            ->firstOrFail();
    }

    private function currentAccountId(): string
    {
        return trim((string) app('current_account_id'));
    }
}
