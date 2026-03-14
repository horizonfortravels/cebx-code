<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vessel;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\ResolvesTenantResourceAccount;

class VesselPolicy
{
    use AuthorizesTenantResource;
    use ResolvesTenantResourceAccount;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'vessels.read');
    }

    public function view(User $user, Vessel $vessel): bool
    {
        $accountId = $this->resolveResourceAccountId($vessel);

        if ($accountId === null) {
            return $this->allowsTenantAction($user, 'vessels.read');
        }

        return $this->allowsTenantResourceAction(
            $user,
            'vessels.read',
            $accountId
        );
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'vessels.manage');
    }

    public function update(User $user, Vessel $vessel): bool
    {
        $accountId = $this->resolveResourceAccountId($vessel);

        if ($accountId === null) {
            return $this->allowsTenantAction($user, 'vessels.manage');
        }

        return $this->allowsTenantResourceAction(
            $user,
            'vessels.manage',
            $accountId
        );
    }

    public function delete(User $user, Vessel $vessel): bool
    {
        return $this->update($user, $vessel);
    }
}
