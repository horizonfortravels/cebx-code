<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\ProofOfDelivery;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DriverController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Driver::class);

        $query = $this->scopedDriverQuery()
            ->with('branch:id,name,city');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->branch_id) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->available) {
            $query->available();
        }

        if ($request->search) {
            $query->where(function ($builder) use ($request): void {
                $builder
                    ->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('phone', 'like', '%' . $request->search . '%')
                    ->orWhere('vehicle_plate', 'like', '%' . $request->search . '%');
            });
        }

        return response()->json([
            'data' => $query->orderBy('name')->paginate($request->per_page ?? 25),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Driver::class);

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email',
            'license_number' => 'required|string|max:50',
            'license_expiry' => 'required|date|after:today',
            'vehicle_type' => 'nullable|string|max:50',
            'vehicle_plate' => 'nullable|string|max:30',
            'id_number' => 'nullable|string|max:30',
            'nationality' => 'nullable|string|size:2',
            'branch_id' => 'nullable|uuid|exists:branches,id',
            'zones' => 'nullable|array',
        ]);

        if (!empty($validated['branch_id'])) {
            $validated['branch_id'] = $this->findBranchForCurrentAccount($validated['branch_id'])->id;
        }

        $validated['account_id'] = $this->currentAccountId();
        $driver = Driver::create($validated);

        return response()->json([
            'data' => $driver,
            'message' => 'طھظ… ط¥ط¶ط§ظپط© ط§ظ„ط³ط§ط¦ظ‚ ط¨ظ†ط¬ط§ط­',
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $shipmentColumns = $this->shipmentSelectColumns(['status', 'tracking_number']);

        $driver = $this->findDriverForCurrentAccount($id, [
            'branch:id,name,city',
            'activeAssignments.shipment' => static function ($query) use ($shipmentColumns): void {
                $query->select($shipmentColumns);
            },
        ]);
        $this->authorize('view', $driver);

        $driver->success_rate = $driver->getSuccessRate();

        return response()->json(['data' => $driver]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $driver = $this->findDriverForCurrentAccount($id);
        $this->authorize('update', $driver);

        $payload = $request->only([
            'name',
            'phone',
            'email',
            'license_number',
            'license_expiry',
            'vehicle_type',
            'vehicle_plate',
            'id_number',
            'nationality',
            'branch_id',
            'status',
            'zones',
            'photo_url',
        ]);

        if (!empty($payload['branch_id'])) {
            $payload['branch_id'] = $this->findBranchForCurrentAccount((string) $payload['branch_id'])->id;
        }

        $driver->update($payload);

        return response()->json([
            'data' => $driver,
            'message' => 'طھظ… طھط­ط¯ظٹط« ط¨ظٹط§ظ†ط§طھ ط§ظ„ط³ط§ط¦ظ‚',
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $driver = $this->findDriverForCurrentAccount($id);
        $this->authorize('delete', $driver);

        if ($driver->activeAssignments()->exists()) {
            return response()->json(['message' => 'ظ„ط§ ظٹظ…ظƒظ† ط­ط°ظپ ط³ط§ط¦ظ‚ ظ„ط¯ظٹظ‡ ظ…ظ‡ط§ظ… ظ†ط´ط·ط©'], 422);
        }

        $driver->delete();

        return response()->json(['message' => 'طھظ… ط­ط°ظپ ط§ظ„ط³ط§ط¦ظ‚']);
    }

    public function updateLocation(Request $request, string $id): JsonResponse
    {
        $request->validate(['latitude' => 'required|numeric', 'longitude' => 'required|numeric']);

        $driver = $this->findDriverForCurrentAccount($id);
        $this->authorize('updateLocation', $driver);

        $driver->update([
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'location_updated_at' => now(),
        ]);

        return response()->json(['message' => 'طھظ… طھط­ط¯ظٹط« ط§ظ„ظ…ظˆظ‚ط¹']);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate(['status' => 'required|in:available,on_duty,on_delivery,off_duty,suspended']);

        $driver = $this->findDriverForCurrentAccount($id);
        $this->authorize('updateStatus', $driver);

        $driver->update(['status' => $request->status]);

        return response()->json(['message' => 'طھظ… طھط­ط¯ظٹط« ط­ط§ظ„ط© ط§ظ„ط³ط§ط¦ظ‚']);
    }

    public function toggle(Request $request, string $id): JsonResponse
    {
        $driver = $this->findDriverForCurrentAccount($id);
        $this->authorize('updateStatus', $driver);

        $nextStatus = $driver->status === 'suspended' ? 'available' : 'suspended';
        $driver->update(['status' => $nextStatus]);

        return response()->json([
            'data' => $driver,
            'message' => 'طھظ… طھط­ط¯ظٹط« ط­ط§ظ„ط© ط§ظ„ط³ط§ط¦ظ‚',
        ]);
    }

    public function assignments(Request $request): JsonResponse
    {
        $this->authorize('viewAssignments', Driver::class);

        $shipmentColumns = $this->shipmentSelectColumns([
            'status',
            'tracking_number',
            'recipient_name',
            'recipient_phone',
        ]);

        $query = DeliveryAssignment::query()
            ->where('account_id', $this->currentAccountId())
            ->with([
                'driver:id,name,phone',
                'shipment' => static function ($query) use ($shipmentColumns): void {
                    $query->select($shipmentColumns);
                },
            ]);

        if ($request->driver_id) {
            $query->where('driver_id', $request->driver_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->date) {
            $query->whereDate('scheduled_at', $request->date);
        }

        if ($request->active) {
            $query->whereNotIn('status', ['delivered', 'failed', 'returned', 'cancelled']);
        }

        return response()->json([
            'data' => $query->orderByDesc('created_at')->paginate($request->per_page ?? 25),
        ]);
    }

    public function assign(Request $request): JsonResponse
    {
        $this->authorize('createAssignment', Driver::class);

        $validated = $request->validate([
            'shipment_id' => 'required|uuid|exists:shipments,id',
            'driver_id' => 'required|uuid|exists:drivers,id',
            'type' => 'required|in:pickup,delivery,return',
            'scheduled_at' => 'nullable|date',
            'special_instructions' => 'nullable|string|max:1000',
            'pickup_lat' => 'nullable|numeric',
            'pickup_lng' => 'nullable|numeric',
            'delivery_lat' => 'nullable|numeric',
            'delivery_lng' => 'nullable|numeric',
        ]);

        $driver = $this->findDriverForCurrentAccount($validated['driver_id']);
        $shipment = $this->findShipmentForCurrentAccount($validated['shipment_id']);

        $validated['account_id'] = $this->currentAccountId();
        $validated['driver_id'] = $driver->id;
        $validated['shipment_id'] = $shipment->id;
        $validated['assignment_number'] = DeliveryAssignment::generateNumber();
        $validated['status'] = 'assigned';

        if ($request->pickup_lat && $request->delivery_lat) {
            $validated['distance_km'] = $this->haversineDistance(
                $request->pickup_lat,
                $request->pickup_lng,
                $request->delivery_lat,
                $request->delivery_lng
            );
            $validated['estimated_minutes'] = max(10, round($validated['distance_km'] * 3));
        }

        $assignment = DeliveryAssignment::create($validated);

        $driver->update(['status' => 'on_delivery']);
        $shipment->update(['driver_id' => $driver->id]);

        return response()->json([
            'data' => $assignment->load(['driver', 'shipment']),
            'message' => 'طھظ… طھط¹ظٹظٹظ† ظ…ظ‡ظ…ط© ط§ظ„طھظˆطµظٹظ„',
        ], 201);
    }

    public function showAssignment(string $id): JsonResponse
    {
        $assignment = $this->findAssignmentForCurrentAccount($id, ['driver', 'shipment', 'branch', 'proofOfDelivery']);
        $this->authorize('view', $assignment);

        return response()->json(['data' => $assignment]);
    }

    public function updateAssignmentStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:accepted,rejected,en_route_pickup,picked_up,en_route_delivery,arrived,attempting,delivered,failed,returned,cancelled',
            'failure_reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        $assignment = $this->findAssignmentForCurrentAccount($id);
        $this->authorize('manageAssignment', $assignment);

        $updates = ['status' => $request->status];

        match ($request->status) {
            'accepted' => $updates['accepted_at'] = now(),
            'picked_up' => $updates['picked_up_at'] = now(),
            'delivered' => $updates['delivered_at'] = now(),
            'failed' => $updates['failure_reason'] = $request->failure_reason,
            default => null,
        };

        if ($request->notes) {
            $updates['delivery_notes'] = $request->notes;
        }

        $assignment->update($updates);

        if ($request->status === 'delivered') {
            $assignment->driver?->update(['status' => 'available']);
            $assignment->driver?->increment('total_deliveries');
            $assignment->driver?->increment('successful_deliveries');
            $assignment->shipment?->update(['status' => 'delivered', 'actual_delivery_at' => now()]);
        } elseif ($request->status === 'failed') {
            $assignment->driver?->increment('total_deliveries');
            if ($assignment->attempt_number < $assignment->max_attempts) {
                $assignment->update(['attempt_number' => $assignment->attempt_number + 1]);
            }
        } elseif (in_array($request->status, ['rejected', 'cancelled'], true)) {
            $assignment->driver?->update(['status' => 'available']);
        } elseif ($request->status === 'en_route_delivery') {
            $assignment->shipment?->update(['status' => 'out_for_delivery']);
        }

        return response()->json([
            'data' => $assignment->fresh(),
            'message' => 'طھظ… طھط­ط¯ظٹط« ط­ط§ظ„ط© ط§ظ„طھظˆطµظٹظ„',
        ]);
    }

    public function completeDelivery(Request $request, string $id): JsonResponse
    {
        $request->merge(['status' => 'delivered']);

        return $this->updateAssignmentStatus($request, $id);
    }

    public function submitPod(Request $request, string $assignmentId): JsonResponse
    {
        $assignment = $this->findAssignmentForCurrentAccount($assignmentId);
        $this->authorize('submitPod', $assignment);

        $request->validate([
            'pod_type' => 'required|in:signature,otp,photo,pin,biometric',
            'recipient_name' => 'required|string|max:200',
            'recipient_relation' => 'nullable|string|max:100',
            'recipient_id_number' => 'nullable|string|max:30',
            'signature_data' => 'required_if:pod_type,signature|nullable|string',
            'otp_code' => 'required_if:pod_type,otp|nullable|string|max:10',
            'photo' => 'required_if:pod_type,photo|nullable|file|image|max:10240',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        $payload = [
            'assignment_id' => $assignment->id,
            'shipment_id' => $assignment->shipment_id,
            'pod_type' => $request->pod_type,
            'recipient_name' => $request->recipient_name,
            'recipient_relation' => $request->recipient_relation,
            'recipient_id_number' => $request->recipient_id_number,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'captured_at' => now(),
            'notes' => $request->notes,
        ];

        if ($request->pod_type === 'signature') {
            $payload['signature_data'] = $request->signature_data;
        } elseif ($request->pod_type === 'otp') {
            $payload['otp_code'] = $request->otp_code;
            $payload['otp_verified'] = true;
        } elseif ($request->pod_type === 'photo' && $request->hasFile('photo')) {
            $payload['photo_url'] = $request->file('photo')->store('pod/' . $assignment->id, 'public');
        }

        $pod = ProofOfDelivery::create($payload);

        $assignment->update(['status' => 'delivered', 'delivered_at' => now()]);
        $assignment->shipment?->update([
            'status' => 'delivered',
            'actual_delivery_at' => now(),
            'pod_status' => 'confirmed',
        ]);

        if ($assignment->driver) {
            $assignment->driver->increment('total_deliveries');
            $assignment->driver->increment('successful_deliveries');
            $assignment->driver->update(['status' => 'available']);
        }

        return response()->json([
            'data' => $pod,
            'message' => 'طھظ… طھط£ظƒظٹط¯ ط§ظ„طھط³ظ„ظٹظ… ط¨ظ†ط¬ط§ط­',
        ], 201);
    }

    public function showPod(string $assignmentId): JsonResponse
    {
        $pod = ProofOfDelivery::query()
            ->where('assignment_id', $assignmentId)
            ->whereHas('assignment', function ($builder): void {
                $builder->where('account_id', $this->currentAccountId());
            })
            ->with('assignment')
            ->firstOrFail();
        $this->authorize('viewPod', $pod);

        return response()->json(['data' => $pod]);
    }

    public function pods(Request $request): JsonResponse
    {
        $this->authorize('viewPods', Driver::class);

        $pods = ProofOfDelivery::query()
            ->whereHas('assignment', function ($builder): void {
                $builder->where('account_id', $this->currentAccountId());
            })
            ->with(['assignment', 'shipment'])
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 25);

        return response()->json(['data' => $pods]);
    }

    public function getPod(string $id): JsonResponse
    {
        $pod = ProofOfDelivery::query()
            ->where('id', $id)
            ->whereHas('assignment', function ($builder): void {
                $builder->where('account_id', $this->currentAccountId());
            })
            ->with('assignment')
            ->firstOrFail();
        $this->authorize('viewPod', $pod);

        return response()->json(['data' => $pod]);
    }

    public function generateOtp(Request $request, string $assignmentId): JsonResponse
    {
        $assignment = $this->findAssignmentForCurrentAccount($assignmentId);
        $this->authorize('manageAssignment', $assignment);

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $assignment->update([
            'metadata' => array_merge($assignment->metadata ?? [], [
                'otp' => $otp,
                'otp_expires' => now()->addMinutes(15)->toIso8601String(),
            ]),
        ]);

        return response()->json([
            'message' => 'طھظ… ط¥ط±ط³ط§ظ„ ط±ظ…ط² ط§ظ„طھط­ظ‚ظ‚',
            'otp_preview' => $otp,
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Driver::class);

        $accountId = $this->currentAccountId();
        $driverQuery = $this->scopedDriverQuery();
        $assignmentQuery = DeliveryAssignment::query()->where('account_id', $accountId);

        return response()->json(['data' => [
            'total_drivers' => (clone $driverQuery)->count(),
            'available' => (clone $driverQuery)->where('status', 'available')->count(),
            'on_delivery' => (clone $driverQuery)->where('status', 'on_delivery')->count(),
            'off_duty' => (clone $driverQuery)->where('status', 'off_duty')->count(),
            'suspended' => (clone $driverQuery)->where('status', 'suspended')->count(),
            'total_assignments' => (clone $assignmentQuery)->count(),
            'active_assignments' => (clone $assignmentQuery)->whereNotIn('status', ['delivered', 'failed', 'returned', 'cancelled'])->count(),
            'delivered_today' => (clone $assignmentQuery)->where('status', 'delivered')->whereDate('delivered_at', today())->count(),
            'failed_today' => (clone $assignmentQuery)->where('status', 'failed')->whereDate('created_at', today())->count(),
            'avg_delivery_time' => round((clone $assignmentQuery)->where('status', 'delivered')->whereNotNull('accepted_at')->whereNotNull('delivered_at')->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, accepted_at, delivered_at)) as avg')->value('avg') ?? 0),
            'success_rate' => (function () use ($assignmentQuery) {
                $total = (clone $assignmentQuery)->whereIn('status', ['delivered', 'failed'])->count();
                $success = (clone $assignmentQuery)->where('status', 'delivered')->count();

                return $total > 0 ? round(($success / $total) * 100, 1) : 100;
            })(),
        ]]);
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Driver::class);

        $deliveryCountColumn = Schema::hasColumn('drivers', 'total_deliveries') ? 'total_deliveries' : 'deliveries_count';
        $successCountColumn = Schema::hasColumn('drivers', 'successful_deliveries') ? 'successful_deliveries' : $deliveryCountColumn;

        $drivers = $this->scopedDriverQuery()
            ->where($deliveryCountColumn, '>', 0)
            ->orderByDesc($successCountColumn)
            ->limit(20)
            ->get()
            ->map(fn (Driver $driver): array => [
                'id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
                'total' => $driver->{$deliveryCountColumn},
                'success' => $driver->{$successCountColumn},
                'rate' => $driver->getSuccessRate(),
                'rating' => $driver->rating,
            ]);

        return response()->json(['data' => $drivers]);
    }

    /**
     * @param array<int, string> $with
     */
    private function findDriverForCurrentAccount(string $id, array $with = []): Driver
    {
        $query = $this->scopedDriverQuery();

        if ($with !== []) {
            $query->with($with);
        }

        return $query->where('id', $id)->firstOrFail();
    }

    /**
     * @param array<int, string> $with
     */
    private function findAssignmentForCurrentAccount(string $id, array $with = []): DeliveryAssignment
    {
        $query = DeliveryAssignment::query()->where('account_id', $this->currentAccountId());

        if ($with !== []) {
            $query->with($with);
        }

        return $query->where('id', $id)->firstOrFail();
    }

    private function findShipmentForCurrentAccount(string $id): Shipment
    {
        return Shipment::query()
            ->where('account_id', $this->currentAccountId())
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

    private function currentAccountId(): string
    {
        return trim((string) app('current_account_id'));
    }

    private function scopedDriverQuery(): Builder
    {
        if (Schema::hasColumn('drivers', 'account_id')) {
            return Driver::query()->where('account_id', $this->currentAccountId());
        }

        return Driver::query()->whereHas('assignments', function (Builder $builder): void {
            $builder->where('account_id', $this->currentAccountId());
        });
    }

    private function haversineDistance($lat1, $lng1, $lat2, $lng2): float
    {
        $radius = 6371;
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);
        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLng / 2) ** 2;

        return round($radius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    /**
     * @param array<int, string> $preferredColumns
     * @return array<int, string>
     */
    private function shipmentSelectColumns(array $preferredColumns = []): array
    {
        $columns = ['id'];

        foreach ($preferredColumns as $column) {
            if (Schema::hasColumn('shipments', $column)) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }
}
