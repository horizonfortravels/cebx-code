<?php

namespace Tests\Concerns;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

trait InteractsWithStrictRbac
{
    /**
     * @param array<int, string> $permissionKeys
     */
    protected function createTenantRoleWithPermissions(
        string $accountId,
        array $permissionKeys,
        ?string $roleName = null
    ): Role {
        $accountId = trim($accountId);
        if ($accountId === '') {
            throw new RuntimeException('Cannot create tenant RBAC role without account_id.');
        }

        $keys = $this->normalizePermissionKeys($permissionKeys);
        if ($keys === []) {
            throw new RuntimeException('At least one permission key is required.');
        }

        $roleName = $roleName ?? ('test_role_' . Str::lower(Str::random(8)));
        $roleSlug = Str::slug($roleName, '_');

        $role = Role::withoutGlobalScopes()->updateOrCreate(
            [
                'account_id' => $accountId,
                'slug' => $roleSlug,
            ],
            [
                'name' => $roleName,
                'display_name' => $roleName,
                'description' => 'Test RBAC role',
                'is_system' => false,
                'template' => null,
            ]
        );

        $permissionIds = [];
        foreach ($keys as $key) {
            $permission = Permission::query()->updateOrCreate(
                ['key' => $key],
                $this->permissionPayload($key)
            );
            $permissionIds[] = (string) $permission->id;
        }

        $role->permissions()->sync(
            collect($permissionIds)->mapWithKeys(static fn (string $permissionId): array => [
                $permissionId => ['granted_at' => now()],
            ])->all()
        );

        return $role;
    }

    /**
     * @param array<int, string> $permissionKeys
     */
    protected function grantTenantPermissions(
        User $user,
        array $permissionKeys,
        ?string $roleName = null
    ): Role {
        $accountId = trim((string) $user->account_id);
        if ($accountId === '') {
            throw new RuntimeException('Cannot grant tenant RBAC permissions to a user without account_id.');
        }

        $keys = $this->normalizePermissionKeys($permissionKeys);
        if ($keys === []) {
            throw new RuntimeException('At least one permission key is required.');
        }

        $role = $this->createTenantRoleWithPermissions($accountId, $keys, $roleName);

        $user->roles()->syncWithoutDetaching([
            (string) $role->id => [
                'assigned_by' => null,
                'assigned_at' => now(),
            ],
        ]);

        return $role;
    }

    /**
     * @param array<int, string> $permissionKeys
     * @return array<int, string>
     */
    private function normalizePermissionKeys(array $permissionKeys): array
    {
        $keys = [];
        foreach ($permissionKeys as $key) {
            $value = trim((string) $key);
            if ($value === '') {
                continue;
            }

            if (str_contains($value, ':')) {
                throw new RuntimeException(
                    sprintf('Strict RBAC tests require dot-notation permission keys. Invalid key: %s', $value)
                );
            }

            $keys[] = $value;
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array<string, string>
     */
    private function permissionPayload(string $key): array
    {
        $payload = [
            'group' => explode('.', $key)[0],
            'display_name' => $key,
            'description' => $key,
        ];

        if (Schema::hasColumn('permissions', 'audience')) {
            $payload['audience'] = 'external';
        }

        return $payload;
    }
}
