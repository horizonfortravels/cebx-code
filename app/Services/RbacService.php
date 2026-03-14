<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Rbac\PermissionsCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RbacService
{
    // ─── Maximum permissions per role (edge case from AC) ─────────
    private const MAX_PERMISSIONS_PER_ROLE = 100;

    /**
     * Create a custom role (starts with ZERO permissions — Least Privilege).
     */
    public function createRole(array $data, User $performer): Role
    {
        $this->assertCanManageRoles($performer);
        $accountId = $performer->account_id;

        // Check duplicate name
        $exists = Role::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            throw new BusinessException('اسم الدور موجود مسبقاً.', 'ERR_ROLE_EXISTS', 422);
        }

        return DB::transaction(function () use ($data, $performer, $accountId) {
            $role = Role::withoutGlobalScopes()->create([
                'account_id'   => $accountId,
                'name'         => $data['name'],
                'display_name' => $data['display_name'] ?? $data['name'],
                'description'  => $data['description'] ?? null,
                'is_system'    => false,
                'template'     => $data['template'] ?? null,
            ]);

            // If a template was specified, apply its permissions
            if (!empty($data['template'])) {
                $this->applyTemplate($role, $data['template']);
            }

            // If explicit permissions provided, sync them
            if (!empty($data['permissions'])) {
                $this->syncRolePermissions($role, $data['permissions'], $performer);
            }

            $this->logAction($accountId, $performer->id, 'role.created', 'Role', $role->id, null, [
                'name'        => $role->name,
                'template'    => $role->template,
                'permissions' => $role->permissions()->pluck('key')->toArray(),
            ]);

            return $role->load('permissions');
        });
    }

    /**
     * Create a role from a predefined template.
     */
    public function createFromTemplate(string $templateName, User $performer, ?string $customName = null): Role
    {
        $template = PermissionsCatalog::template($templateName);

        if (!$template) {
            throw new BusinessException('قالب الدور غير موجود.', 'ERR_TEMPLATE_NOT_FOUND', 404);
        }

        return $this->createRole([
            'name'         => $customName ?? $templateName,
            'display_name' => $template['display_name'],
            'description'  => $template['description'],
            'template'     => $templateName,
        ], $performer);
    }

    /**
     * Update a role's info and/or permissions.
     */
    public function updateRole(string $roleId, array $data, User $performer): Role
    {
        $this->assertCanManageRoles($performer);

        $role = $this->findRoleOrFail($roleId, $performer->account_id);

        if ($role->is_system) {
            throw new BusinessException('لا يمكن تعديل الأدوار الأساسية.', 'ERR_SYSTEM_ROLE', 422);
        }

        // Check name conflict if name is being changed
        if (isset($data['name']) && $data['name'] !== $role->name) {
            $exists = Role::withoutGlobalScopes()
                ->where('account_id', $performer->account_id)
                ->where('name', $data['name'])
                ->where('id', '!=', $role->id)
                ->exists();

            if ($exists) {
                throw new BusinessException('اسم الدور موجود مسبقاً.', 'ROLE_NAME_CONFLICT', 422);
            }
        }

        return DB::transaction(function () use ($role, $data, $performer) {
            $oldValues = $role->only(['name', 'display_name', 'description']);
            $oldPermissions = $role->permissions()->pluck('key')->toArray();

            // Update basic info
            $role->update(collect($data)->only(['name', 'display_name', 'description'])->toArray());

            // Sync permissions if provided
            if (isset($data['permissions'])) {
                $this->syncRolePermissions($role, $data['permissions'], $performer);
            }

            $role->refresh()->load('permissions');

            $this->logAction(
                $performer->account_id,
                $performer->id,
                'role.updated',
                'Role',
                $role->id,
                array_merge($oldValues, ['permissions' => $oldPermissions]),
                array_merge(
                    $role->only(['name', 'display_name', 'description']),
                    ['permissions' => $role->permissions->pluck('key')->toArray()]
                )
            );

            return $role;
        });
    }

    /**
     * Delete a role (soft delete). Cannot delete system roles.
     */
    public function deleteRole(string $roleId, User $performer): bool
    {
        $this->assertCanManageRoles($performer);

        $role = $this->findRoleOrFail($roleId, $performer->account_id);

        if ($role->is_system) {
            throw new BusinessException('لا يمكن حذف الأدوار الأساسية.', 'ERR_SYSTEM_ROLE', 422);
        }

        // Check if any users are assigned to this role
        $usersCount = $role->users()->count();
        if ($usersCount > 0) {
            throw new BusinessException(
                "لا يمكن حذف الدور. يوجد {$usersCount} مستخدم مرتبط به.",
                'ERR_ROLE_IN_USE',
                409
            );
        }

        return DB::transaction(function () use ($role, $performer) {
            $role->permissions()->detach();
            $role->delete();

            $this->logAction(
                $performer->account_id,
                $performer->id,
                'role.deleted',
                'Role',
                $role->id,
                ['name' => $role->name],
                null
            );

            return true;
        });
    }

    /**
     * Assign a role to a user.
     */
    public function assignRoleToUser(string $userId, string $roleId, User $performer): User
    {
        $this->assertCanAssignRoles($performer);

        $user = User::withoutGlobalScopes()
            ->where('account_id', $performer->account_id)
            ->where('id', $userId)
            ->firstOr(fn () => throw BusinessException::userNotFound());

        $role = $this->findRoleOrFail($roleId, $performer->account_id);

        // Check if already assigned
        if ($user->roles()->where('role_id', $role->id)->exists()) {
            throw new BusinessException('الدور معيّن بالفعل لهذا المستخدم.', 'ERR_ROLE_ALREADY_ASSIGNED', 422);
        }

        $user->roles()->attach($role->id, [
            'assigned_by' => $performer->id,
            'assigned_at' => now(),
        ]);

        $this->logAction(
            $performer->account_id,
            $performer->id,
            'role.assigned',
            'User',
            $user->id,
            null,
            ['role_id' => $role->id, 'role_name' => $role->name]
        );

        return $user->load('roles.permissions');
    }

    /**
     * Revoke a role from a user.
     */
    public function revokeRoleFromUser(string $userId, string $roleId, User $performer): User
    {
        $this->assertCanAssignRoles($performer);

        $user = User::withoutGlobalScopes()
            ->where('account_id', $performer->account_id)
            ->where('id', $userId)
            ->firstOr(fn () => throw BusinessException::userNotFound());

        $role = $this->findRoleOrFail($roleId, $performer->account_id);

        $user->roles()->detach($role->id);

        $this->logAction(
            $performer->account_id,
            $performer->id,
            'role.revoked',
            'User',
            $user->id,
            ['role_id' => $role->id, 'role_name' => $role->name],
            null
        );

        return $user->load('roles.permissions');
    }

    /**
     * List all roles for the current account.
     */
    public function listRoles(string $accountId): \Illuminate\Database\Eloquent\Collection
    {
        $this->assertAccountAllowsTeamManagement($accountId);

        return Role::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->with('permissions')
            ->withCount('users')
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all available permissions from the catalog.
     */
    public function getPermissionsCatalog(): array
    {
        return PermissionsCatalog::all();
    }

    /**
     * Get available templates.
     */
    public function getTemplates(): array
    {
        return PermissionsCatalog::templates();
    }

    /**
     * Create default system roles for a new account (called after account creation).
     */
    public function createDefaultRoles(string $accountId): void
    {
        $ownerRole = Role::withoutGlobalScopes()->create([
            'account_id'   => $accountId,
            'name'         => 'owner',
            'display_name' => 'مالك الحساب',
            'description'  => 'صلاحيات كاملة — لا يمكن حذفه',
            'is_system'    => true,
            'template'     => 'owner',
        ]);

        // Owner gets ALL permissions
        $allPermissionIds = Permission::pluck('id')->toArray();
        $ownerRole->syncPermissions($allPermissionIds);
    }

    // ─── Private Helpers ──────────────────────────────────────────

    private function syncRolePermissions(Role $role, array $permissionKeys, User $performer): void
    {
        // Validate: max permissions check
        if (count($permissionKeys) > self::MAX_PERMISSIONS_PER_ROLE) {
            throw new BusinessException(
                'تجاوز الحد الأقصى للصلاحيات لكل دور (' . self::MAX_PERMISSIONS_PER_ROLE . ').',
                'ERR_MAX_PERMISSIONS_EXCEEDED',
                422
            );
        }

        // Validate: all keys must exist in catalog
        foreach ($permissionKeys as $key) {
            if (!PermissionsCatalog::exists($key)) {
                throw new BusinessException(
                    "الصلاحية '{$key}' غير موجودة في كتالوج الصلاحيات.",
                    'PERMISSION_UNKNOWN',
                    422
                );
            }
        }
        // Validate: performer cannot grant permissions they do not already have.
        $performerPerms = $performer->allPermissions();
        $unauthorized = array_diff($permissionKeys, $performerPerms);
        if (!empty($unauthorized)) {
            throw new BusinessException(
                'Cannot grant permissions higher than your own permissions.',
                'ERR_ESCALATION_DENIED',
                403
            );
        }

        // Resolve permission IDs from keys
        $permissionIds = Permission::whereIn('key', $permissionKeys)->pluck('id')->toArray();

        $role->syncPermissions($permissionIds);
    }

    private function applyTemplate(Role $role, string $templateName): void
    {
        $template = PermissionsCatalog::template($templateName);
        if (!$template) {
            return;
        }

        $permissionIds = Permission::whereIn('key', $template['permissions'])->pluck('id')->toArray();
        $role->syncPermissions($permissionIds);
    }

    private function assertCanManageRoles(User $performer): void
    {
        if (!$performer->hasPermission('roles.manage')) {
            throw BusinessException::permissionDenied();
        }

        $this->assertAccountAllowsTeamManagement((string) $performer->account_id);
    }

    private function assertCanAssignRoles(User $performer): void
    {
        if (!$performer->hasPermission('roles.assign')) {
            throw BusinessException::permissionDenied();
        }

        $this->assertAccountAllowsTeamManagement((string) $performer->account_id);
    }

    private function findRoleOrFail(string $roleId, string $accountId): Role
    {
        $role = Role::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('id', $roleId)
            ->first();

        if (!$role) {
            throw new BusinessException('الدور غير موجود.', 'ERR_ROLE_NOT_FOUND', 404);
        }

        return $role;
    }

    /**
     * @throws BusinessException
     */
    private function assertAccountAllowsTeamManagement(string $accountId): void
    {
        /** @var Account|null $account */
        $account = Account::withoutGlobalScopes()->find($accountId);

        if ($account instanceof Account && !$account->allowsTeamManagement()) {
            throw BusinessException::accountUpgradeRequired();
        }
    }

    private function logAction(string $accountId, string $userId, string $action, string $entityType, string $entityId, ?array $old, ?array $new): void
    {
        AuditLog::withoutGlobalScopes()->create([
            'account_id'  => $accountId,
            'user_id'     => $userId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_values'  => $old,
            'new_values'  => $new,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);
    }
}



