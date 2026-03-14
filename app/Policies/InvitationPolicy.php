<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\EnforcesCanonicalExternalAccountModel;

class InvitationPolicy
{
    use AuthorizesTenantResource;
    use EnforcesCanonicalExternalAccountModel;

    public function viewAny(User $user): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantAction($user, 'users.read');
    }

    public function view(User $user, Invitation $invitation): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantResourceAction($user, 'users.read', $invitation->account_id);
    }

    public function create(User $user): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantAction($user, 'users.invite');
    }

    public function update(User $user, Invitation $invitation): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantResourceAction($user, 'users.invite', $invitation->account_id);
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantResourceAction($user, 'users.invite', $invitation->account_id);
    }

    public function cancel(User $user, Invitation $invitation): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantResourceAction($user, 'users.invite', $invitation->account_id);
    }

    public function resend(User $user, Invitation $invitation): bool
    {
        return $this->allowsOrganizationTeamManagement($user)
            && $this->allowsTenantResourceAction($user, 'users.invite', $invitation->account_id);
    }
}
