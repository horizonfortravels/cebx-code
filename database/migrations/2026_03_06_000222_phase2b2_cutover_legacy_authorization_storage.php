<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $columnCache = [];

    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $this->migrateInternalLegacyAdminsToSuperAdminRole();
        $this->migrateExternalLegacyRolesToTenantRoles();
        $this->migrateOrganizationMemberCustomPermissions();
        $this->removeMixedRealmAssignments();
        $this->dropLegacyAuthorizationStorage();
    }

    public function down(): void
    {
        // Forward-only cutover.
    }

    private function migrateInternalLegacyAdminsToSuperAdminRole(): void
    {
        if (
            !Schema::hasTable('internal_roles') ||
            !Schema::hasTable('internal_user_role') ||
            !Schema::hasColumn('internal_user_role', 'user_id') ||
            !Schema::hasColumn('internal_user_role', 'internal_role_id')
        ) {
            return;
        }

        $hasUserType = $this->hasColumn('users', 'user_type');
        $hasIsSuperAdmin = $this->hasColumn('users', 'is_super_admin');
        $hasRole = $this->hasColumn('users', 'role');
        $hasRoleName = $this->hasColumn('users', 'role_name');

        if (!$hasIsSuperAdmin && !$hasRole && !$hasRoleName) {
            return;
        }

        $query = DB::table('users')->select('id');

        if ($hasUserType) {
            $query->where('user_type', 'internal');
        }

        $query->where(function ($inner) use ($hasIsSuperAdmin, $hasRole, $hasRoleName): void {
            if ($hasIsSuperAdmin) {
                $inner->orWhere('is_super_admin', true);
            }

            if ($hasRole) {
                $inner->orWhereRaw("LOWER(COALESCE(role, '')) IN ('admin', 'super_admin', 'super-admin', 'platform_admin', 'platform-admin')");
            }

            if ($hasRoleName) {
                $inner->orWhereRaw("LOWER(COALESCE(role_name, '')) IN ('admin', 'super admin', 'superadmin', 'platform admin')");
            }
        });

        $userIds = $query->pluck('id')->map(static fn ($id): string => (string) $id)->all();

        if ($userIds === []) {
            return;
        }

        $superAdminRoleId = $this->ensureInternalSuperAdminRole();

        if ($superAdminRoleId === '') {
            return;
        }

        foreach ($userIds as $userId) {
            DB::table('internal_user_role')->updateOrInsert(
                [
                    'user_id' => $userId,
                    'internal_role_id' => $superAdminRoleId,
                ],
                [
                    'assigned_by' => null,
                    'assigned_at' => now(),
                ]
            );
        }
    }

    private function migrateExternalLegacyRolesToTenantRoles(): void
    {
        if (
            !Schema::hasTable('roles') ||
            !Schema::hasTable('user_role') ||
            !$this->hasColumn('users', 'account_id')
        ) {
            return;
        }

        $query = DB::table('users')->select(['id', 'account_id']);

        if ($this->hasColumn('users', 'user_type')) {
            $query->where('user_type', 'external');
        }

        $query->whereNotNull('account_id');

        if ($this->hasColumn('users', 'role')) {
            $query->addSelect('role');
        }
        if ($this->hasColumn('users', 'role_name')) {
            $query->addSelect('role_name');
        }
        if ($this->hasColumn('users', 'is_owner')) {
            $query->addSelect('is_owner');
        }

        $users = $query->get();

        foreach ($users as $user) {
            $accountId = trim((string) ($user->account_id ?? ''));
            $userId = trim((string) ($user->id ?? ''));

            if ($accountId === '' || $userId === '') {
                continue;
            }

            $targetRole = $this->resolveLegacyTenantRole($user);
            $roleId = $this->ensureTenantRoleForAccount($accountId, $targetRole);

            if ($roleId === '') {
                continue;
            }

            DB::table('user_role')->updateOrInsert(
                [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                ],
                [
                    'assigned_by' => null,
                    'assigned_at' => now(),
                ]
            );
        }
    }

    private function migrateOrganizationMemberCustomPermissions(): void
    {
        if (
            !Schema::hasTable('organization_members') ||
            !Schema::hasTable('organizations') ||
            !Schema::hasTable('roles') ||
            !Schema::hasTable('user_role') ||
            !$this->hasColumn('organization_members', 'custom_permissions') ||
            !$this->hasColumn('organization_members', 'user_id') ||
            !$this->hasColumn('organization_members', 'organization_id') ||
            !$this->hasColumn('organizations', 'account_id') ||
            !$this->hasColumn('organizations', 'id')
        ) {
            return;
        }

        $query = DB::table('organization_members as om')
            ->join('organizations as o', 'o.id', '=', 'om.organization_id')
            ->select([
                'om.user_id',
                'o.account_id',
                'om.custom_permissions',
            ]);

        if (Schema::hasTable('users') && $this->hasColumn('users', 'id') && $this->hasColumn('users', 'user_type')) {
            $query
                ->join('users as u', 'u.id', '=', 'om.user_id')
                ->where('u.user_type', 'external');
        }

        $rows = $query->get();

        foreach ($rows as $row) {
            $userId = trim((string) ($row->user_id ?? ''));
            $accountId = trim((string) ($row->account_id ?? ''));

            if ($userId === '' || $accountId === '') {
                continue;
            }

            $permissions = $this->decodePermissionList($row->custom_permissions ?? null);
            if ($permissions === []) {
                continue;
            }

            $targetRole = $this->resolveTenantRoleFromCustomPermissions($permissions);
            $roleId = $this->ensureTenantRoleForAccount($accountId, $targetRole);

            if ($roleId === '') {
                continue;
            }

            DB::table('user_role')->updateOrInsert(
                [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                ],
                [
                    'assigned_by' => null,
                    'assigned_at' => now(),
                ]
            );
        }
    }

    private function removeMixedRealmAssignments(): void
    {
        if (!Schema::hasTable('users') || !$this->hasColumn('users', 'user_type')) {
            return;
        }

        if (Schema::hasTable('user_role') && $this->hasColumn('user_role', 'user_id')) {
            DB::table('user_role')
                ->whereIn('user_id', function ($q): void {
                    $q->select('id')->from('users')->where('user_type', 'internal');
                })
                ->delete();
        }

        if (Schema::hasTable('internal_user_role') && $this->hasColumn('internal_user_role', 'user_id')) {
            DB::table('internal_user_role')
                ->whereIn('user_id', function ($q): void {
                    $q->select('id')->from('users')->where('user_type', 'external');
                })
                ->delete();
        }
    }

    private function dropLegacyAuthorizationStorage(): void
    {
        if (Schema::hasTable('users')) {
            $dropColumns = [];
            foreach (['role', 'role_name', 'is_super_admin'] as $column) {
                if ($this->hasColumn('users', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                Schema::table('users', function (Blueprint $table) use ($dropColumns): void {
                    $table->dropColumn($dropColumns);
                });
            }
        }

        if (Schema::hasTable('organization_members') && $this->hasColumn('organization_members', 'custom_permissions')) {
            Schema::table('organization_members', function (Blueprint $table): void {
                $table->dropColumn('custom_permissions');
            });
        }

        if (Schema::hasTable('permission_catalog')) {
            Schema::drop('permission_catalog');
        }
    }

    private function ensureInternalSuperAdminRole(): string
    {
        if (!Schema::hasTable('internal_roles') || !$this->hasColumn('internal_roles', 'id') || !$this->hasColumn('internal_roles', 'name')) {
            return '';
        }

        $existing = DB::table('internal_roles')
            ->where('name', 'super_admin')
            ->value('id');

        if ($existing) {
            return (string) $existing;
        }

        $id = (string) Str::uuid();

        $payload = [
            'id' => $id,
            'name' => 'super_admin',
        ];

        if ($this->hasColumn('internal_roles', 'display_name')) {
            $payload['display_name'] = 'SuperAdmin';
        }
        if ($this->hasColumn('internal_roles', 'description')) {
            $payload['description'] = 'Migrated from legacy internal admin indicators in Phase 2B2.';
        }
        if ($this->hasColumn('internal_roles', 'is_system')) {
            $payload['is_system'] = true;
        }
        if ($this->hasColumn('internal_roles', 'created_at')) {
            $payload['created_at'] = now();
        }
        if ($this->hasColumn('internal_roles', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        DB::table('internal_roles')->insert($payload);

        return $id;
    }

    private function ensureTenantRoleForAccount(string $accountId, string $roleName): string
    {
        if (!Schema::hasTable('roles') || !$this->hasColumn('roles', 'id') || !$this->hasColumn('roles', 'name')) {
            return '';
        }

        $query = DB::table('roles')->where('name', $roleName);

        if ($this->hasColumn('roles', 'account_id')) {
            $query->where('account_id', $accountId);
        }

        $existing = $query->value('id');

        if ($existing) {
            return (string) $existing;
        }

        $definitions = [
            'organization_owner' => [
                'display_name' => 'OrganizationOwner',
                'description' => 'Migrated organization owner role from legacy grants.',
            ],
            'organization_admin' => [
                'display_name' => 'OrganizationAdmin',
                'description' => 'Migrated organization admin role from legacy grants.',
            ],
            'staff' => [
                'display_name' => 'Staff',
                'description' => 'Migrated organization staff role from legacy grants.',
            ],
        ];

        $roleMeta = $definitions[$roleName] ?? $definitions['staff'];
        $id = (string) Str::uuid();

        $payload = [
            'id' => $id,
            'name' => $roleName,
        ];

        if ($this->hasColumn('roles', 'account_id')) {
            $payload['account_id'] = $accountId;
        }
        if ($this->hasColumn('roles', 'display_name')) {
            $payload['display_name'] = $roleMeta['display_name'];
        }
        if ($this->hasColumn('roles', 'description')) {
            $payload['description'] = $roleMeta['description'];
        }
        if ($this->hasColumn('roles', 'is_system')) {
            $payload['is_system'] = true;
        }
        if ($this->hasColumn('roles', 'template')) {
            $payload['template'] = $roleName;
        }
        if ($this->hasColumn('roles', 'created_at')) {
            $payload['created_at'] = now();
        }
        if ($this->hasColumn('roles', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        DB::table('roles')->insert($payload);

        return $id;
    }

    private function resolveLegacyTenantRole(object $user): string
    {
        $isOwner = isset($user->is_owner) && (bool) $user->is_owner;

        if ($isOwner) {
            return 'organization_owner';
        }

        $role = $this->normalizeString($user->role ?? '');
        $roleName = $this->normalizeString($user->role_name ?? '');

        if (
            $this->containsAny($role, ['owner', 'tenant_owner', 'organization_owner', 'account_owner', 'مالك']) ||
            $this->containsAny($roleName, ['owner', 'مالك'])
        ) {
            return 'organization_owner';
        }

        if (
            $this->containsAny($role, ['api_developer', 'developer', 'integration_admin', 'integration']) ||
            $this->containsAny($roleName, ['developer', 'integration', 'مطور'])
        ) {
            return 'organization_admin';
        }

        if (
            $this->containsAny($role, ['tenant_admin', 'organization_admin', 'admin', 'manager', 'supervisor']) ||
            $this->containsAny($roleName, ['admin', 'manager', 'supervisor', 'مدير', 'مشرف'])
        ) {
            return 'organization_admin';
        }

        return 'staff';
    }

    /**
     * @param array<int, string> $permissions
     */
    private function resolveTenantRoleFromCustomPermissions(array $permissions): string
    {
        $normalized = array_values(array_unique(array_map(
            fn (string $key): string => $this->normalizePermissionKey($key),
            $permissions
        )));

        $adminMarkers = [
            'users.manage',
            'roles.manage',
            'roles.assign',
            'orders.manage',
            'wallet.manage',
            'shipments.manage',
            'account.manage',
            'tenancy.context.select',
        ];

        $apiMarkers = [
            'api_keys.read',
            'api_keys.manage',
            'webhooks.read',
            'webhooks.manage',
            'integrations.read',
            'integrations.manage',
        ];

        foreach ($normalized as $key) {
            if (in_array($key, $adminMarkers, true)) {
                return 'organization_admin';
            }
        }

        foreach ($normalized as $key) {
            if (in_array($key, $apiMarkers, true)) {
                return 'organization_admin';
            }
        }

        return 'staff';
    }

    /**
     * @return array<int, string>
     */
    private function decodePermissionList(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }

        $items = null;
        if (is_array($raw)) {
            $items = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        }

        if (!is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $key = $this->normalizePermissionKey((string) $item);
            if ($key === '') {
                continue;
            }

            $normalized[] = $key;
        }

        return array_values(array_unique($normalized));
    }

    private function normalizePermissionKey(string $key): string
    {
        return strtolower(str_replace(':', '.', trim($key)));
    }

    private function normalizeString(mixed $value): string
    {
        $string = trim((string) $value);

        if ($string === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($string, 'UTF-8');
        }

        return strtolower($string);
    }

    /**
     * @param array<int, string> $tokens
     */
    private function containsAny(string $haystack, array $tokens): bool
    {
        if ($haystack === '') {
            return false;
        }

        foreach ($tokens as $token) {
            if ($token !== '' && str_contains($haystack, $this->normalizeString($token))) {
                return true;
            }
        }

        return false;
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        if (!isset($this->columnCache[$table])) {
            $this->columnCache[$table] = Schema::getColumnListing($table);
        }

        return in_array($column, $this->columnCache[$table], true);
    }
};

