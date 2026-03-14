<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\ResolvesTenantResourceAccount;

class CustomsDeclarationPolicy
{
    use AuthorizesTenantResource;
    use ResolvesTenantResourceAccount;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'customs.read');
    }

    public function view(User $user, mixed $resource): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'customs.read',
            $this->resolveResourceAccountId($resource)
        );
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'customs.manage');
    }

    public function update(User $user, mixed $resource): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'customs.manage',
            $this->resolveResourceAccountId($resource)
        );
    }

    public function updateStatus(User $user, mixed $resource): bool
    {
        return $this->update($user, $resource);
    }

    public function uploadDocument(User $user, mixed $resource): bool
    {
        return $this->update($user, $resource);
    }

    public function verifyDocument(User $user, mixed $resource): bool
    {
        return $this->update($user, $resource);
    }
}
