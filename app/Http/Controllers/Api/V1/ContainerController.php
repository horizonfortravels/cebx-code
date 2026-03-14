<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Container;
use App\Models\ContainerShipment;
use App\Models\Shipment;
use App\Models\Vessel;
use App\Models\VesselSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ContainerController extends Controller
{
    public function vesselsIndex(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Container::class);

        $query = Vessel::query()->where('account_id', $this->currentAccountId());

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->vessel_type) {
            $query->where('vessel_type', $request->vessel_type);
        }

        if ($request->search) {
            $query->where(function ($builder) use ($request): void {
                $builder
                    ->where('vessel_name', 'like', '%' . $request->search . '%')
                    ->orWhere('imo_number', 'like', '%' . $request->search . '%');
            });
        }

        return response()->json([
            'data' => $query->with('activeSchedules')->orderBy('vessel_name')->paginate($request->per_page ?? 25),
        ]);
    }

    public function vesselsStore(Request $request): JsonResponse
    {
        $this->authorize('create', Container::class);

        $validated = $request->validate([
            'vessel_name' => 'required|string|max:200',
            'imo_number' => 'nullable|string|max:20',
            'mmsi' => 'nullable|string|max:20',
            'call_sign' => 'nullable|string|max:20',
            'flag' => 'nullable|string|max:3',
            'vessel_type' => 'required|in:container,bulk,tanker,roro,general',
            'operator' => 'nullable|string|max:200',
            'capacity_teu' => 'nullable|integer',
            'max_deadweight' => 'nullable|numeric',
        ]);

        $validated['account_id'] = $this->currentAccountId();

        return response()->json([
            'data' => Vessel::create($validated),
            'message' => 'طھظ… ط¥ط¶ط§ظپط© ط§ظ„ط³ظپظٹظ†ط©',
        ], 201);
    }

    public function vesselsShow(string $id): JsonResponse
    {
        $this->authorize('viewAny', Container::class);

        $vessel = Vessel::query()
            ->where('account_id', $this->currentAccountId())
            ->with('schedules')
            ->where('id', $id)
            ->firstOrFail();

        return response()->json(['data' => $vessel]);
    }

    public function vesselsUpdate(Request $request, string $id): JsonResponse
    {
        $this->authorize('create', Container::class);

        $vessel = Vessel::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $id)
            ->firstOrFail();

        $vessel->update($request->only([
            'vessel_name',
            'imo_number',
            'mmsi',
            'call_sign',
            'flag',
            'vessel_type',
            'operator',
            'capacity_teu',
            'max_deadweight',
            'status',
        ]));

        return response()->json([
            'data' => $vessel,
            'message' => 'طھظ… طھط­ط¯ظٹط« ط§ظ„ط³ظپظٹظ†ط©',
        ]);
    }

    public function vesselsDestroy(string $id): JsonResponse
    {
        $this->authorize('create', Container::class);

        Vessel::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $id)
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'طھظ… ط­ط°ظپ ط§ظ„ط³ظپظٹظ†ط©']);
    }

    public function schedulesIndex(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Container::class);

        $query = VesselSchedule::query()
            ->where('account_id', $this->currentAccountId())
            ->with('vessel:id,vessel_name,vessel_type');

        if ($request->vessel_id) {
            $query->where('vessel_id', $request->vessel_id);
        }

        if ($request->port_of_loading) {
            $query->where('port_of_loading', $request->port_of_loading);
        }

        if ($request->port_of_discharge) {
            $query->where('port_of_discharge', $request->port_of_discharge);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->upcoming) {
            $query->where('etd', '>=', now());
        }

        return response()->json([
            'data' => $query->orderBy('etd')->paginate($request->per_page ?? 25),
        ]);
    }

    public function schedulesStore(Request $request): JsonResponse
    {
        $this->authorize('create', Container::class);

        $validated = $request->validate([
            'vessel_id' => 'required|uuid|exists:vessels,id',
            'voyage_number' => 'required|string|max:50',
            'service_route' => 'nullable|string|max:100',
            'port_of_loading' => 'required|string|max:5',
            'port_of_loading_name' => 'nullable|string|max:200',
            'port_of_discharge' => 'required|string|max:5',
            'port_of_discharge_name' => 'nullable|string|max:200',
            'etd' => 'required|date',
            'eta' => 'required|date|after:etd',
            'cut_off_date' => 'nullable|date|before:etd',
            'transit_days' => 'nullable|integer',
            'port_calls' => 'nullable|array',
        ]);

        Vessel::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $validated['vessel_id'])
            ->firstOrFail();

        $validated['account_id'] = $this->currentAccountId();

        return response()->json([
            'data' => VesselSchedule::create($validated),
            'message' => 'طھظ… ط¥ط¶ط§ظپط© ط¬ط¯ظˆظ„ ط§ظ„ط±ط­ظ„ط©',
        ], 201);
    }

    public function schedulesShow(string $id): JsonResponse
    {
        $this->authorize('viewAny', Container::class);

        $schedule = VesselSchedule::query()
            ->where('account_id', $this->currentAccountId())
            ->with(['vessel', 'containers'])
            ->where('id', $id)
            ->firstOrFail();

        return response()->json(['data' => $schedule]);
    }

    public function schedulesUpdate(Request $request, string $id): JsonResponse
    {
        $this->authorize('create', Container::class);

        $schedule = VesselSchedule::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $id)
            ->firstOrFail();

        $schedule->update($request->only([
            'voyage_number',
            'etd',
            'eta',
            'atd',
            'ata',
            'cut_off_date',
            'transit_days',
            'status',
            'port_calls',
        ]));

        return response()->json([
            'data' => $schedule,
            'message' => 'طھظ… طھط­ط¯ظٹط« ط§ظ„ط¬ط¯ظˆظ„',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Container::class);

        $query = $this->scopedContainerQuery()
            ->with('vesselSchedule.vessel:id,vessel_name');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->size) {
            $query->where('size', $request->size);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->vessel_schedule_id) {
            $query->where('vessel_schedule_id', $request->vessel_schedule_id);
        }

        if ($request->search) {
            $query->where('container_number', 'like', '%' . $request->search . '%');
        }

        return response()->json([
            'data' => $query->orderByDesc('created_at')->paginate($request->per_page ?? 25),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Container::class);

        $validated = $request->validate([
            'container_number' => 'required|string|max:15',
            'size' => 'required|in:20ft,40ft,40ft_hc,45ft',
            'type' => 'required|in:dry,reefer,open_top,flat_rack,tank,special',
            'vessel_schedule_id' => 'nullable|uuid|exists:vessel_schedules,id',
            'seal_number' => 'nullable|string|max:50',
            'tare_weight' => 'nullable|numeric',
            'max_payload' => 'nullable|numeric',
            'origin_branch_id' => 'nullable|uuid|exists:branches,id',
            'destination_branch_id' => 'nullable|uuid|exists:branches,id',
            'temperature_min' => 'nullable|numeric',
            'temperature_max' => 'nullable|numeric',
        ]);

        if (!empty($validated['vessel_schedule_id'])) {
            $validated['vessel_schedule_id'] = $this->findScheduleForCurrentAccount($validated['vessel_schedule_id'])->id;
        }

        if (!empty($validated['origin_branch_id'])) {
            $validated['origin_branch_id'] = $this->findBranchForCurrentAccount($validated['origin_branch_id'])->id;
        }

        if (!empty($validated['destination_branch_id'])) {
            $validated['destination_branch_id'] = $this->findBranchForCurrentAccount($validated['destination_branch_id'])->id;
        }

        $validated['account_id'] = $this->currentAccountId();

        return response()->json([
            'data' => Container::create($validated),
            'message' => 'طھظ… ط¥ط¶ط§ظپط© ط§ظ„ط­ط§ظˆظٹط©',
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $container = $this->findContainerForCurrentAccount($id, [
            'vesselSchedule.vessel',
            'shipments',
            'originBranch',
            'destinationBranch',
        ]);
        $this->authorize('view', $container);

        return response()->json(['data' => $container]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $container = $this->findContainerForCurrentAccount($id);
        $this->authorize('update', $container);

        $payload = $request->only([
            'seal_number',
            'current_weight',
            'location',
            'status',
            'temperature_min',
            'temperature_max',
            'vessel_schedule_id',
        ]);

        if (!empty($payload['vessel_schedule_id'])) {
            $payload['vessel_schedule_id'] = $this->findScheduleForCurrentAccount((string) $payload['vessel_schedule_id'])->id;
        }

        $container->update($payload);

        return response()->json([
            'data' => $container,
            'message' => 'طھظ… طھط­ط¯ظٹط« ط§ظ„ط­ط§ظˆظٹط©',
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $container = $this->findContainerForCurrentAccount($id);
        $this->authorize('delete', $container);

        $container->delete();

        return response()->json(['message' => 'طھظ… ط­ط°ظپ ط§ظ„ط­ط§ظˆظٹط©']);
    }

    public function shipments(string $id): JsonResponse
    {
        $container = $this->findContainerForCurrentAccount($id);
        $this->authorize('view', $container);

        return response()->json([
            'data' => $container->shipments()->orderByDesc('container_shipments.loaded_at')->get(),
        ]);
    }

    public function loadShipment(Request $request, string $id): JsonResponse
    {
        $container = $this->findContainerForCurrentAccount($id);
        $this->authorize('assignShipment', $container);

        $request->validate([
            'shipment_id' => 'required|uuid|exists:shipments,id',
            'packages_count' => 'nullable|integer|min:1',
            'weight' => 'nullable|numeric',
            'volume_cbm' => 'nullable|numeric',
        ]);

        $shipment = $this->findShipmentForCurrentAccount($request->shipment_id);

        ContainerShipment::create([
            'container_id' => $container->id,
            'shipment_id' => $shipment->id,
            'packages_count' => $request->packages_count ?? 1,
            'weight' => $request->weight,
            'volume_cbm' => $request->volume_cbm,
            'loaded_at' => now(),
        ]);

        if ($request->filled('weight')) {
            $container->increment('current_weight', (float) $request->weight);
        }

        $container->update(['status' => 'loading']);

        return response()->json(['message' => 'طھظ… طھط­ظ…ظٹظ„ ط§ظ„ط´ط­ظ†ط© ط¹ظ„ظ‰ ط§ظ„ط­ط§ظˆظٹط©']);
    }

    public function assignShipment(Request $request, string $id): JsonResponse
    {
        return $this->loadShipment($request, $id);
    }

    public function unloadShipment(string $id, string $shipmentId): JsonResponse
    {
        $container = $this->findContainerForCurrentAccount($id);
        $this->authorize('unloadShipment', $container);

        $shipment = $this->findShipmentForCurrentAccount($shipmentId);

        $containerShipment = ContainerShipment::query()
            ->where('container_id', $container->id)
            ->where('shipment_id', $shipment->id)
            ->firstOrFail();

        $containerShipment->update(['unloaded_at' => now()]);

        if ($containerShipment->weight) {
            $container->decrement('current_weight', $containerShipment->weight);
        }

        return response()->json(['message' => 'طھظ… طھظپط±ظٹط؛ ط§ظ„ط´ط­ظ†ط© ظ…ظ† ط§ظ„ط­ط§ظˆظٹط©']);
    }

    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Container::class);

        $containerQuery = $this->scopedContainerQuery();

        return response()->json(['data' => [
            'total_containers' => (clone $containerQuery)->count(),
            'by_status' => (clone $containerQuery)->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status'),
            'by_size' => (clone $containerQuery)->selectRaw('size, count(*) as count')->groupBy('size')->pluck('count', 'size'),
            'total_vessels' => Schema::hasColumn('vessels', 'account_id')
                ? Vessel::query()->where('account_id', $this->currentAccountId())->count()
                : 0,
            'upcoming_voyages' => Schema::hasColumn('vessel_schedules', 'account_id')
                ? VesselSchedule::query()->where('account_id', $this->currentAccountId())->where('etd', '>=', now())->count()
                : 0,
        ]]);
    }

    /**
     * @param array<int, string> $with
     */
    private function findContainerForCurrentAccount(string $id, array $with = []): Container
    {
        $query = $this->scopedContainerQuery();

        if ($with !== []) {
            $query->with($with);
        }

        return $query->where('id', $id)->firstOrFail();
    }

    private function findScheduleForCurrentAccount(string $id): VesselSchedule
    {
        return VesselSchedule::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $id)
            ->firstOrFail();
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

    private function scopedContainerQuery(): Builder
    {
        if (Schema::hasColumn('containers', 'account_id')) {
            return Container::query()->where('account_id', $this->currentAccountId());
        }

        return Container::query()->whereHas('shipments', function (Builder $builder): void {
            $builder->where('account_id', $this->currentAccountId());
        });
    }
}
