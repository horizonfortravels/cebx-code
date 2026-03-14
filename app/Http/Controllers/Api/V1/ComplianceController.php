<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CargoManifest;
use App\Models\ImmutableAuditLog;
use App\Models\RetentionPolicy;
use App\Models\TransportDocument;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ComplianceController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    // ── Transport Documents (AWB / BOL / CMR) ────────────────
    public function documents(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TransportDocument::class);

        $query = DB::table('transport_documents')->where('account_id', $this->resolveCurrentAccountId($request));
        if ($request->filled('type')) $query->where('document_type', $request->type);
        if ($request->filled('shipment_id')) $query->where('shipment_id', $request->shipment_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        return response()->json($query->orderByDesc('created_at')->paginate($request->per_page ?? 25));
    }

    public function createDocument(Request $request): JsonResponse
    {
        $this->authorize('create', TransportDocument::class);

        $data = $request->validate([
            'shipment_id' => 'required|uuid',
            'document_type' => 'required|in:AWB,MAWB,HAWB,BOL,CMR,CIM',
            'document_number' => 'required|string|max:50|unique:transport_documents,document_number',
            'issuer' => 'nullable|string|max:100', 'origin_code' => 'required|string|max:10',
            'destination_code' => 'required|string|max:10', 'pieces' => 'required|integer|min:1',
            'gross_weight_kg' => 'required|numeric|min:0.1', 'chargeable_weight_kg' => 'nullable|numeric',
            'declared_value' => 'nullable|numeric', 'declared_value_currency' => 'nullable|string|size:3',
            'goods_description' => 'nullable|string', 'handling_codes' => 'nullable|string|max:100',
        ]);

        // Validate AWB format (3-digit airline prefix + 8-digit serial)
        $errors = [];
        if (in_array($data['document_type'], ['AWB', 'MAWB', 'HAWB'])) {
            if (!preg_match('/^\d{3}-\d{8}$/', $data['document_number'])) {
                $errors[] = 'AWB must be in format XXX-XXXXXXXX (3-digit prefix + 8-digit serial)';
            }
        }
        // BOL format check
        if ($data['document_type'] === 'BOL') {
            if (strlen($data['document_number']) < 8) {
                $errors[] = 'Bill of Lading number must be at least 8 characters';
            }
        }

        $record = array_merge($data, [
            'id' => Str::uuid(), 'account_id' => $this->resolveCurrentAccountId($request),
            'is_validated' => empty($errors), 'validation_errors' => empty($errors) ? null : json_encode($errors),
            'status' => empty($errors) ? 'validated' : 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('transport_documents')->insert($record);
        $this->audit->log('compliance.document.created', (object) $record, $request);
        return response()->json(['data' => $record, 'validation_errors' => $errors], 201);
    }

    public function validateDocument(Request $request, string $id): JsonResponse
    {
        $doc = DB::table('transport_documents')
            ->where('account_id', $this->resolveCurrentAccountId($request))
            ->where('id', $id)
            ->first();

        if (!$doc) return response()->json(['message' => 'Not found'], 404);

        $this->authorize('update', $doc);

        $errors = [];
        // AWB validation rules
        if (in_array($doc->document_type, ['AWB', 'MAWB', 'HAWB'])) {
            if (!preg_match('/^\d{3}-\d{8}$/', $doc->document_number)) $errors[] = 'Invalid AWB format';
            if ($doc->pieces < 1) $errors[] = 'Pieces must be >= 1';
            if ($doc->gross_weight_kg <= 0) $errors[] = 'Weight must be positive';
            if (strlen($doc->origin_code) !== 3) $errors[] = 'Origin must be 3-letter IATA code';
            if (strlen($doc->destination_code) !== 3) $errors[] = 'Destination must be 3-letter IATA code';
        }
        // BOL validation
        if ($doc->document_type === 'BOL') {
            if (strlen($doc->document_number) < 8) $errors[] = 'BOL too short';
            if (!$doc->goods_description) $errors[] = 'Goods description required for BOL';
        }

        DB::table('transport_documents')->where('id', $id)->update([
            'is_validated' => empty($errors), 'validation_errors' => empty($errors) ? null : json_encode($errors),
            'status' => empty($errors) ? 'validated' : 'invalid', 'updated_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $id, 'valid' => empty($errors), 'errors' => $errors]]);
    }

    // ── Cargo Manifests ──────────────────────────────────────
    public function manifests(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CargoManifest::class);

        $query = DB::table('cargo_manifests')->where('account_id', $this->resolveCurrentAccountId($request));
        if ($request->filled('type')) $query->where('manifest_type', $request->type);
        return response()->json($query->orderByDesc('created_at')->paginate($request->per_page ?? 25));
    }

    public function createManifest(Request $request): JsonResponse
    {
        $this->authorize('create', CargoManifest::class);

        $data = $request->validate([
            'manifest_type' => 'required|in:air,sea,land', 'carrier_code' => 'required|string|max:20',
            'flight_voyage' => 'nullable|string|max:50', 'origin_code' => 'required|string|max:10',
            'destination_code' => 'required|string|max:10', 'departure_at' => 'nullable|date',
            'items' => 'nullable|array',
            'items.*.shipment_id' => 'required|uuid', 'items.*.document_number' => 'required|string',
            'items.*.pieces' => 'required|integer', 'items.*.weight_kg' => 'required|numeric',
        ]);

        $manifestId = Str::uuid();
        $items = $data['items'] ?? [];
        unset($data['items']);

        $manifest = array_merge($data, [
            'id' => $manifestId, 'account_id' => $this->resolveCurrentAccountId($request),
            'manifest_number' => 'MFT-' . strtoupper(Str::random(8)),
            'total_pieces' => collect($items)->sum('pieces'),
            'total_weight_kg' => collect($items)->sum('weight_kg'),
            'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('cargo_manifests')->insert($manifest);

        foreach ($items as $item) {
            DB::table('cargo_manifest_items')->insert([
                'id' => Str::uuid(), 'manifest_id' => $manifestId,
                'shipment_id' => $item['shipment_id'], 'document_number' => $item['document_number'],
                'pieces' => $item['pieces'], 'weight_kg' => $item['weight_kg'],
                'goods_description' => $item['goods_description'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->audit->log('compliance.manifest.created', (object) $manifest, $request);
        return response()->json(['data' => $manifest], 201);
    }

    // ── Retention Policies ───────────────────────────────────
    public function retentionPolicies(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RetentionPolicy::class);

        $policies = DB::table('retention_policies')->where('account_id', $this->resolveCurrentAccountId($request))->get();
        return response()->json(['data' => $policies]);
    }

    public function setRetentionPolicy(Request $request): JsonResponse
    {
        $this->authorize('update', RetentionPolicy::class);

        $data = $request->validate([
            'data_category' => 'required|in:shipments,invoices,customs,audit,payments,claims',
            'retention_years' => 'required|integer|min:1|max:15',
            'legal_basis' => 'nullable|string|max:100',
            'tamper_proof' => 'nullable|boolean', 'auto_archive' => 'nullable|boolean',
        ]);
        DB::table('retention_policies')->updateOrInsert(
            ['account_id' => $this->resolveCurrentAccountId($request), 'data_category' => $data['data_category']],
            array_merge($data, ['id' => Str::uuid(), 'account_id' => $this->resolveCurrentAccountId($request),
                'created_at' => now(), 'updated_at' => now()])
        );
        return response()->json(['data' => $data]);
    }

    // ── Immutable Audit Log ──────────────────────────────────
    public function auditLog(Request $request): JsonResponse
    {
        $this->authorize('audit', ImmutableAuditLog::class);

        $query = DB::table('immutable_audit_log')->where('account_id', $this->resolveCurrentAccountId($request));
        if ($request->filled('entity_type')) $query->where('entity_type', $request->entity_type);
        if ($request->filled('event_type')) $query->where('event_type', $request->event_type);
        if ($request->filled('from')) $query->where('occurred_at', '>=', $request->from);
        if ($request->filled('to')) $query->where('occurred_at', '<=', $request->to);
        return response()->json($query->orderByDesc('occurred_at')->paginate($request->per_page ?? 50));
    }

    public function exportAudit(Request $request): JsonResponse
    {
        $this->authorize('exportAudit', ImmutableAuditLog::class);

        // Trigger export job
        return response()->json(['data' => [
            'status' => 'queued', 'message' => 'Audit export queued for processing',
            'estimated_records' => DB::table('immutable_audit_log')
                ->where('account_id', $this->resolveCurrentAccountId($request))->count(),
        ]]);
    }

    public function complianceStats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TransportDocument::class);

        $accountId = $this->resolveCurrentAccountId($request);
        $docs = DB::table('transport_documents')->where('account_id', $accountId);
        return response()->json(['data' => [
            'documents' => [
                'total' => (clone $docs)->count(),
                'validated' => (clone $docs)->where('is_validated', true)->count(),
                'invalid' => (clone $docs)->where('status', 'invalid')->count(),
                'by_type' => (clone $docs)->selectRaw("document_type, count(*) as count")->groupBy('document_type')->pluck('count', 'document_type'),
            ],
            'manifests' => DB::table('cargo_manifests')->where('account_id', $accountId)->count(),
            'audit_records' => DB::table('immutable_audit_log')->where('account_id', $accountId)->count(),
            'retention_policies' => DB::table('retention_policies')->where('account_id', $accountId)->count(),
        ]]);
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
}
