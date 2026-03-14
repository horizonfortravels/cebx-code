<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Vessel;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class VesselController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vessel::class);

        $query = $this->scopedVesselQuery();
        $nameColumn = $this->vesselNameColumn();

        if ($request->filled('search')) {
            $query->where(function (Builder $builder) use ($request, $nameColumn): void {
                $builder
                    ->where($nameColumn, 'like', '%' . $request->search . '%')
                    ->orWhere('imo_number', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(
            $query->withCount('containers')->latest()->paginate($request->integer('per_page', 25))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Vessel::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'imo_number' => 'nullable|string|max:50',
            'flag' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:50',
            'vessel_type' => 'nullable|string|max:50',
            'capacity_teu' => 'nullable|integer',
        ]);

        $payload = $this->mapVesselPayload($data);
        if (Schema::hasColumn('vessels', 'account_id')) {
            $payload['account_id'] = $this->currentAccountId();
        }
        if (Schema::hasColumn('vessels', 'status') && !array_key_exists('status', $payload)) {
            $payload['status'] = 'active';
        }

        $vessel = Vessel::create($payload);
        $this->auditInfo('vessel.created', $vessel, $payload);

        return response()->json(['data' => $vessel], 201);
    }

    public function show(string $id): JsonResponse
    {
        $vessel = $this->findVesselForCurrentAccount($id, ['schedules', 'containers']);
        $this->authorize('view', $vessel);

        return response()->json(['data' => $vessel]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $vessel = $this->findVesselForCurrentAccount($id);
        $this->authorize('update', $vessel);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'imo_number' => 'nullable|string|max:50',
            'flag' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:50',
            'vessel_type' => 'nullable|string|max:50',
            'capacity_teu' => 'nullable|integer',
            'status' => 'sometimes|in:active,inactive,maintenance',
        ]);

        $payload = $this->mapVesselPayload($data);
        $vessel->update($payload);
        $this->auditInfo('vessel.updated', $vessel, $payload);

        return response()->json(['data' => $vessel->fresh()]);
    }

    public function destroy(string $id): JsonResponse
    {
        $vessel = $this->findVesselForCurrentAccount($id);
        $this->authorize('delete', $vessel);

        $vessel->delete();
        $this->auditInfo('vessel.deleted', $vessel);

        return response()->json(null, 204);
    }

    private function scopedVesselQuery(): Builder
    {
        $query = Vessel::query();

        if (Schema::hasColumn('vessels', 'account_id')) {
            $query->where('account_id', $this->currentAccountId());
        }

        return $query;
    }

    private function findVesselForCurrentAccount(string $id, array $with = []): Vessel
    {
        return $this->scopedVesselQuery()
            ->with($with)
            ->where('id', $id)
            ->firstOrFail();
    }

    private function mapVesselPayload(array $data): array
    {
        $payload = [];
        $nameColumn = $this->vesselNameColumn();
        $typeColumn = $this->vesselTypeColumn();

        if (array_key_exists('name', $data)) {
            $payload[$nameColumn] = $data['name'];
        }

        if (array_key_exists('imo_number', $data)) {
            $payload['imo_number'] = $data['imo_number'];
        }

        if (array_key_exists('flag', $data)) {
            $payload['flag'] = $data['flag'];
        }

        if (array_key_exists('capacity_teu', $data)) {
            $payload['capacity_teu'] = $data['capacity_teu'];
        }

        if (array_key_exists('status', $data)) {
            $payload['status'] = $data['status'];
        }

        $typeValue = $data['vessel_type'] ?? $data['type'] ?? null;
        if ($typeValue !== null) {
            $payload[$typeColumn] = $typeValue;
        }

        return $payload;
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

    private function auditInfo(string $action, Vessel $vessel, ?array $newValues = null): void
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
            Vessel::class,
            (string) $vessel->id,
            null,
            $newValues
        );
    }
}
