<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\EnforcesCanonicalExternalAccountModel;

class UserPolicy
{
    use AuthorizesTenantResource;
    use EnforcesCanonicalExternalAccountModel;

    public function viewAny(User $user): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantAction($user, 'users.read');
    }

    public function view(User $user, User $target): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantResourceAction($user, 'users.read', $target->account_id);
    }

    public function create(User $user): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantAction($user, 'users.manage');
    }

    public function update(User $user, User $target): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantResourceAction($user, 'users.manage', $target->account_id);
    }

    public function delete(User $user, User $target): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantResourceAction($user, 'users.manage', $target->account_id);
    }

    public function disable(User $user, User $target): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantResourceAction($user, 'users.manage', $target->account_id);
    }

    public function enable(User $user, User $target): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantResourceAction($user, 'users.manage', $target->account_id);
    }

    public function assignRole(User $user): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantAction($user, 'roles.assign');
    }
}
