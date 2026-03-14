<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\ResolvesTenantResourceAccount;

class BranchPolicy
{
    use AuthorizesTenantResource;
    use ResolvesTenantResourceAccount;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'branches.read');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'branches.read',
            $this->resolveResourceAccountId($branch)
        );
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'branches.manage');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'branches.manage',
            $this->resolveResourceAccountId($branch)
        );
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch);
    }

    public function manageStaff(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch);
    }
}
