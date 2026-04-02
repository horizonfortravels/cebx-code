<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\InternalStaffAdminService;
use App\Support\Internal\InternalControlPlane;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InternalStaffReadCenterController extends Controller
{
    public function index(Request $request, InternalControlPlane $controlPlane): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'role' => $this->normalizedFilter((string) $request->query('role', ''), $controlPlane->canonicalRoles()),
            'status' => $this->normalizedFilter((string) $request->query('status', ''), ['active', 'suspended', 'disabled', 'inactive']),
            'deprecated' => $this->normalizedFilter((string) $request->query('deprecated', ''), ['flagged', 'clean']),
        ];

        $staffQuery = $this->filteredStaffQuery($filters, $controlPlane)
            ->with(['internalRoles'])
            ->orderBy('name');

        $staff = $staffQuery->paginate(15)->withQueryString();
        $staff->setCollection(
            $staff->getCollection()->map(fn (User $user): array => $this->buildIndexRow($user, $controlPlane))
        );

        $statsBaseQuery = $this->internalUsersQuery();

        return view('pages.admin.staff-index', [
            'staff' => $staff,
            'filters' => $filters,
            'canCreateStaff' => $this->viewerCanCreateStaff(auth()->user(), $controlPlane),
            'stats' => [
                'total' => (clone $statsBaseQuery)->count(),
                'active' => (clone $statsBaseQuery)->where('status', 'active')->count(),
                'deprecated' => (clone $statsBaseQuery)
                    ->whereHas('internalRoles', function (Builder $query) use ($controlPlane): void {
                        $query->whereNotIn('name', $controlPlane->canonicalRoles());
                    })
                    ->count(),
            ],
            'roleOptions' => collect($controlPlane->canonicalRoles())
                ->mapWithKeys(fn (string $role): array => [$role => $controlPlane->roleProfileForCanonicalRole($role)['label']])
                ->all(),
            'statusOptions' => [
                'active' => 'نشط',
                'suspended' => 'موقوف',
                'disabled' => 'معطل',
                'inactive' => 'غير نشط',
            ],
        ]);
    }

    public function show(
        string $user,
        InternalControlPlane $controlPlane,
        InternalStaffAdminService $staffAdminService,
    ): View
    {
        $staffUser = $this->internalUsersQuery()
            ->with(['internalRoles'])
            ->findOrFail($user);
        $viewer = auth()->user();

        $staffSummary = $this->staffSummary($staffUser, $controlPlane);
        $permissionSummary = $this->permissionSummary($staffUser, $staffSummary);
        $latestActivity = $this->latestActivitySummary($staffUser);
        $canManageLifecycle = $this->viewerCanManageStaffLifecycle($viewer, $controlPlane);
        $canTriggerPasswordReset = $this->viewerCanTriggerStaffPasswordReset($viewer, $controlPlane);

        return view('pages.admin.staff-show', [
            'staffUser' => $staffUser,
            'staffSummary' => $staffSummary,
            'canUpdateStaff' => $this->viewerCanUpdateStaff($viewer, $controlPlane),
            'canManageLifecycle' => $canManageLifecycle,
            'canTriggerPasswordReset' => $canTriggerPasswordReset,
            'availableLifecycleActions' => $canManageLifecycle
                ? $staffAdminService->availableLifecycleActions($staffUser, $viewer)
                : [],
            'lifecycleProtectionMessage' => $canManageLifecycle
                ? $staffAdminService->lifecycleProtectionMessage($staffUser, $viewer)
                : null,
            'statusLabel' => $this->statusLabel((string) ($staffUser->status ?? 'active')),
            'lastLoginAt' => optional($staffUser->last_login_at)->format('Y-m-d H:i'),
            'emailVerifiedAt' => optional($staffUser->email_verified_at)->format('Y-m-d H:i'),
            'permissionSummary' => $permissionSummary,
            'latestActivity' => $latestActivity,
        ]);
    }

    private function filteredStaffQuery(array $filters, InternalControlPlane $controlPlane): Builder
    {
        return $this->internalUsersQuery()
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $search = '%' . $filters['q'] . '%';

                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search);
                });
            })
            ->when($filters['role'] !== '', function (Builder $query) use ($filters, $controlPlane): void {
                $query->whereHas('internalRoles', function (Builder $roleQuery) use ($filters, $controlPlane): void {
                    $roleQuery->whereIn('name', $this->roleFilterNames($filters['role'], $controlPlane));
                });
            })
            ->when($filters['status'] !== '', static function (Builder $query) use ($filters): void {
                $query->where('status', $filters['status']);
            })
            ->when($filters['deprecated'] !== '', function (Builder $query) use ($filters, $controlPlane): void {
                $constraint = static function (Builder $roleQuery) use ($controlPlane): void {
                    $roleQuery->whereNotIn('name', $controlPlane->canonicalRoles());
                };

                if ($filters['deprecated'] === 'flagged') {
                    $query->whereHas('internalRoles', $constraint);

                    return;
                }

                $query->whereDoesntHave('internalRoles', $constraint);
            });
    }

    private function internalUsersQuery(): Builder
    {
        $query = User::query()->withoutGlobalScopes();

        if (!Schema::hasColumn('users', 'user_type')) {
            return $query->whereNull('account_id');
        }

        return $query->where(function (Builder $inner): void {
            $inner->where('user_type', 'internal')
                ->orWhere(function (Builder $legacy): void {
                    $legacy->where(function (Builder $legacyType): void {
                        $legacyType->whereNull('user_type')
                            ->orWhere('user_type', '');
                    })->whereNull('account_id');
                });
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildIndexRow(User $user, InternalControlPlane $controlPlane): array
    {
        $summary = $this->staffSummary($user, $controlPlane);

        return [
            'user' => $user,
            'roleLabel' => $summary['label'],
            'hasDeprecatedAssignments' => $summary['hasDeprecatedAssignments'],
            'statusLabel' => $this->statusLabel((string) ($user->status ?? 'active')),
            'lastLoginAt' => optional($user->last_login_at)->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array{
     *     primaryRole: string|null,
     *     canonicalRoles: array<int, string>,
     *     canonicalRoleLabels: array<int, string>,
     *     isCanonicalAlignmentClean: bool,
     *     label: string,
     *     description: string,
     *     landingRoute: string|null,
     *     landingRouteNote: string|null,
     *     hasDeprecatedAssignments: bool
     * }
     */
    private function staffSummary(User $user, InternalControlPlane $controlPlane): array
    {
        $assignedRoleNames = $this->assignedRoleNames($user);
        $canonicalRoles = $controlPlane->resolvedCanonicalRolesFromNames($assignedRoleNames);
        $primaryRole = $controlPlane->primaryCanonicalRoleFromNames($assignedRoleNames);
        $hasDeprecatedAssignments = $controlPlane->hasDeprecatedAssignmentsFromNames($assignedRoleNames);
        $roleProfile = $controlPlane->roleProfileForCanonicalRole($primaryRole);

        $label = $primaryRole !== null
            ? $roleProfile['label']
            : ($hasDeprecatedAssignments
                ? 'دور داخلي قديم مخفي من الواجهة النشطة'
                : 'لا يوجد دور داخلي نشط');

        $description = $primaryRole !== null
            ? $roleProfile['description']
            : ($hasDeprecatedAssignments
                ? 'تم إخفاء مسمى الدور القديم من الواجهة النشطة حتى استكمال مواءمة حساب الموظف مع الأدوار المعتمدة.'
                : 'لا توجد تعيينات ضمن الأدوار الداخلية المعتمدة لهذا الحساب.');

        $canonicalRoleLabels = collect($canonicalRoles)
            ->map(fn (string $role): string => $controlPlane->roleProfileForCanonicalRole($role)['label'])
            ->values()
            ->all();
        $isCanonicalAlignmentClean = $primaryRole !== null
            && !$hasDeprecatedAssignments
            && count($canonicalRoles) === 1;

        return [
            'primaryRole' => $primaryRole,
            'canonicalRoles' => $canonicalRoles,
            'canonicalRoleLabels' => $canonicalRoleLabels,
            'isCanonicalAlignmentClean' => $isCanonicalAlignmentClean,
            'label' => $label,
            'description' => $description,
            'landingRoute' => $isCanonicalAlignmentClean ? $roleProfile['landing_route'] : null,
            'landingRouteNote' => $isCanonicalAlignmentClean
                ? null
                : 'تم تعليق عرض نقطة الهبوط المتوقعة حتى يقتصر الحساب على دور داخلي معتمد واحد دون تعيينات قديمة.',
            'hasDeprecatedAssignments' => $hasDeprecatedAssignments,
        ];
    }

    /**
     * @param array{
     *     primaryRole: string|null,
     *     canonicalRoles: array<int, string>,
     *     isCanonicalAlignmentClean: bool
     * } $staffSummary
     * @return array{count: int, groups: array<string, int>, isVisible: bool, note: string}
     */
    private function permissionSummary(User $user, array $staffSummary): array
    {
        if (($staffSummary['isCanonicalAlignmentClean'] ?? false) !== true || !is_string($staffSummary['primaryRole'] ?? null)) {
            return [
                'count' => 0,
                'groups' => [],
                'isVisible' => false,
                'note' => 'تم إخفاء ملخص الصلاحيات حتى تكتمل مواءمة هذا الحساب مع دور داخلي معتمد واحد دون تعيينات قديمة.',
            ];
        }

        $permissions = collect($this->canonicalPermissionKeysForRole($user, $staffSummary['primaryRole']))
            ->filter(static fn ($permission): bool => is_string($permission) && trim($permission) !== '')
            ->map(static fn (string $permission): string => trim($permission))
            ->sort()
            ->values();

        /** @var array<string, int> $groups */
        $groups = $permissions
            ->map(static function (string $permission): string {
                $group = explode('.', $permission)[0] ?? $permission;

                return Str::headline(str_replace('_', ' ', $group));
            })
            ->countBy()
            ->sortKeys()
            ->all();

        return [
            'count' => $permissions->count(),
            'groups' => $groups,
            'isVisible' => true,
            'note' => 'يعرض هذا الملخص للقراءة فقط وهو مشتق من الدور الداخلي المعتمد الحالي.',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function canonicalPermissionKeysForRole(User $user, string $roleName): array
    {
        if (
            !Schema::hasTable('internal_user_role') ||
            !Schema::hasTable('internal_roles') ||
            !Schema::hasTable('internal_role_permission') ||
            !Schema::hasTable('permissions')
        ) {
            return [];
        }

        $query = DB::table('internal_user_role as iur')
            ->join('internal_roles as ir', 'ir.id', '=', 'iur.internal_role_id')
            ->join('internal_role_permission as irp', 'irp.internal_role_id', '=', 'ir.id')
            ->join('permissions as p', 'p.id', '=', 'irp.permission_id')
            ->where('iur.user_id', (string) $user->id)
            ->where('ir.name', $roleName);

        if (Schema::hasColumn('permissions', 'audience')) {
            $query->whereIn('p.audience', ['internal', 'both']);
        }

        /** @var array<int, string|null> $keys */
        $keys = $query->distinct()->pluck('p.key')->all();

        return collect($keys)
            ->filter(static fn ($key): bool => is_string($key) && trim($key) !== '')
            ->map(static fn (string $key): string => trim($key))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{action: string|null, at: string|null}
     */
    private function latestActivitySummary(User $user): array
    {
        if (!Schema::hasTable('audit_logs') || !Schema::hasColumn('audit_logs', 'user_id')) {
            return ['action' => null, 'at' => null];
        }

        $latest = AuditLog::query()
            ->where('user_id', (string) $user->id)
            ->latest('created_at')
            ->first(['action', 'created_at']);

        return [
            'action' => $latest?->action,
            'at' => optional($latest?->created_at)->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function assignedRoleNames(User $user): array
    {
        $roles = $user->relationLoaded('internalRoles')
            ? $user->internalRoles
            : $user->internalRoles()->get();

        if (!$roles instanceof Collection) {
            return [];
        }

        return $roles->pluck('name')
            ->map(static fn ($name): string => strtolower(trim((string) $name)))
            ->filter(static fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function roleFilterNames(string $canonicalRole, InternalControlPlane $controlPlane): array
    {
        $aliases = collect($controlPlane->legacyRoleAliases())
            ->filter(static fn (string $mappedRole): bool => $mappedRole === $canonicalRole)
            ->keys()
            ->values()
            ->all();

        return array_values(array_unique(array_merge([$canonicalRole], $aliases)));
    }

    /**
     * @param array<int, string> $allowed
     */
    private function normalizedFilter(string $value, array $allowed): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, $allowed, true) ? $normalized : '';
    }

    private function statusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'active' => 'نشط',
            'suspended' => 'موقوف',
            'disabled' => 'معطل',
            'inactive' => 'غير نشط',
            default => Str::headline($status === '' ? 'غير محدد' : $status),
        };
    }
    private function viewerCanCreateStaff(?User $viewer, InternalControlPlane $controlPlane): bool
    {
        return $viewer instanceof User
            && $viewer->hasPermission('users.manage')
            && $viewer->hasPermission('roles.assign')
            && $controlPlane->canSeeSurface($viewer, InternalControlPlane::SURFACE_INTERNAL_STAFF_CREATE);
    }

    private function viewerCanUpdateStaff(?User $viewer, InternalControlPlane $controlPlane): bool
    {
        return $viewer instanceof User
            && $viewer->hasPermission('users.manage')
            && $viewer->hasPermission('roles.assign')
            && $controlPlane->canSeeSurface($viewer, InternalControlPlane::SURFACE_INTERNAL_STAFF_UPDATE);
    }

    private function viewerCanManageStaffLifecycle(?User $viewer, InternalControlPlane $controlPlane): bool
    {
        return $viewer instanceof User
            && $viewer->hasPermission('users.manage')
            && $controlPlane->canSeeSurface($viewer, InternalControlPlane::SURFACE_INTERNAL_STAFF_LIFECYCLE);
    }

    private function viewerCanTriggerStaffPasswordReset(?User $viewer, InternalControlPlane $controlPlane): bool
    {
        return $viewer instanceof User
            && $viewer->hasPermission('users.manage')
            && $controlPlane->canSeeSurface($viewer, InternalControlPlane::SURFACE_INTERNAL_STAFF_SUPPORT_ACTIONS);
    }
}
