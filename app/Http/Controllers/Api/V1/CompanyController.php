<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class CompanyController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Company::class);

        $query = $this->scopedCompanyQuery();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('country')) {
            $query->where('country', $request->country);
        }

        if ($request->filled('search')) {
            $query->where(function (Builder $builder) use ($request): void {
                $builder
                    ->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('legal_name', 'like', '%' . $request->search . '%');
            });
        }

        if ($this->companySupportsBranchRelation()) {
            $query->withCount('branches');
        }

        $companies = $query
            ->orderBy($request->get('sort', 'name'))
            ->paginate($request->get('per_page', 20));

        return response()->json(['data' => $companies]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'legal_name' => 'nullable|string|max:300',
            'registration_number' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:100',
            'country' => 'required|string|size:2',
            'base_currency' => 'nullable|string|size:3',
            'timezone' => 'nullable|string|max:50',
            'industry' => 'nullable|string|max:100',
            'website' => 'nullable|url|max:300',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
        ]);

        if (Schema::hasColumn('companies', 'account_id')) {
            $data['account_id'] = $this->currentAccountId();
        }
        $data['status'] = $data['status'] ?? 'active';

        $company = Company::create($data);
        $this->auditInfo('company.created', $company, ['name' => $company->name]);

        return response()->json([
            'data' => $company,
            'message' => 'تم إنشاء الشركة بنجاح',
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $with = [];
        if ($this->companySupportsBranchRelation()) {
            $with['branches'] = fn ($query) => $this->scopeBranchQuery($query)
                ->where('status', 'active');
        }

        $company = $this->findCompanyForCurrentAccount($id, $with);
        if ($this->companySupportsBranchRelation()) {
            $company->loadCount('branches');
        }
        $this->authorize('view', $company);

        return response()->json(['data' => $company]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $company = $this->findCompanyForCurrentAccount($id);
        $this->authorize('update', $company);

        $data = $request->validate([
            'name' => 'sometimes|string|max:200',
            'legal_name' => 'nullable|string|max:300',
            'registration_number' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:100',
            'country' => 'sometimes|string|size:2',
            'base_currency' => 'sometimes|string|size:3',
            'timezone' => 'sometimes|string|max:50',
            'industry' => 'nullable|string|max:100',
            'website' => 'nullable|url|max:300',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'status' => 'sometimes|in:active,suspended,inactive',
        ]);

        $company->update($data);
        $this->auditInfo('company.updated', $company, $data);

        return response()->json([
            'data' => $company->fresh(),
            'message' => 'تم تحديث الشركة',
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $company = $this->findCompanyForCurrentAccount($id);
        $this->authorize('delete', $company);

        if ($company->branches()->where('status', 'active')->exists()) {
            return response()->json(['message' => 'لا يمكن حذف شركة لديها فروع نشطة'], 422);
        }

        $company->delete();
        $this->auditInfo('company.deleted', $company);

        return response()->json(['message' => 'تم حذف الشركة']);
    }

    public function stats(string $id): JsonResponse
    {
        $company = $this->findCompanyForCurrentAccount($id);
        $this->authorize('view', $company);

        if (!$this->companySupportsBranchRelation()) {
            return response()->json(['data' => [
                'total_branches' => 0,
                'active_branches' => 0,
                'total_staff' => 0,
                'countries' => [],
                'branch_types' => [],
            ]]);
        }

        return response()->json(['data' => [
            'total_branches' => $company->branches()->count(),
            'active_branches' => $company->branches()->where('status', 'active')->count(),
            'total_staff' => $company->branches()->withCount('staff')->get()->sum('staff_count'),
            'countries' => $company->branches()->distinct('country')->pluck('country'),
            'branch_types' => $company->branches()
                ->selectRaw('branch_type, count(*) as count')
                ->groupBy('branch_type')
                ->pluck('count', 'branch_type'),
        ]]);
    }

    public function branches(Request $request, string $id): JsonResponse
    {
        $company = $this->findCompanyForCurrentAccount($id);
        $this->authorize('view', $company);

        if (!$this->companySupportsBranchRelation()) {
            return response()->json(['data' => new LengthAwarePaginator([], 0, $request->integer('per_page', 20))]);
        }

        $branches = $company->branches()
            ->when(Schema::hasColumn('branches', 'account_id'), fn (Builder $query): Builder => $query->where('account_id', $this->currentAccountId()))
            ->withCount('staff')
            ->when($request->filled('status'), fn (Builder $query): Builder => $query->where('status', $request->status))
            ->when($request->filled('type'), fn (Builder $query): Builder => $query->where('branch_type', $request->type))
            ->orderBy('name')
            ->paginate($request->get('per_page', 20));

        return response()->json(['data' => $branches]);
    }

    private function scopedCompanyQuery(): Builder
    {
        $query = Company::query();

        if (Schema::hasColumn('companies', 'account_id')) {
            return $query->where('account_id', $this->currentAccountId());
        }

        if (Schema::hasTable('branches')
            && Schema::hasColumn('branches', 'company_id')
            && Schema::hasColumn('branches', 'account_id')) {
            return $query->whereHas('branches', fn (Builder $builder): Builder => $this->scopeBranchQuery($builder));
        }

        return $query;
    }

    private function findCompanyForCurrentAccount(string $id, array $with = []): Company
    {
        return $this->scopedCompanyQuery()
            ->with($with)
            ->where('id', $id)
            ->firstOrFail();
    }

    private function currentAccountId(): string
    {
        return trim((string) app('current_account_id'));
    }

    private function scopeBranchQuery($query)
    {
        if (Schema::hasColumn('branches', 'account_id')) {
            $query->where('account_id', $this->currentAccountId());
        }

        return $query;
    }

    private function companySupportsBranchRelation(): bool
    {
        return Schema::hasTable('branches') && Schema::hasColumn('branches', 'company_id');
    }

    private function auditInfo(string $action, Company $company, ?array $newValues = null): void
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
            Company::class,
            (string) $company->id,
            null,
            $newValues
        );
    }
}
