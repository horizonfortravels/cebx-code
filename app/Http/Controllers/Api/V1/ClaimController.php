<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\ClaimDocument;
use App\Models\ClaimHistory;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ClaimController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Claim::class);

        $query = Claim::query()
            ->where('account_id', $this->currentAccountId())
            ->with([
                'shipment' => fn ($builder) => $builder->select($this->shipmentColumnsForClaims()),
                'filer:id,name',
                'assignee:id,name',
            ]);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->claim_type) {
            $query->where('claim_type', $request->claim_type);
        }

        if ($request->shipment_id) {
            $query->where('shipment_id', $request->shipment_id);
        }

        if ($request->assigned_to) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->search) {
            $query->where(function ($builder) use ($request): void {
                $builder
                    ->where('claim_number', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->overdue) {
            $query
                ->where('sla_deadline', '<', now())
                ->whereNotIn('status', ['settled', 'closed', 'rejected']);
        }

        $sort = (string) ($request->sort ?? '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        $query->orderBy($column, $direction);

        return response()->json([
            'data' => $query->paginate($request->per_page ?? 25),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Claim::class);

        $validated = $request->validate([
            'shipment_id' => 'required|uuid|exists:shipments,id',
            'claim_type' => 'required|in:damage,loss,shortage,delay,wrong_delivery,theft,water_damage,temperature_deviation,other',
            'description' => 'required|string|min:20|max:5000',
            'claimed_amount' => 'required|numeric|min:0.01',
            'claimed_currency' => 'nullable|string|size:3',
            'incident_date' => 'required|date|before_or_equal:today',
            'incident_location' => 'nullable|string|max:300',
        ]);

        $shipment = $this->findShipmentForCurrentAccount($validated['shipment_id']);

        $validated['account_id'] = $this->currentAccountId();
        $validated['shipment_id'] = $shipment->id;
        $validated['claim_number'] = Claim::generateNumber();
        $validated['status'] = 'draft';
        $validated['filed_by'] = $request->user()->id;
        $validated['sla_deadline'] = now()->addWeekdays(14)->toDateString();

        $claim = Claim::create($validated);

        ClaimHistory::create([
            'claim_id' => $claim->id,
            'from_status' => 'new',
            'to_status' => 'draft',
            'changed_by' => $request->user()->id,
            'notes' => 'تم إنشاء المطالبة',
        ]);

        return response()->json([
            'data' => $claim->load(['shipment', 'filer']),
            'message' => 'تم إنشاء المطالبة بنجاح',
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $claim = $this->findClaimForCurrentAccount($id, [
            'shipment' => fn ($builder) => $builder->select($this->shipmentColumnsForClaims(true)),
            'filer:id,name,email',
            'assignee:id,name,email',
            'approver:id,name',
            'documents',
            'history.user:id,name',
        ]);
        $this->authorize('view', $claim);

        return response()->json(['data' => $claim]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $claim = $this->findClaimForCurrentAccount($id);
        $this->authorize('update', $claim);

        if (!in_array($claim->status, ['draft', 'submitted'], true)) {
            return response()->json(['message' => 'لا يمكن تعديل المطالبة في هذه المرحلة'], 422);
        }

        $claim->update($request->only([
            'description',
            'claimed_amount',
            'claimed_currency',
            'incident_date',
            'incident_location',
        ]));

        return response()->json([
            'data' => $claim,
            'message' => 'تم تحديث المطالبة',
        ]);
    }

    public function submit(Request $request, string $id): JsonResponse
    {
        return $this->transition($request, $id, 'submitted', 'submit', 'تم تقديم المطالبة');
    }

    public function assign(Request $request, string $id): JsonResponse
    {
        $request->validate(['assigned_to' => 'required|uuid|exists:users,id']);

        $claim = $this->findClaimForCurrentAccount($id);
        $this->authorize('assign', $claim);

        $fromStatus = $claim->status;
        $claim->update([
            'assigned_to' => $request->assigned_to,
            'status' => 'under_review',
        ]);

        ClaimHistory::create([
            'claim_id' => $claim->id,
            'from_status' => $fromStatus,
            'to_status' => 'under_review',
            'changed_by' => $request->user()->id,
            'notes' => 'تم تعيين المطالبة للمعالجة',
        ]);

        return response()->json([
            'data' => $claim,
            'message' => 'تم تعيين المطالبة',
        ]);
    }

    public function investigate(Request $request, string $id): JsonResponse
    {
        return $this->transition($request, $id, 'investigation', 'investigate', 'بدأ التحقيق');
    }

    public function assess(Request $request, string $id): JsonResponse
    {
        return $this->transition($request, $id, 'assessment', 'assess', 'جارٍ التقييم');
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'approved_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $claim = $this->findClaimForCurrentAccount($id);
        $this->authorize('approve', $claim);

        $fromStatus = $claim->status;
        $status = $request->approved_amount < $claim->claimed_amount ? 'partially_approved' : 'approved';

        $claim->update([
            'status' => $status,
            'approved_amount' => $request->approved_amount,
            'approved_by' => $request->user()->id,
            'resolved_at' => now(),
            'resolution_notes' => $request->notes,
        ]);

        ClaimHistory::create([
            'claim_id' => $claim->id,
            'from_status' => $fromStatus,
            'to_status' => $status,
            'changed_by' => $request->user()->id,
            'notes' => 'تمت الموافقة - المبلغ: ' . $request->approved_amount,
        ]);

        return response()->json([
            'data' => $claim,
            'message' => 'تمت الموافقة على المطالبة',
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|min:10']);

        $claim = $this->findClaimForCurrentAccount($id);
        $this->authorize('reject', $claim);

        $fromStatus = $claim->status;
        $claim->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
            'resolved_at' => now(),
        ]);

        ClaimHistory::create([
            'claim_id' => $claim->id,
            'from_status' => $fromStatus,
            'to_status' => 'rejected',
            'changed_by' => $request->user()->id,
            'notes' => 'تم الرفض: ' . $request->reason,
        ]);

        return response()->json([
            'data' => $claim,
            'message' => 'تم رفض المطالبة',
        ]);
    }

    public function settle(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'settled_amount' => 'required|numeric|min:0',
            'settlement_ref' => 'nullable|string|max:100',
            'settlement_currency' => 'nullable|string|size:3',
        ]);

        $claim = $this->findClaimForCurrentAccount($id);
        $this->authorize('settle', $claim);

        $fromStatus = $claim->status;
        $claim->update([
            'status' => 'settled',
            'settled_amount' => $request->settled_amount,
            'settlement_ref' => $request->settlement_ref,
            'settlement_currency' => $request->settlement_currency ?? $claim->claimed_currency,
            'settled_at' => now(),
        ]);

        ClaimHistory::create([
            'claim_id' => $claim->id,
            'from_status' => $fromStatus,
            'to_status' => 'settled',
            'changed_by' => $request->user()->id,
            'notes' => 'تمت التسوية - المبلغ: ' . $request->settled_amount,
        ]);

        return response()->json([
            'data' => $claim,
            'message' => 'تمت تسوية المطالبة',
        ]);
    }

    public function close(Request $request, string $id): JsonResponse
    {
        return $this->transition($request, $id, 'closed', 'close', 'تم إغلاق المطالبة');
    }

    public function resolve(Request $request, string $id): JsonResponse
    {
        return $this->close($request, $id);
    }

    public function appeal(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|min:10']);

        $claim = $this->findClaimForCurrentAccount($id);
        $this->authorize('appeal', $claim);

        if (!in_array($claim->status, ['rejected', 'partially_approved'], true)) {
            return response()->json(['message' => 'لا يمكن الاعتراض في هذه المرحلة'], 422);
        }

        $fromStatus = $claim->status;
        $claim->update(['status' => 'appealed']);

        ClaimHistory::create([
            'claim_id' => $claim->id,
            'from_status' => $fromStatus,
            'to_status' => 'appealed',
            'changed_by' => $request->user()->id,
            'notes' => 'اعتراض: ' . $request->reason,
        ]);

        return response()->json([
            'data' => $claim,
            'message' => 'تم تقديم الاعتراض',
        ]);
    }

    public function uploadDocument(Request $request, string $id): JsonResponse
    {
        $claim = $this->findClaimForCurrentAccount($id);
        $this->authorize('uploadDocument', $claim);

        $request->validate([
            'document_type' => 'required|in:photo,video,invoice,receipt,report,correspondence,other',
            'title' => 'required|string|max:200',
            'file' => 'required|file|max:20480',
            'notes' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $path = $file->store('claims/' . $claim->id, 'public');

        $document = ClaimDocument::create([
            'claim_id' => $claim->id,
            'document_type' => $request->document_type,
            'title' => $request->title,
            'file_path' => $path,
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'data' => $document,
            'message' => 'تم رفع المستند',
        ], 201);
    }

    public function documents(string $id): JsonResponse
    {
        $claim = $this->findClaimForCurrentAccount($id);
        $this->authorize('view', $claim);

        return response()->json([
            'data' => ClaimDocument::query()
                ->where('claim_id', $claim->id)
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function deleteDocument(string $id, string $docId): JsonResponse
    {
        $claim = $this->findClaimForCurrentAccount($id);
        $this->authorize('deleteDocument', $claim);

        ClaimDocument::query()
            ->where('claim_id', $claim->id)
            ->where('id', $docId)
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'تم حذف المستند']);
    }

    public function history(string $id): JsonResponse
    {
        $claim = $this->findClaimForCurrentAccount($id);
        $this->authorize('view', $claim);

        return response()->json([
            'data' => ClaimHistory::query()
                ->where('claim_id', $claim->id)
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Claim::class);

        $accountId = $this->currentAccountId();
        $query = Claim::query()->where('account_id', $accountId);

        return response()->json(['data' => [
            'total' => (clone $query)->count(),
            'by_status' => (clone $query)->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status'),
            'by_type' => (clone $query)->selectRaw('claim_type, count(*) as count')->groupBy('claim_type')->pluck('count', 'claim_type'),
            'open' => (clone $query)->whereNotIn('status', ['settled', 'closed', 'rejected'])->count(),
            'overdue' => (clone $query)->where('sla_deadline', '<', now())->whereNotIn('status', ['settled', 'closed', 'rejected'])->count(),
            'total_claimed' => (clone $query)->sum('claimed_amount'),
            'total_approved' => (clone $query)->whereNotNull('approved_amount')->sum('approved_amount'),
            'total_settled' => (clone $query)->where('status', 'settled')->sum('settled_amount'),
            'avg_resolution_days' => round((clone $query)->whereNotNull('resolved_at')->selectRaw('AVG(DATEDIFF(resolved_at, created_at)) as avg')->value('avg') ?? 0),
            'this_month' => (clone $query)->whereMonth('created_at', now()->month)->count(),
        ]]);
    }

    private function transition(
        Request $request,
        string $id,
        string $toStatus,
        string $ability,
        string $message
    ): JsonResponse {
        $claim = $this->findClaimForCurrentAccount($id);
        $this->authorize($ability, $claim);

        $fromStatus = $claim->status;
        $claim->update(['status' => $toStatus]);

        ClaimHistory::create([
            'claim_id' => $claim->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $request->user()->id,
            'notes' => $request->input('notes') ?? $message,
        ]);

        return response()->json([
            'data' => $claim,
            'message' => $message,
        ]);
    }

    /**
     * @param array<int, string> $with
     */
    private function findClaimForCurrentAccount(string $id, array $with = []): Claim
    {
        $query = Claim::query()->where('account_id', $this->currentAccountId());

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

    private function currentAccountId(): string
    {
        return trim((string) app('current_account_id'));
    }

    /**
     * @return array<int, string>
     */
    private function shipmentColumnsForClaims(bool $includeFinancials = false): array
    {
        $columns = ['id', 'status'];

        foreach (['tracking_number', 'carrier_name'] as $column) {
            if (Schema::hasColumn('shipments', $column)) {
                $columns[] = $column;
            }
        }

        if ($includeFinancials) {
            foreach (['total_charge', 'total_cost'] as $column) {
                if (Schema::hasColumn('shipments', $column)) {
                    $columns[] = $column;
                }
            }
        }

        return array_values(array_unique($columns));
    }
}
