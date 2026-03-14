<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Vessel;
use App\Models\VesselSchedule;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class VesselScheduleController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    public function listVessels(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vessel::class);

        $query = Vessel::query();
        if (Schema::hasColumn('vessels', 'account_id')) {
            $query->where('account_id', $this->currentAccountId());
        }
        $nameColumn = $this->vesselNameColumn();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where($nameColumn, 'like', '%' . $request->search . '%');
        }

        return response()->json(['data' => $query->withCount('schedules')->paginate(20)]);
    }

    public function createVessel(Request $request): JsonResponse
    {
        $this->authorize('create', Vessel::class);

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'imo_number' => 'nullable|string|max:20',
            'flag' => 'nullable|string|size:2',
            'vessel_type' => 'required|in:container,bulk,tanker,roro,general',
            'capacity_teu' => 'nullable|integer',
            'operator' => 'nullable|string|max:200',
        ]);

        $payload = [
            $this->vesselNameColumn() => $data['name'],
            $this->vesselTypeColumn() => $data['vessel_type'],
            'imo_number' => $data['imo_number'] ?? null,
            'flag' => $data['flag'] ?? null,
            'capacity_teu' => $data['capacity_teu'] ?? null,
            'operator' => $data['operator'] ?? null,
            'status' => 'active',
        ];
        if (Schema::hasColumn('vessels', 'account_id')) {
            $payload['account_id'] = $this->currentAccountId();
        }

        $vessel = Vessel::create($payload);
        $this->auditInfo('vessel.created', Vessel::class, (string) $vessel->id, $payload);

        return response()->json(['data' => $vessel], 201);
    }

    public function showVessel(string $id): JsonResponse
    {
        $vessel = $this->findVesselForCurrentAccount($id, [
            'schedules' => fn (Builder $query): Builder => $query->orderByDesc('etd')->limit(10),
        ]);
        $this->authorize('view', $vessel);

        return response()->json(['data' => $vessel]);
    }

    public function updateVessel(Request $request, string $id): JsonResponse
    {
        $vessel = $this->findVesselForCurrentAccount($id);
        $this->authorize('update', $vessel);

        $data = $request->validate([
            'name' => 'sometimes|string|max:200',
            'imo_number' => 'nullable|string|max:20',
            'flag' => 'nullable|string|size:2',
            'vessel_type' => 'sometimes|in:container,bulk,tanker,roro,general',
            'capacity_teu' => 'nullable|integer',
            'operator' => 'nullable|string|max:200',
            'status' => 'sometimes|in:active,inactive,maintenance',
        ]);

        $payload = [];
        if (array_key_exists('name', $data)) {
            $payload[$this->vesselNameColumn()] = $data['name'];
        }
        if (array_key_exists('vessel_type', $data)) {
            $payload[$this->vesselTypeColumn()] = $data['vessel_type'];
        }
        foreach (['imo_number', 'flag', 'capacity_teu', 'operator', 'status'] as $column) {
            if (array_key_exists($column, $data)) {
                $payload[$column] = $data[$column];
            }
        }

        $vessel->update($payload);
        $this->auditInfo('vessel.updated', Vessel::class, (string) $vessel->id, $payload);

        return response()->json(['data' => $vessel->fresh()]);
    }

    public function deleteVessel(string $id): JsonResponse
    {
        $vessel = $this->findVesselForCurrentAccount($id);
        $this->authorize('delete', $vessel);

        $vessel->delete();
        $this->auditInfo('vessel.deleted', Vessel::class, (string) $vessel->id);

        return response()->json(['message' => 'تم حذف السفينة']);
    }

    public function listSchedules(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VesselSchedule::class);

        $query = $this->scopedScheduleQuery()->with('vessel');

        if ($request->filled('vessel_id')) {
            $query->where('vessel_id', $request->vessel_id);
        }
        if ($request->filled('port_of_loading')) {
            $query->where('port_of_loading', 'like', '%' . $request->port_of_loading . '%');
        }
        if ($request->filled('port_of_discharge')) {
            $query->where('port_of_discharge', 'like', '%' . $request->port_of_discharge . '%');
        }
        if ($request->filled('from_date')) {
            $query->where('etd', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->where('etd', '<=', $request->to_date);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(['data' => $query->orderByDesc('etd')->paginate(20)]);
    }

    public function createSchedule(Request $request): JsonResponse
    {
        $this->authorize('create', VesselSchedule::class);

        $data = $request->validate([
            'vessel_id' => 'required|uuid|exists:vessels,id',
            'voyage_number' => 'required|string|max:50',
            'port_of_loading' => 'required|string|max:100',
            'port_of_discharge' => 'required|string|max:100',
            'etd' => 'required|date',
            'eta' => 'required|date|after:etd',
            'transit_time_days' => 'nullable|integer',
            'cutoff_date' => 'nullable|date|before:etd',
            'notes' => 'nullable|string',
        ]);

        $vessel = $this->findVesselForCurrentAccount($data['vessel_id']);

        $payload = [
            'account_id' => $this->currentAccountId(),
            'vessel_id' => $vessel->id,
            'voyage_number' => $data['voyage_number'],
            'port_of_loading' => $data['port_of_loading'],
            'port_of_discharge' => $data['port_of_discharge'],
            'etd' => $data['etd'],
            'eta' => $data['eta'],
            'status' => 'scheduled',
        ];

        if (Schema::hasColumn('vessel_schedules', 'transit_days') && isset($data['transit_time_days'])) {
            $payload['transit_days'] = $data['transit_time_days'];
        }
        if (Schema::hasColumn('vessel_schedules', 'cut_off_date') && isset($data['cutoff_date'])) {
            $payload['cut_off_date'] = $data['cutoff_date'];
        }
        if (Schema::hasColumn('vessel_schedules', 'metadata') && isset($data['notes'])) {
            $payload['metadata'] = ['notes' => $data['notes']];
        }

        $schedule = VesselSchedule::create($payload);

        return response()->json(['data' => $schedule->load('vessel')], 201);
    }

    public function showSchedule(string $id): JsonResponse
    {
        $schedule = $this->findScheduleForCurrentAccount($id, ['vessel', 'containers']);
        $this->authorize('view', $schedule);

        return response()->json(['data' => $schedule]);
    }

    public function updateSchedule(Request $request, string $id): JsonResponse
    {
        $schedule = $this->findScheduleForCurrentAccount($id);
        $this->authorize('update', $schedule);

        $data = $request->validate([
            'voyage_number' => 'sometimes|string|max:50',
            'port_of_loading' => 'sometimes|string|max:100',
            'port_of_discharge' => 'sometimes|string|max:100',
            'etd' => 'sometimes|date',
            'eta' => 'sometimes|date',
            'status' => 'sometimes|in:scheduled,departed,in_transit,arrived,completed,cancelled',
            'actual_departure' => 'nullable|date',
            'actual_arrival' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $payload = collect($data)
            ->only(['voyage_number', 'port_of_loading', 'port_of_discharge', 'etd', 'eta', 'status'])
            ->all();

        if (Schema::hasColumn('vessel_schedules', 'atd') && array_key_exists('actual_departure', $data)) {
            $payload['atd'] = $data['actual_departure'];
        }
        if (Schema::hasColumn('vessel_schedules', 'ata') && array_key_exists('actual_arrival', $data)) {
            $payload['ata'] = $data['actual_arrival'];
        }
        if (Schema::hasColumn('vessel_schedules', 'metadata') && array_key_exists('notes', $data)) {
            $payload['metadata'] = array_filter(['notes' => $data['notes']]);
        }

        $schedule->update($payload);
        $this->auditInfo('schedule.updated', VesselSchedule::class, (string) $schedule->id, $payload);

        return response()->json(['data' => $schedule->fresh()]);
    }

    public function deleteSchedule(string $id): JsonResponse
    {
        $schedule = $this->findScheduleForCurrentAccount($id);
        $this->authorize('delete', $schedule);

        $schedule->delete();

        return response()->json(['message' => 'تم حذف الجدول']);
    }

    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VesselSchedule::class);

        $request->validate([
            'origin_port' => 'required|string',
            'destination_port' => 'required|string',
            'from_date' => 'required|date',
        ]);

        $schedules = $this->scopedScheduleQuery()
            ->where('port_of_loading', 'like', '%' . $request->origin_port . '%')
            ->where('port_of_discharge', 'like', '%' . $request->destination_port . '%')
            ->where('etd', '>=', $request->from_date)
            ->where('status', 'scheduled')
            ->with('vessel')
            ->orderBy('etd')
            ->limit(20)
            ->get();

        return response()->json(['data' => $schedules]);
    }

    public function scheduleStats(): JsonResponse
    {
        $this->authorize('viewAny', VesselSchedule::class);

        $accountId = $this->currentAccountId();
        $arrivalColumn = Schema::hasColumn('vessel_schedules', 'ata') ? 'ata' : 'updated_at';

        return response()->json(['data' => [
            'total_vessels' => Vessel::query()->where('account_id', $accountId)->count(),
            'active_voyages' => $this->scopedScheduleQuery()
                ->whereIn('status', ['departed', 'in_transit'])
                ->count(),
            'scheduled' => $this->scopedScheduleQuery()
                ->where('status', 'scheduled')
                ->where('etd', '>=', now())
                ->count(),
            'completed_this_month' => $this->scopedScheduleQuery()
                ->where('status', 'completed')
                ->where($arrivalColumn, '>=', now()->startOfMonth())
                ->count(),
            'ports' => $this->scopedScheduleQuery()
                ->selectRaw('DISTINCT port_of_loading as port')
                ->pluck('port')
                ->merge(
                    $this->scopedScheduleQuery()
                        ->selectRaw('DISTINCT port_of_discharge as port')
                        ->pluck('port')
                )
                ->unique()
                ->values(),
        ]]);
    }

    private function findVesselForCurrentAccount(string $id, array $with = []): Vessel
    {
        $query = Vessel::query()->with($with)->where('id', $id);

        if (Schema::hasColumn('vessels', 'account_id')) {
            $query->where('account_id', $this->currentAccountId());
        }

        return $query->firstOrFail();
    }

    private function scopedScheduleQuery(): Builder
    {
        return VesselSchedule::query()->where('account_id', $this->currentAccountId());
    }

    private function findScheduleForCurrentAccount(string $id, array $with = []): VesselSchedule
    {
        return $this->scopedScheduleQuery()
            ->with($with)
            ->where('id', $id)
            ->firstOrFail();
    }

    private function vesselNameColumn(): string
    {
        return Schema::hasColumn('vessels', 'vessel_name') ? 'vessel_name' : 'name';
    }

    private function vesselTypeColumn(): string
    {
        return Schema::hasColumn('vessels', 'vessel_type') ? 'vessel_type' : 'type';
    }

    private function currentAccountId(): string
    {
        return trim((string) app('current_account_id'));
    }

    public function index(Request $request): JsonResponse
    {
        return $this->listSchedules($request);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->createSchedule($request);
    }

    public function show(string $id): JsonResponse
    {
        return $this->showSchedule($id);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        return $this->updateSchedule($request, $id);
    }

    public function destroy(string $id): JsonResponse
    {
        return $this->deleteSchedule($id);
    }

    private function auditInfo(string $action, string $entityType, string $entityId, ?array $newValues = null): void
    {
        $accountId = $this->currentAccountId();
        if ($accountId === '') {
            return;
        }

        $userId = auth()->check() ? (string) auth()->id() : null;

        $this->audit->info(
            $accountId,
            $userId,
            $action,
            AuditLog::CATEGORY_ACCOUNT,
            $entityType,
            $entityId,
            null,
            $newValues
        );
    }
}
