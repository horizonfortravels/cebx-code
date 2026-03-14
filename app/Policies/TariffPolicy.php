<?php

namespace App\Policies;

use App\Models\TariffRule;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\ResolvesTenantResourceAccount;

class TariffPolicy
{
    use AuthorizesTenantResource;
    use ResolvesTenantResourceAccount;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'tariffs.read');
    }

    public function view(User $user, mixed $resource): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'tariffs.read',
            $this->resolveResourceAccountId($resource)
        );
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'tariffs.manage');
    }

    public function update(User $user, TariffRule $tariff): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'tariffs.manage',
            $this->resolveResourceAccountId($tariff)
        );
    }

    public function delete(User $user, TariffRule $tariff): bool
    {
        return $this->update($user, $tariff);
    }

    public function calculate(User $user): bool
    {
        return $this->allowsTenantAction($user, 'tariffs.manage');
    }

    public function viewCharges(User $user, mixed $resource): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'tariffs.read',
            $this->resolveResourceAccountId($resource)
        );
    }

    public function addCharge(User $user, mixed $resource): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'tariffs.manage',
            $this->resolveResourceAccountId($resource)
        );
    }

    public function removeCharge(User $user, mixed $resource): bool
    {
        return $this->addCharge($user, $resource);
    }

    public function viewTaxRules(User $user): bool
    {
        return $user->hasPermission('tax_rules.read');
    }

    public function createTaxRule(User $user): bool
    {
        return $user->hasPermission('tax_rules.manage');
    }
}
