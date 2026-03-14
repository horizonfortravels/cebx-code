<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CustomsBroker;
use App\Models\CustomsDeclaration;
use App\Models\CustomsDocument;
use App\Models\HsCode;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CustomsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomsDeclaration::class);

        $query = $this->scopedDeclarationQuery()
            ->with(['shipment:id,tracking_number,status', 'broker:id,name']);

        if ($request->customs_status) {
            $query->where('customs_status', $request->customs_status);
        }

        if ($request->declaration_type) {
            $query->where('declaration_type', $request->declaration_type);
        }

        if ($request->shipment_id) {
            $query->where('shipment_id', $request->shipment_id);
        }

        if ($request->broker_id) {
            $query->where('broker_id', $request->broker_id);
        }

        if ($request->search) {
            $query->where('declaration_number', 'like', '%' . $request->search . '%');
        }

        return response()->json([
            'data' => $query->orderByDesc('created_at')->paginate($request->per_page ?? 25),
        ]);
    }

    public function declarations(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CustomsDeclaration::class);

        $validated = $request->validate([
            'shipment_id' => 'required|uuid|exists:shipments,id',
            'broker_id' => 'nullable|uuid|exists:customs_brokers,id',
            'branch_id' => 'nullable|uuid|exists:branches,id',
            'declaration_type' => 'required|in:export,import,transit,re_export',
            'customs_office' => 'nullable|string|max:200',
            'origin_country' => 'required|string|size:2',
            'destination_country' => 'required|string|size:2',
            'incoterm_code' => 'nullable|string|max:3',
            'declared_value' => 'required|numeric|min:0',
            'declared_currency' => 'nullable|string|size:3',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.description' => 'required_with:items|string|max:500',
            'items.*.hs_code' => 'nullable|string|max:12',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.weight' => 'nullable|numeric',
            'items.*.unit_value' => 'required_with:items|numeric|min:0',
        ]);

        $shipment = $this->findShipmentForCurrentAccount($validated['shipment_id']);

        if (!empty($validated['broker_id'])) {
            $validated['broker_id'] = $this->findBrokerForCurrentAccount($validated['broker_id'])->id;
        }

        if (!empty($validated['branch_id'])) {
            $validated['branch_id'] = $this->findBranchForCurrentAccount($validated['branch_id'])->id;
        }

        $validated['account_id'] = $this->currentAccountId();
        $validated['shipment_id'] = $shipment->id;
        $validated['customs_status'] = 'draft';

        $declaration = CustomsDeclaration::create($validated);

        foreach ($request->input('items', []) as $item) {
            $item['shipment_id'] = $shipment->id;
            $item['declaration_id'] = $declaration->id;
            $item['total_value'] = ($item['quantity'] ?? 1) * ($item['unit_value'] ?? 0);
            ShipmentItem::create($item);
        }

        $this->calculateDuties($declaration->fresh());

        return response()->json([
            'data' => $declaration->fresh()->load(['items', 'documents']),
            'message' => 'طھظ… ط¥ظ†ط´ط§ط، ط§ظ„ط¨ظٹط§ظ† ط§ظ„ط¬ظ…ط±ظƒظٹ',
        ], 201);
    }

    public function createDeclaration(Request $request): JsonResponse
    {
        return $this->store($request);
    }

    public function show(string $id): JsonResponse
    {
        $declaration = $this->findDeclarationForCurrentAccount($id, [
            'shipment',
            'broker',
            'documents',
            'items',
            'branch',
            'inspector',
        ]);
        $this->authorize('view', $declaration);

        return response()->json(['data' => $declaration]);
    }

    public function showDeclaration(string $id): JsonResponse
    {
        return $this->show($id);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $declaration = $this->findDeclarationForCurrentAccount($id);
        $this->authorize('update', $declaration);
        $statusColumn = $this->declarationStatusColumn();

        if (!in_array((string) ($declaration->{$statusColumn} ?? ''), ['draft', 'documents_pending'], true)) {
            return response()->json(['message' => 'ظ„ط§ ظٹظ…ظƒظ† ط§ظ„طھط¹ط¯ظٹظ„ ظپظٹ ظ‡ط°ظ‡ ط§ظ„ظ…ط±ط­ظ„ط©'], 422);
        }

        $payload = $request->only([
            'broker_id',
            'branch_id',
            'customs_office',
            'incoterm_code',
            'declared_value',
            'declared_currency',
            'notes',
        ]);

        if (!empty($payload['broker_id'])) {
            $payload['broker_id'] = $this->findBrokerForCurrentAccount((string) $payload['broker_id'])->id;
        }

        if (!empty($payload['branch_id'])) {
            $payload['branch_id'] = $this->findBranchForCurrentAccount((string) $payload['branch_id'])->id;
        }

        $declaration->update($payload);
        $this->calculateDuties($declaration->fresh());

        return response()->json([
            'data' => $declaration->fresh(),
            'message' => 'طھظ… طھط­ط¯ظٹط« ط§ظ„ط¨ظٹط§ظ†',
        ]);
    }

    public function updateDeclaration(Request $request, string $id): JsonResponse
    {
        return $this->update($request, $id);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate(['status' => 'required|string', 'notes' => 'nullable|string']);

        $declaration = $this->findDeclarationForCurrentAccount($id);
        $this->authorize('updateStatus', $declaration);
        $statusColumn = $this->declarationStatusColumn();

        if (!$declaration->canTransitionTo((string) $request->status)) {
            return response()->json([
                'message' => 'ظ„ط§ ظٹظ…ظƒظ† ط§ظ„ط§ظ†طھظ‚ط§ظ„ ظ…ظ† ' . ($declaration->{$statusColumn} ?? '') . ' ط¥ظ„ظ‰ ' . $request->status,
            ], 422);
        }

        $oldStatus = (string) ($declaration->{$statusColumn} ?? '');
        $updates = [$statusColumn => $request->status];

        match ($request->status) {
            'submitted' => $updates['submitted_at'] = now(),
            'cleared' => $updates['cleared_at'] = now(),
            'duty_paid' => $updates = array_merge($updates, [
                'duty_paid_at' => now(),
                'duty_payment_ref' => $request->payment_ref,
            ]),
            'inspecting' => $updates = array_merge($updates, [
                'inspection_flag' => true,
                'inspection_date' => now(),
                'inspector_user_id' => $request->user()->id,
            ]),
            default => null,
        };

        if ($request->filled('inspection_result')) {
            $updates['inspection_result'] = $request->inspection_result;
        }

        if ($request->filled('inspection_notes')) {
            $updates['inspection_notes'] = $request->inspection_notes;
        }

        $declaration->update($updates);

        AuditLog::create([
            'account_id' => $declaration->account_id,
            'user_id' => $request->user()->id,
            'action' => 'customs_status_change',
            'entity_type' => 'customs_declaration',
            'entity_id' => $declaration->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $request->status],
            'description' => 'طھط؛ظٹظٹط± ط­ط§ظ„ط© ط§ظ„ط¬ظ…ط§ط±ظƒ: ' . $oldStatus . ' â†’ ' . $request->status,
        ]);

        return response()->json([
            'data' => $declaration,
            'message' => 'طھظ… طھط­ط¯ظٹط« ط­ط§ظ„ط© ط§ظ„ط¨ظٹط§ظ† ط§ظ„ط¬ظ…ط±ظƒظٹ',
        ]);
    }

    public function inspect(Request $request, string $id): JsonResponse
    {
        $request->merge(['status' => 'inspecting']);

        return $this->updateStatus($request, $id);
    }

    public function issueClearance(Request $request, string $id): JsonResponse
    {
        $request->merge(['status' => 'cleared']);

        return $this->updateStatus($request, $id);
    }

    public function uploadDocument(Request $request, string $id): JsonResponse
    {
        $declaration = $this->resolveDeclarationForDocumentUpload($id);
        $this->authorize('uploadDocument', $declaration);

        $request->validate([
            'document_type' => 'required|string',
            'document_name' => 'required|string|max:200',
            'document_number' => 'nullable|string|max:100',
            'file' => 'required|file|max:10240',
            'is_required' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        $path = $file->store('customs/' . $declaration->id, 'public');

        $document = CustomsDocument::create([
            'declaration_id' => $declaration->id,
            'shipment_id' => $declaration->shipment_id,
            'document_type' => $request->document_type,
            'document_name' => $request->document_name,
            'document_number' => $request->document_number,
            'file_path' => $path,
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
            'is_required' => $request->boolean('is_required', true),
        ]);

        return response()->json([
            'data' => $document,
            'message' => 'طھظ… ط±ظپط¹ ط§ظ„ظ…ط³طھظ†ط¯',
        ], 201);
    }

    public function documents(string $shipmentId): JsonResponse
    {
        $shipment = $this->findShipmentForCurrentAccount($shipmentId);
        $this->authorize('viewAny', CustomsDeclaration::class);

        return response()->json([
            'data' => CustomsDocument::query()
                ->where('shipment_id', $shipment->id)
                ->with('declaration')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function verifyDocument(Request $request, string $id): JsonResponse
    {
        $document = CustomsDocument::query()
            ->where('id', $id)
            ->whereHas('declaration', function ($builder): void {
                $builder->where('account_id', $this->currentAccountId());
            })
            ->with('declaration')
            ->firstOrFail();
        $this->authorize('verifyDocument', $document);

        $action = (string) $request->input('action', 'approve');

        if ($action === 'approve') {
            $document->update([
                'is_verified' => true,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
                'rejection_reason' => null,
            ]);
        } else {
            $document->update([
                'is_verified' => false,
                'rejection_reason' => $request->input('rejection_reason'),
            ]);
        }

        return response()->json([
            'data' => $document,
            'message' => $action === 'approve' ? 'طھظ… ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ط§ظ„ظ…ط³طھظ†ط¯' : 'طھظ… ط±ظپط¶ ط§ظ„ظ…ط³طھظ†ط¯',
        ]);
    }

    public function duties(string $shipmentId): JsonResponse
    {
        $shipment = $this->findShipmentForCurrentAccount($shipmentId);
        $this->authorize('viewAny', CustomsDeclaration::class);

        $declaration = $this->scopedDeclarationQuery()
            ->where('shipment_id', $shipment->id)
            ->latest()
            ->first();

        return response()->json([
            'data' => [
                'declaration_id' => $declaration?->id,
                'duty_amount' => $declaration?->duty_amount ?? 0,
                'vat_amount' => $declaration?->vat_amount ?? 0,
                'excise_amount' => $declaration?->excise_amount ?? 0,
                'broker_fee' => $declaration?->broker_fee ?? 0,
                'total_customs_charges' => $declaration?->total_customs_charges ?? 0,
            ],
        ]);
    }

    public function brokersIndex(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomsBroker::class);

        $query = CustomsBroker::query()->where('account_id', $this->currentAccountId());

        if ($request->country) {
            $query->where('country', $request->country);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->where(function ($builder) use ($request): void {
                $builder
                    ->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('license_number', 'like', '%' . $request->search . '%');
            });
        }

        return response()->json([
            'data' => $query->orderBy('rating', 'desc')->paginate($request->per_page ?? 25),
        ]);
    }

    public function brokers(Request $request): JsonResponse
    {
        return $this->brokersIndex($request);
    }

    public function brokersStore(Request $request): JsonResponse
    {
        $this->authorize('create', CustomsBroker::class);

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'license_number' => 'required|string|max:100',
            'country' => 'required|string|size:2',
            'city' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email',
            'company_name' => 'nullable|string|max:200',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'fixed_fee' => 'nullable|numeric|min:0',
            'specializations' => 'nullable|array',
        ]);

        $validated['account_id'] = $this->currentAccountId();

        return response()->json([
            'data' => CustomsBroker::create($validated),
            'message' => 'طھظ… ط¥ط¶ط§ظپط© ط§ظ„ظ…ط®ظ„طµ ط§ظ„ط¬ظ…ط±ظƒظٹ',
        ], 201);
    }

    public function createBroker(Request $request): JsonResponse
    {
        return $this->brokersStore($request);
    }

    public function brokersShow(string $id): JsonResponse
    {
        $broker = $this->findBrokerForCurrentAccount($id, ['declarations']);
        $this->authorize('view', $broker);

        return response()->json(['data' => $broker]);
    }

    public function brokersUpdate(Request $request, string $id): JsonResponse
    {
        $broker = $this->findBrokerForCurrentAccount($id);
        $this->authorize('update', $broker);

        $broker->update($request->only([
            'name',
            'license_number',
            'country',
            'city',
            'phone',
            'email',
            'company_name',
            'commission_rate',
            'fixed_fee',
            'status',
            'specializations',
        ]));

        return response()->json([
            'data' => $broker,
            'message' => 'طھظ… طھط­ط¯ظٹط« ط§ظ„ظ…ط®ظ„طµ',
        ]);
    }

    public function updateBroker(Request $request, string $id): JsonResponse
    {
        return $this->brokersUpdate($request, $id);
    }

    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomsDeclaration::class);

        $accountId = $this->currentAccountId();

        return response()->json(['data' => [
            'total_declarations' => $this->scopedDeclarationQuery()->count(),
            'by_status' => $this->declarationStatusAggregate(),
            'pending_clearance' => $this->pendingDeclarationCount(),
            'cleared_this_month' => $this->clearedDeclarationCountForCurrentMonth(),
            'total_duties' => $this->clearedDeclarationDutyTotal(),
            'active_brokers' => CustomsBroker::query()->where('account_id', $accountId)->where('status', 'active')->count(),
        ]]);
    }

    /**
     * @param array<int, string> $with
     */
    private function findDeclarationForCurrentAccount(string $id, array $with = []): CustomsDeclaration
    {
        $query = $this->scopedDeclarationQuery();

        if ($with !== []) {
            $query->with($with);
        }

        return $query->where('id', $id)->firstOrFail();
    }

    /**
     * @param array<int, string> $with
     */
    private function findBrokerForCurrentAccount(string $id, array $with = []): CustomsBroker
    {
        $query = CustomsBroker::query()->where('account_id', $this->currentAccountId());

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

    private function calculateDuties(CustomsDeclaration $declaration): void
    {
        $items = ShipmentItem::query()->where('declaration_id', $declaration->id)->get();
        $totalDuty = 0;
        $totalVat = 0;
        $totalExcise = 0;

        foreach ($items as $item) {
            if (!$item->hs_code) {
                continue;
            }

            $hsCode = HsCode::query()
                ->where('code', $item->hs_code)
                ->where(function ($builder) use ($declaration): void {
                    $builder
                        ->where('country', $declaration->destination_country)
                        ->orWhere('country', '*');
                })
                ->first();

            if (!$hsCode) {
                continue;
            }

            $duties = $hsCode->calculateDuty($item->total_value);
            $totalDuty += $duties['duty'];
            $totalVat += $duties['vat'];
            $totalExcise += $duties['excise'];
        }

        $brokerFee = 0;
        if ($declaration->broker_id) {
            $broker = CustomsBroker::query()->find($declaration->broker_id);
            if ($broker) {
                $brokerFee = $broker->fixed_fee + ($declaration->declared_value * $broker->commission_rate / 100);
            }
        }

        $updates = [];

        foreach ([
            'duty_amount' => $totalDuty,
            'vat_amount' => $totalVat,
            'excise_amount' => $totalExcise,
            'broker_fee' => round($brokerFee, 2),
            'total_customs_charges' => $totalDuty + $totalVat + $totalExcise + $brokerFee,
        ] as $column => $value) {
            if (Schema::hasColumn('customs_declarations', $column)) {
                $updates[$column] = $value;
            }
        }

        if ($updates !== []) {
            $declaration->update($updates);
        }
    }

    private function resolveDeclarationForDocumentUpload(string $identifier): CustomsDeclaration
    {
        $declaration = $this->scopedDeclarationQuery()
            ->where('id', $identifier)
            ->first();

        if ($declaration) {
            return $declaration;
        }

        $shipment = $this->findShipmentForCurrentAccount($identifier);

        return $this->scopedDeclarationQuery()
            ->where('shipment_id', $shipment->id)
            ->latest()
            ->firstOrFail();
    }

    private function scopedDeclarationQuery(): Builder
    {
        if (Schema::hasColumn('customs_declarations', 'account_id')) {
            return CustomsDeclaration::query()->where('account_id', $this->currentAccountId());
        }

        return CustomsDeclaration::query()->whereHas('shipment', function (Builder $builder): void {
            $builder->where('account_id', $this->currentAccountId());
        });
    }

    private function declarationStatusColumn(): string
    {
        return Schema::hasColumn('customs_declarations', 'customs_status') ? 'customs_status' : 'status';
    }

    private function declarationAmountColumn(): string
    {
        return Schema::hasColumn('customs_declarations', 'total_customs_charges') ? 'total_customs_charges' : 'duty_amount';
    }

    private function declarationStatusAggregate(): mixed
    {
        $statusColumn = $this->declarationStatusColumn();

        return $this->scopedDeclarationQuery()
            ->selectRaw($statusColumn . ', count(*) as count')
            ->groupBy($statusColumn)
            ->pluck('count', $statusColumn);
    }

    private function pendingDeclarationCount(): int
    {
        $statusColumn = $this->declarationStatusColumn();

        return $this->scopedDeclarationQuery()
            ->whereNotIn($statusColumn, ['cleared', 'cancelled', 'rejected'])
            ->count();
    }

    private function clearedDeclarationCountForCurrentMonth(): int
    {
        $statusColumn = $this->declarationStatusColumn();

        $query = $this->scopedDeclarationQuery()->where($statusColumn, 'cleared');

        if (Schema::hasColumn('customs_declarations', 'cleared_at')) {
            $query->whereMonth('cleared_at', now()->month);
        } else {
            $query->whereMonth('updated_at', now()->month);
        }

        return $query->count();
    }

    private function clearedDeclarationDutyTotal(): int|float
    {
        $statusColumn = $this->declarationStatusColumn();
        $amountColumn = $this->declarationAmountColumn();

        return $this->scopedDeclarationQuery()
            ->where($statusColumn, 'cleared')
            ->sum($amountColumn);
    }

    private function currentAccountId(): string
    {
        return trim((string) app('current_account_id'));
    }
}
