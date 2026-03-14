<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\ProofOfDelivery;
use App\Models\Shipment;
use App\Services\AuditService;
use App\Services\StatusTransitionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LastMileDeliveryController extends Controller
{
    public function __construct(
        protected StatusTransitionService $statusEngine,
        protected AuditService $audit,
    ) {}

    public function dashboard(): JsonResponse
    {
        $this->authorize('viewAny', self::class);

        $accountId = $this->currentAccountId();
        $today = now()->startOfDay();

        return response()->json(['data' => [
            'pending_assignment' => Shipment::query()
                ->where('account_id', $accountId)
                ->whereIn('status', ['ready_for_pickup', 'picked_up', 'in_transit'])
                ->count(),
            'out_for_delivery' => Shipment::query()
                ->where('account_id', $accountId)
                ->where('status', 'out_for_delivery')
                ->count(),
            'delivered_today' => Shipment::query()
                ->where('account_id', $accountId)
                ->where('status', 'delivered')
                ->where(function (Builder $builder) use ($today): void {
                    $builder
                        ->where('actual_delivery_at', '>=', $today)
                        ->orWhere('delivered_at', '>=', $today);
                })
                ->count(),
            'failed_today' => Shipment::query()
                ->where('account_id', $accountId)
                ->whereIn('status', ['failed', 'failed_delivery'])
                ->where('updated_at', '>=', $today)
                ->count(),
            'available_drivers' => Driver::query()
                ->when(Schema::hasColumn('drivers', 'account_id'), fn (Builder $query): Builder => $query->where('account_id', $accountId))
                ->where('status', 'available')
                ->count(),
            'active_drivers' => Driver::query()
                ->when(Schema::hasColumn('drivers', 'account_id'), fn (Builder $query): Builder => $query->where('account_id', $accountId))
                ->where('status', 'on_delivery')
                ->count(),
        ]]);
    }

    public function pendingDeliveries(): JsonResponse
    {
        $this->authorize('viewAny', self::class);

        $shipments = Shipment::query()
            ->where('account_id', $this->currentAccountId())
            ->whereIn('status', ['ready_for_pickup', 'picked_up', 'in_transit'])
            ->with(['deliveryAssignment.driver:id,name,phone'])
            ->orderBy('created_at')
            ->paginate(20);

        return response()->json(['data' => $shipments]);
    }

    public function assignDriver(Request $request, string $shipmentId): JsonResponse
    {
        $data = $request->validate([
            'driver_id' => 'required|uuid|exists:drivers,id',
            'scheduled_date' => 'nullable|date',
            'scheduled_time_from' => 'nullable|date_format:H:i',
            'scheduled_time_to' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:500',
            'priority' => 'in:normal,high,urgent',
        ]);

        $shipment = $this->findShipmentForCurrentAccount($shipmentId);
        $this->authorize('assign', [self::class, $shipment]);

        $driver = $this->findDriverForAssignment($data['driver_id']);
        $activeDeliveries = DeliveryAssignment::query()
            ->where('account_id', $this->currentAccountId())
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['assigned', 'accepted', 'en_route_delivery'])
            ->count();

        if ($activeDeliveries >= (int) ($driver->max_deliveries ?? 15)) {
            return response()->json(['message' => 'السائق لديه الحد الأقصى من التوصيلات'], 422);
        }

        $scheduledAt = null;
        if (!empty($data['scheduled_date'])) {
            $scheduledAt = $data['scheduled_date'] . ' ' . ($data['scheduled_time_from'] ?? '09:00');
        }

        $assignment = DeliveryAssignment::create([
            'account_id' => $this->currentAccountId(),
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'assignment_number' => DeliveryAssignment::generateNumber(),
            'type' => 'delivery',
            'status' => 'assigned',
            'scheduled_at' => $scheduledAt,
            'delivery_notes' => $data['notes'] ?? null,
            'metadata' => array_filter([
                'priority' => $data['priority'] ?? 'normal',
                'scheduled_time_to' => $data['scheduled_time_to'] ?? null,
                'assigned_by' => (string) $request->user()->id,
            ]),
        ]);

        $driver->update(['status' => 'on_delivery']);

        $this->audit->info(
            $this->currentAccountId(),
            (string) $request->user()->id,
            'delivery.assigned',
            AuditLog::CATEGORY_ACCOUNT,
            DeliveryAssignment::class,
            (string) $assignment->id
        );

        return response()->json([
            'data' => $assignment->load(['driver:id,name,phone', 'shipment:id,tracking_number']),
            'message' => "تم تعيين السائق {$driver->name}",
        ], 201);
    }

    public function recordPOD(Request $request, string $shipmentId): JsonResponse
    {
        $data = $request->validate([
            'pod_type' => 'required|in:signature,otp,photo,combined',
            'signature_data' => 'required_if:pod_type,signature,combined|nullable|string',
            'otp_code' => 'required_if:pod_type,otp|nullable|string|max:10',
            'photo_url' => 'required_if:pod_type,photo,combined|nullable|string',
            'recipient_name' => 'required|string|max:200',
            'recipient_relation' => 'nullable|string|max:100',
            'recipient_id_number' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $shipment = $this->findShipmentForCurrentAccount($shipmentId, ['deliveryAssignment']);
        $this->authorize('submitPod', [self::class, $shipment]);

        if ($shipment->status !== 'out_for_delivery') {
            return response()->json(['message' => 'الشحنة ليست خارج للتسليم'], 422);
        }

        $assignment = $shipment->deliveryAssignment;
        if (!$assignment) {
            return response()->json(['message' => 'لا يوجد تعيين تسليم نشط لهذه الشحنة'], 422);
        }

        $pod = ProofOfDelivery::create([
            'shipment_id' => $shipment->id,
            'assignment_id' => $assignment->id,
            'pod_type' => $data['pod_type'] === 'combined' ? 'signature' : $data['pod_type'],
            'signature_data' => $data['signature_data'] ?? null,
            'otp_code' => $data['otp_code'] ?? null,
            'otp_verified' => !empty($data['otp_code']),
            'photo_url' => $data['photo_url'] ?? null,
            'recipient_name' => $data['recipient_name'],
            'recipient_relation' => $data['recipient_relation'] ?? null,
            'recipient_id_number' => $data['recipient_id_number'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'notes' => $data['notes'] ?? null,
            'captured_at' => now(),
        ]);

        $shipment->update(['status' => 'delivered', 'actual_delivery_at' => now()]);
        $assignment->update(['status' => 'delivered', 'delivered_at' => now()]);

        $remaining = DeliveryAssignment::query()
            ->where('account_id', $this->currentAccountId())
            ->where('driver_id', $assignment->driver_id)
            ->whereIn('status', ['assigned', 'accepted', 'en_route_delivery'])
            ->count();

        if ($remaining === 0) {
            Driver::query()->where('id', $assignment->driver_id)->update(['status' => 'available']);
        }

        $this->audit->info(
            $this->currentAccountId(),
            (string) $request->user()->id,
            'delivery.completed',
            AuditLog::CATEGORY_ACCOUNT,
            Shipment::class,
            (string) $shipment->id
        );

        return response()->json([
            'data' => $pod,
            'message' => 'تم تسجيل التسليم بنجاح',
        ], 201);
    }

    public function failedDelivery(Request $request, string $shipmentId): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|in:no_answer,wrong_address,refused,closed,other',
            'notes' => 'nullable|string|max:500',
            'reschedule_date' => 'nullable|date',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $shipment = $this->findShipmentForCurrentAccount($shipmentId, ['deliveryAssignment']);
        $this->authorize('recordFailure', [self::class, $shipment]);

        $shipment->update([
            'status' => 'failed',
            'status_reason' => $data['notes'] ?? $data['reason'],
        ]);

        if ($shipment->deliveryAssignment) {
            $shipment->deliveryAssignment->update([
                'status' => 'failed',
                'failure_reason' => $data['notes'] ?? $data['reason'],
                'scheduled_at' => !empty($data['reschedule_date'])
                    ? $data['reschedule_date'] . ' 09:00:00'
                    : $shipment->deliveryAssignment->scheduled_at,
                'attempt_number' => (int) ($shipment->deliveryAssignment->attempt_number ?? 1) + 1,
            ]);
        }

        return response()->json([
            'data' => $shipment->fresh(),
            'message' => 'تم تسجيل فشل التسليم',
        ]);
    }

    public function driverAssignments(Request $request, string $driverId): JsonResponse
    {
        $driver = $this->findDriverForCurrentAccount($driverId);
        $this->authorize('viewDriverAssignments', [self::class, $driver]);

        $assignments = DeliveryAssignment::query()
            ->where('account_id', $this->currentAccountId())
            ->where('driver_id', $driver->id)
            ->with(['shipment:' . implode(',', $this->deliveryShipmentSummaryColumns())])
            ->when($request->filled('status'), fn (Builder $query): Builder => $query->where('status', $request->status))
            ->when($request->filled('date'), fn (Builder $query): Builder => $query->whereDate('scheduled_at', $request->date))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['data' => $assignments]);
    }

    private function findShipmentForCurrentAccount(string $id, array $with = []): Shipment
    {
        return Shipment::query()
            ->where('account_id', $this->currentAccountId())
            ->with($with)
            ->where('id', $id)
            ->firstOrFail();
    }

    private function findDriverForCurrentAccount(string $id): Driver
    {
        $query = Driver::query()->where('id', $id);

        if (Schema::hasColumn('drivers', 'account_id')) {
            return $query
                ->where('account_id', $this->currentAccountId())
                ->firstOrFail();
        }

        if (Schema::hasTable('delivery_assignments')
            && Schema::hasColumn('delivery_assignments', 'driver_id')
            && Schema::hasColumn('delivery_assignments', 'account_id')) {
            return $query
                ->whereExists(function ($builder): void {
                    $builder
                        ->select(DB::raw(1))
                        ->from('delivery_assignments')
                        ->whereColumn('delivery_assignments.driver_id', 'drivers.id')
                        ->where('delivery_assignments.account_id', $this->currentAccountId());
                })
                ->firstOrFail();
        }

        return $query->firstOrFail();
    }

    private function currentAccountId(): string
    {
        return trim((string) app('current_account_id'));
    }

    private function findDriverForAssignment(string $id): Driver
    {
        $query = Driver::query()->where('id', $id);

        if (Schema::hasColumn('drivers', 'account_id')) {
            $query->where('account_id', $this->currentAccountId());
        }

        return $query->firstOrFail();
    }

    /**
     * @return array<int, string>
     */
    private function deliveryShipmentSummaryColumns(): array
    {
        $columns = ['id', 'status'];

        foreach (['tracking_number', 'reference_number', 'recipient_name', 'recipient_phone', 'recipient_address_1', 'recipient_address', 'recipient_city'] as $column) {
            if (Schema::hasColumn('shipments', $column)) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }
}
