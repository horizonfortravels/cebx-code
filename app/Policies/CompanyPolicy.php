<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\ResolvesTenantResourceAccount;

class CompanyPolicy
{
    use AuthorizesTenantResource;
    use ResolvesTenantResourceAccount;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'companies.read');
    }

    public function view(User $user, Company $company): bool
    {
        $accountId = $this->resolveResourceAccountId($company);

        if ($accountId === null) {
            return $this->allowsTenantAction($user, 'companies.read');
        }

        return $this->allowsTenantResourceAction(
            $user,
            'companies.read',
            $accountId
        );
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'companies.manage');
    }

    public function update(User $user, Company $company): bool
    {
        $accountId = $this->resolveResourceAccountId($company);

        if ($accountId === null) {
            return $this->allowsTenantAction($user, 'companies.manage');
        }

        return $this->allowsTenantResourceAction(
            $user,
            'companies.manage',
            $accountId
        );
    }

    public function delete(User $user, Company $company): bool
    {
        return $this->update($user, $company);
    }
}
