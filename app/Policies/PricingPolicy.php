<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class PricingPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'pricing.read');
    }

    public function view(User $user, mixed $resource): bool
    {
        $accountId = $this->resolveResourceAccountId($resource);
        if ($accountId !== null) {
            return $this->allowsTenantResourceAction($user, 'pricing.read', $accountId);
        }

        return false;
    }

    public function calculate(User $user): bool
    {
        return $this->allowsTenantAction($user, 'pricing.read');
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'pricing.manage');
    }

    public function update(User $user, mixed $resource): bool
    {
        $accountId = $this->resolveResourceAccountId($resource);
        if ($accountId !== null) {
            return $this->allowsTenantResourceAction($user, 'pricing.manage', $accountId);
        }

        return false;
    }

    public function manage(User $user, mixed $resource = null): bool
    {
        if ($resource !== null) {
            $accountId = $this->resolveResourceAccountId($resource);
            if ($accountId !== null) {
                return $this->allowsTenantResourceAction($user, 'pricing.manage', $accountId);
            }
        }

        return $this->allowsTenantAction($user, 'pricing.manage');
    }

    public function activate(User $user, mixed $resource): bool
    {
        return $this->update($user, $resource);
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

