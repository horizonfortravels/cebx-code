<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class AddressPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'addresses.read')
            || $this->allowsTenantAction($user, 'addresses.manage');
    }

    public function view(User $user, Address $address): bool
    {
        return $this->allowsTenantResourceAction($user, 'addresses.read', $address->account_id)
            || $this->allowsTenantResourceAction($user, 'addresses.manage', $address->account_id);
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'addresses.manage');
    }

    public function update(User $user, Address $address): bool
    {
        return $this->allowsTenantResourceAction($user, 'addresses.manage', $address->account_id);
    }

    public function delete(User $user, Address $address): bool
    {
        return $this->allowsTenantResourceAction($user, 'addresses.manage', $address->account_id);
    }
}
