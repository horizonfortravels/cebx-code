<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ContentDeclaration;
use App\Models\Shipment;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ContentDeclarationController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', self::class);

        $query = $this->scopedDeclarationQuery()
            ->with('shipment:' . implode(',', $this->shipmentSummaryColumns()));

        if ($request->filled('shipment_id')) {
            $query->where('shipment_id', $request->shipment_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'data' => $query->orderByDesc('created_at')->paginate(20),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', self::class);

        $data = $request->validate([
            'shipment_id' => 'required|uuid|exists:shipments,id',
            'declaration_type' => 'nullable|in:export,import,transit',
            'items' => 'nullable|array',
            'items.*.description' => 'required|string|max:300',
            'items.*.hs_code' => 'nullable|string|max:20',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.weight_kg' => 'nullable|numeric|min:0.01',
            'items.*.dangerous' => 'nullable|boolean',
            'total_value' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'purpose' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'contains_dangerous_goods' => 'nullable|boolean',
        ]);

        $shipment = $this->findShipmentForCurrentAccount($data['shipment_id']);

        $containsDangerousGoods = $this->extractDangerousGoodsFlag($data);
        $declaration = ContentDeclaration::create([
            'account_id' => $this->currentAccountId(),
            'shipment_id' => $shipment->id,
            'contains_dangerous_goods' => $containsDangerousGoods,
            'status' => $containsDangerousGoods
                ? ContentDeclaration::STATUS_HOLD_DG
                : ContentDeclaration::STATUS_PENDING,
            'hold_reason' => $containsDangerousGoods
                ? 'Dangerous goods require review before issuance.'
                : null,
            'waiver_accepted' => false,
            'declared_by' => (string) $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'locale' => (string) ($request->user()->locale ?? 'en'),
            'declared_at' => now(),
        ]);

        $this->audit->info(
            $this->currentAccountId(),
            (string) $request->user()->id,
            'declaration.created',
            AuditLog::CATEGORY_ACCOUNT,
            ContentDeclaration::class,
            (string) $declaration->id
        );

        return response()->json([
            'data' => $declaration,
            'message' => 'تم إنشاء البيان الجمركي',
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $declaration = $this->findDeclarationForCurrentAccount($id, [
            'shipment:' . implode(',', $this->shipmentSummaryColumns([
                'origin_country',
                'destination_country',
            ])),
        ]);
        $this->authorize('view', [self::class, $declaration]);

        return response()->json(['data' => $declaration]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $declaration = $this->findDeclarationForCurrentAccount($id);
        $this->authorize('update', [self::class, $declaration]);

        $data = $request->validate([
            'items' => 'nullable|array',
            'items.*.dangerous' => 'nullable|boolean',
            'contains_dangerous_goods' => 'nullable|boolean',
            'waiver_accepted' => 'nullable|boolean',
            'locale' => 'nullable|string|max:5',
        ]);

        $updates = [];

        if (array_key_exists('contains_dangerous_goods', $data) || array_key_exists('items', $data)) {
            $updates['contains_dangerous_goods'] = $this->extractDangerousGoodsFlag($data);
        }

        if (array_key_exists('waiver_accepted', $data)) {
            $updates['waiver_accepted'] = (bool) $data['waiver_accepted'];
            $updates['waiver_accepted_at'] = $data['waiver_accepted'] ? now() : null;
        }

        if (array_key_exists('locale', $data)) {
            $updates['locale'] = $data['locale'];
        }

        if ($updates !== []) {
            $updates['status'] = $this->resolveStatusAfterUpdate(
                $updates['contains_dangerous_goods'] ?? (bool) $declaration->contains_dangerous_goods,
                $updates['waiver_accepted'] ?? (bool) $declaration->waiver_accepted
            );
            $updates['hold_reason'] = $updates['status'] === ContentDeclaration::STATUS_HOLD_DG
                ? 'Dangerous goods require review before issuance.'
                : null;

            $declaration->update($updates);
        }

        return response()->json(['data' => $declaration->fresh()]);
    }

    public function submit(Request $request, string $id): JsonResponse
    {
        $declaration = $this->findDeclarationForCurrentAccount($id);
        $this->authorize('submit', [self::class, $declaration]);

        if ($declaration->contains_dangerous_goods) {
            return response()->json([
                'message' => 'البيان يتطلب مراجعة قبل التقديم بسبب المواد الخطرة',
            ], 422);
        }

        if (!$declaration->waiver_accepted) {
            return response()->json([
                'message' => 'يجب قبول الإقرار قبل التقديم',
            ], 422);
        }

        $declaration->update([
            'status' => ContentDeclaration::STATUS_COMPLETED,
            'declared_at' => now(),
        ]);

        $this->audit->info(
            $this->currentAccountId(),
            (string) $request->user()->id,
            'declaration.submitted',
            AuditLog::CATEGORY_ACCOUNT,
            ContentDeclaration::class,
            (string) $declaration->id
        );

        return response()->json([
            'data' => $declaration->fresh(),
            'message' => 'تم تقديم البيان الجمركي',
        ]);
    }

    public function review(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string|max:1000',
        ]);

        $declaration = $this->findDeclarationForCurrentAccount($id);
        $this->authorize('review', [self::class, $declaration]);

        $declaration->update([
            'status' => $data['action'] === 'approve'
                ? ContentDeclaration::STATUS_COMPLETED
                : ContentDeclaration::STATUS_REQUIRES_ACTION,
            'hold_reason' => $data['action'] === 'reject'
                ? ($data['notes'] ?? 'Rejected during review')
                : null,
        ]);

        $this->audit->info(
            $this->currentAccountId(),
            (string) $request->user()->id,
            'declaration.' . $data['action'],
            AuditLog::CATEGORY_ACCOUNT,
            ContentDeclaration::class,
            (string) $declaration->id,
            null,
            ['notes' => $data['notes'] ?? null]
        );

        return response()->json(['data' => $declaration->fresh()]);
    }

    public function destroy(string $id): JsonResponse
    {
        $declaration = $this->findDeclarationForCurrentAccount($id);
        $this->authorize('delete', [self::class, $declaration]);

        if (!in_array((string) $declaration->status, [
            ContentDeclaration::STATUS_PENDING,
            ContentDeclaration::STATUS_REQUIRES_ACTION,
        ], true)) {
            return response()->json(['message' => 'لا يمكن حذف بيان تم تقديمه'], 422);
        }

        $declaration->delete();

        return response()->json(['message' => 'تم حذف البيان']);
    }

    private function scopedDeclarationQuery(): Builder
    {
        return ContentDeclaration::query()->where('account_id', $this->currentAccountId());
    }

    private function findDeclarationForCurrentAccount(string $id, array $with = []): ContentDeclaration
    {
        return $this->scopedDeclarationQuery()
            ->with($with)
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

    private function extractDangerousGoodsFlag(array $data): bool
    {
        if (array_key_exists('contains_dangerous_goods', $data)) {
            return (bool) $data['contains_dangerous_goods'];
        }

        foreach ($data['items'] ?? [] as $item) {
            if (!empty($item['dangerous'])) {
                return true;
            }
        }

        return false;
    }

    private function resolveStatusAfterUpdate(bool $containsDangerousGoods, bool $waiverAccepted): string
    {
        if ($containsDangerousGoods) {
            return ContentDeclaration::STATUS_HOLD_DG;
        }

        return $waiverAccepted
            ? ContentDeclaration::STATUS_COMPLETED
            : ContentDeclaration::STATUS_PENDING;
    }

    private function currentAccountId(): string
    {
        return trim((string) app('current_account_id'));
    }

    /**
     * @param array<int, string> $extraColumns
     * @return array<int, string>
     */
    private function shipmentSummaryColumns(array $extraColumns = []): array
    {
        $columns = ['id', 'status'];

        foreach (['tracking_number', 'reference_number', ...$extraColumns] as $column) {
            if (Schema::hasColumn('shipments', $column)) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }
}
