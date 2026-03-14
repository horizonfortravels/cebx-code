<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\EnforcesCanonicalExternalAccountModel;

class RolePolicy
{
    use AuthorizesTenantResource;
    use EnforcesCanonicalExternalAccountModel;

    public function viewAny(User $user): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantAction($user, 'roles.read');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantResourceAction($user, 'roles.read', $role->account_id);
    }

    public function catalog(User $user): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantAction($user, 'roles.read');
    }

    public function templates(User $user): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantAction($user, 'roles.read');
    }

    public function create(User $user): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantAction($user, 'roles.manage');
    }

    public function createFromTemplate(User $user): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantAction($user, 'roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantResourceAction($user, 'roles.manage', $role->account_id);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantResourceAction($user, 'roles.manage', $role->account_id);
    }

    public function assign(User $user, Role $role): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantResourceAction($user, 'roles.assign', $role->account_id);
    }

    public function revoke(User $user, Role $role): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantResourceAction($user, 'roles.assign', $role->account_id);
    }
}
