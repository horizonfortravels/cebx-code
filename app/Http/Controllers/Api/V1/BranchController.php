<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchStaff;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class BranchController extends Controller
{
    public function companiesIndex(Request $request): JsonResponse
    {
        $query = Company::query()->where('account_id', $this->currentAccountId());

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->where(function ($builder) use ($request): void {
                $builder
                    ->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('legal_name', 'like', '%' . $request->search . '%');
            });
        }

        return response()->json([
            'data' => $query->orderBy('name')->paginate($request->per_page ?? 25),
        ]);
    }

    public function companiesStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'legal_name' => 'nullable|string|max:300',
            'registration_number' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:100',
            'country' => 'required|string|size:2',
            'base_currency' => 'nullable|string|size:3',
            'timezone' => 'nullable|string|max:50',
            'industry' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'website' => 'nullable|url',
        ]);

        $validated['account_id'] = $this->currentAccountId();
        $company = Company::create($validated);

        return response()->json([
            'data' => $company,
            'message' => 'طھظ… ط¥ظ†ط´ط§ط، ط§ظ„ط´ط±ظƒط© ط¨ظ†ط¬ط§ط­',
        ], 201);
    }

    public function companiesShow(string $id): JsonResponse
    {
        $company = Company::query()
            ->where('account_id', $this->currentAccountId())
            ->with('branches')
            ->where('id', $id)
            ->firstOrFail();

        return response()->json(['data' => $company]);
    }

    public function companiesUpdate(Request $request, string $id): JsonResponse
    {
        $company = Company::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $id)
            ->firstOrFail();

        $company->update($request->only([
            'name',
            'legal_name',
            'registration_number',
            'tax_id',
            'country',
            'base_currency',
            'timezone',
            'industry',
            'phone',
            'email',
            'address',
            'website',
            'status',
        ]));

        return response()->json([
            'data' => $company,
            'message' => 'طھظ… طھط­ط¯ظٹط« ط§ظ„ط´ط±ظƒط©',
        ]);
    }

    public function companiesDestroy(string $id): JsonResponse
    {
        Company::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $id)
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'طھظ… ط­ط°ظپ ط§ظ„ط´ط±ظƒط©']);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Branch::class);

        $query = $this->scopedBranchQuery()
            ->with('company:id,name');

        if ($request->company_id) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->branch_type) {
            $query->where('branch_type', $request->branch_type);
        }

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
                    ->orWhere('code', 'like', '%' . $request->search . '%')
                    ->orWhere('city', 'like', '%' . $request->search . '%');
            });
        }

        return response()->json([
            'data' => $query->orderBy('name')->paginate($request->per_page ?? 25),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Branch::class);

        $validated = $request->validate([
            'company_id' => 'required|uuid|exists:companies,id',
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:20|unique:branches,code',
            'country' => 'required|string|size:2',
            'city' => 'required|string|max:100',
            'branch_type' => 'required|in:headquarters,hub,port,airport,office,warehouse,customs_office',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email',
            'manager_name' => 'nullable|string|max:200',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'capabilities' => 'nullable|array',
            'operating_hours' => 'nullable|array',
        ]);

        Company::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $validated['company_id'])
            ->firstOrFail();

        $validated['account_id'] = $this->currentAccountId();
        $branch = Branch::create($validated);

        return response()->json([
            'data' => $branch,
            'message' => 'طھظ… ط¥ظ†ط´ط§ط، ط§ظ„ظپط±ط¹ ط¨ظ†ط¬ط§ط­',
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $relations = ['staff.user'];
        if (Schema::hasColumn('branches', 'company_id')) {
            $relations[] = 'company';
        }
        if (Schema::hasColumn('drivers', 'branch_id')) {
            $relations[] = 'drivers';
        }

        $branch = $this->findBranchForCurrentAccount($id, $relations);
        $this->authorize('view', $branch);

        return response()->json(['data' => $branch]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $branch = $this->findBranchForCurrentAccount($id);
        $this->authorize('update', $branch);

        $branch->update($request->only([
            'name',
            'city',
            'address',
            'branch_type',
            'phone',
            'email',
            'manager_name',
            'manager_user_id',
            'latitude',
            'longitude',
            'status',
            'capabilities',
            'operating_hours',
        ]));

        return response()->json([
            'data' => $branch,
            'message' => 'طھظ… طھط­ط¯ظٹط« ط§ظ„ظپط±ط¹',
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $branch = $this->findBranchForCurrentAccount($id);
        $this->authorize('delete', $branch);

        $branch->delete();

        return response()->json(['message' => 'طھظ… ط­ط°ظپ ط§ظ„ظپط±ط¹']);
    }

    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Branch::class);

        $branchQuery = $this->scopedBranchQuery();

        return response()->json(['data' => [
            'total' => (clone $branchQuery)->count(),
            'active' => Schema::hasColumn('branches', 'status')
                ? (clone $branchQuery)->where('status', 'active')->count()
                : (clone $branchQuery)->where('is_active', true)->count(),
            'by_type' => Schema::hasColumn('branches', 'branch_type')
                ? (clone $branchQuery)->selectRaw('branch_type, count(*) as count')->groupBy('branch_type')->pluck('count', 'branch_type')
                : collect(),
            'by_country' => Schema::hasColumn('branches', 'country')
                ? (clone $branchQuery)->selectRaw('country, count(*) as count')->groupBy('country')->pluck('count', 'country')
                : collect(),
            'companies' => Schema::hasColumn('companies', 'account_id')
                ? Company::query()->where('account_id', $this->currentAccountId())->count()
                : 0,
        ]]);
    }

    public function assignStaff(Request $request, string $id): JsonResponse
    {
        $branch = $this->findBranchForCurrentAccount($id);
        $this->authorize('manageStaff', $branch);

        $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'role' => 'nullable|string|max:50',
        ]);

        User::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $request->user_id)
            ->firstOrFail();

        BranchStaff::updateOrCreate(
            ['branch_id' => $branch->id, 'user_id' => $request->user_id],
            ['role' => $request->role ?? 'agent', 'assigned_at' => now(), 'released_at' => null]
        );

        return response()->json(['message' => 'طھظ… طھط¹ظٹظٹظ† ط§ظ„ظ…ظˆط¸ظپ']);
    }

    public function staff(string $id): JsonResponse
    {
        $branch = $this->findBranchForCurrentAccount($id, ['staff.user']);
        $this->authorize('view', $branch);

        return response()->json(['data' => $branch->staff]);
    }

    public function removeStaff(string $id, string $userId): JsonResponse
    {
        $branch = $this->findBranchForCurrentAccount($id);
        $this->authorize('manageStaff', $branch);

        BranchStaff::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $userId)
            ->update(['released_at' => now()]);

        return response()->json(['message' => 'طھظ… ط¥ظ„ط؛ط§ط، طھط¹ظٹظٹظ† ط§ظ„ظ…ظˆط¸ظپ']);
    }

    /**
     * @param array<int, string> $with
     */
    private function findBranchForCurrentAccount(string $id, array $with = []): Branch
    {
        $query = $this->scopedBranchQuery();

        if ($with !== []) {
            $query->with($with);
        }

        return $query->where('id', $id)->firstOrFail();
    }

    private function scopedBranchQuery(): Builder
    {
        if (Schema::hasColumn('branches', 'account_id')) {
            return Branch::query()->where('account_id', $this->currentAccountId());
        }

        return Branch::query()->whereHas('staff.user', function (Builder $builder): void {
            $builder->where('account_id', $this->currentAccountId());
        });
    }

    private function currentAccountId(): string
    {
        return trim((string) app('current_account_id'));
    }
}
