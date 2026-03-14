<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PermissionResolver
{
    public function can(?User $user, string $permission, ?string $tenantAccountId = null): bool
    {
        if (!$user) {
            return false;
        }

        $permission = trim($permission);
        if ($permission === '' || str_contains($permission, ':')) {
            return false;
        }

        $granted = $this->all($user, $tenantAccountId);
        if ($granted === []) {
            return false;
        }

        return in_array($permission, $granted, true);
    }

    /**
     * @return array<int, string>
     */
    public function all(?User $user, ?string $tenantAccountId = null): array
    {
        if (!$user) {
            return [];
        }

        $userType = $this->resolveUserType($user);

        if ($userType === 'internal') {
            return $this->internalPermissionKeys((string) $user->id);
        }

        return $this->externalPermissionKeys($user, $tenantAccountId);
    }

    /**
     * @return array<int, string>
     */
    private function internalPermissionKeys(string $userId): array
    {
        if (
            !Schema::hasTable('internal_user_role') ||
            !Schema::hasTable('internal_role_permission') ||
            !Schema::hasTable('permissions')
        ) {
            return [];
        }

        $query = DB::table('internal_user_role as iur')
            ->join('internal_role_permission as irp', 'irp.internal_role_id', '=', 'iur.internal_role_id')
            ->join('permissions as p', 'p.id', '=', 'irp.permission_id')
            ->where('iur.user_id', $userId);

        if (Schema::hasColumn('permissions', 'audience')) {
            $query->whereIn('p.audience', ['internal', 'both']);
        }

        /** @var array<int, string|null> $keys */
        $keys = $query->distinct()->pluck('p.key')->all();

        return $this->sanitizeKeys($keys);
    }

    /**
     * @return array<int, string>
     */
    private function externalPermissionKeys(User $user, ?string $tenantAccountId): array
    {
        if (
            !Schema::hasTable('user_role') ||
            !Schema::hasTable('roles') ||
            !Schema::hasTable('role_permission') ||
            !Schema::hasTable('permissions')
        ) {
            return [];
        }

        $userAccountId = (string) ($user->account_id ?? '');
        if ($userAccountId === '') {
            return [];
        }

        $activeAccountId = (string) ($tenantAccountId ?: $this->currentTenantAccountId() ?: $userAccountId);
        if ($activeAccountId === '' || $activeAccountId !== $userAccountId) {
            // External users are always bound to their own tenant.
            return [];
        }

        $query = DB::table('user_role as ur')
            ->join('roles as r', function ($join) use ($activeAccountId): void {
                $join->on('r.id', '=', 'ur.role_id')
                    ->where('r.account_id', '=', $activeAccountId);
            })
            ->join('role_permission as rp', 'rp.role_id', '=', 'r.id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('ur.user_id', (string) $user->id);

        if (Schema::hasColumn('permissions', 'audience')) {
            $query->whereIn('p.audience', ['external', 'both']);
        }

        /** @var array<int, string|null> $keys */
        $keys = $query->distinct()->pluck('p.key')->all();

        return $this->sanitizeKeys($keys);
    }

    private function currentTenantAccountId(): ?string
    {
        if (!app()->bound('current_account_id')) {
            return null;
        }

        $value = app('current_account_id');
        if (!is_scalar($value)) {
            return null;
        }

        $id = trim((string) $value);

        return $id === '' ? null : $id;
    }

    private function resolveUserType(User $user): string
    {
        $userType = strtolower(trim((string) ($user->user_type ?? '')));

        if ($userType === 'internal' || $userType === 'external') {
            return $userType;
        }

        return empty($user->account_id) ? 'internal' : 'external';
    }

    /**
     * @param array<int, string|null> $keys
     * @return array<int, string>
     */
    private function sanitizeKeys(array $keys): array
    {
        $clean = [];
        foreach ($keys as $key) {
            if ($key === null) {
                continue;
            }

            $value = trim((string) $key);
            if ($value === '' || str_contains($value, ':')) {
                continue;
            }

            $clean[] = $value;
        }

        return array_values(array_unique($clean));
    }
}
