<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class RatePolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'rates.read')
            || $this->allowsTenantAction($user, 'quotes.read');
    }

    public function view(User $user, mixed $resource): bool
    {
        $accountId = $this->resolveResourceAccountId($resource);
        if ($accountId !== null) {
            return $this->allowsTenantResourceAction($user, 'quotes.read', $accountId);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'pricing_rules.manage');
    }

    public function update(User $user, mixed $resource): bool
    {
        $accountId = $this->resolveResourceAccountId($resource);
        if ($accountId !== null) {
            return $this->allowsTenantResourceAction($user, 'pricing_rules.manage', $accountId);
        }

        return false;
    }

    public function delete(User $user, mixed $resource): bool
    {
        return $this->update($user, $resource);
    }

    public function manage(User $user, mixed $resource = null): bool
    {
        if ($resource !== null) {
            $accountId = $this->resolveResourceAccountId($resource);
            if ($accountId !== null) {
                return $this->allowsTenantResourceAction($user, 'quotes.manage', $accountId);
            }
        }

        return $this->allowsTenantAction($user, 'rates.manage');
    }

    private function resolveResourceAccountId(mixed $resource): ?string
    {
        if (!is_object($resource)) {
            return null;
        }

        $accountId = null;

        if ($resource instanceof \Illuminate\Database\Eloquent\Model) {
            $accountId = $resource->getAttribute('account_id');
        } elseif (isset($resource->account_id)) {
            $accountId = $resource->account_id;
        }

        $value = trim((string) ($accountId ?? ''));

        return $value === '' ? null : $value;
    }
}

