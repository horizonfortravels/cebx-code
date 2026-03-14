<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AuthorizesTenantResource
{
    protected function resolveCurrentAccountId(): ?string
    {
        $id = app()->bound('current_account_id')
            ? trim((string) app('current_account_id'))
            : '';

        if ($id !== '') {
            return $id;
        }

        return null;
    }

    protected function resolveUserType(User $user): string
    {
        $userType = strtolower(trim((string) ($user->user_type ?? '')));

        if ($userType === 'internal' || $userType === 'external') {
            return $userType;
        }

        return empty($user->account_id) ? 'internal' : 'external';
    }

    protected function hasTenantContext(User $user): bool
    {
        $currentAccountId = $this->resolveCurrentAccountId();
        if ($currentAccountId === null) {
            return false;
        }

        if ($this->resolveUserType($user) === 'external') {
            $userAccountId = trim((string) ($user->account_id ?? ''));

            return $userAccountId !== '' && $userAccountId === $currentAccountId;
        }

        // Internal users can act on tenant-bound resources only when context is explicitly bound.
        return true;
    }

    protected function tenantResourceMatchesCurrentAccount(User $user, string|int|null $resourceAccountId): bool
    {
        if (!$this->hasTenantContext($user)) {
            return false;
        }

        $currentAccountId = $this->resolveCurrentAccountId();
        if ($currentAccountId === null) {
            return false;
        }

        $resourceAccountId = trim((string) $resourceAccountId);
        if ($resourceAccountId === '') {
            return false;
        }

        return $resourceAccountId === $currentAccountId;
    }

    protected function allowsTenantAction(User $user, string $permission): bool
    {
        if (!$this->hasTenantContext($user)) {
            return false;
        }

        return $user->hasPermission($permission);
    }

    protected function allowsTenantResourceAction(User $user, string $permission, string|int|null $resourceAccountId): bool
    {
        if (!$this->allowsTenantAction($user, $permission)) {
            return false;
        }

        return $this->tenantResourceMatchesCurrentAccount($user, $resourceAccountId);
    }
}
