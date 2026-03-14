<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\ResolvesTenantResourceAccount;

class ClaimPolicy
{
    use AuthorizesTenantResource;
    use ResolvesTenantResourceAccount;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'claims.read');
    }

    public function view(User $user, mixed $resource): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'claims.read',
            $this->resolveResourceAccountId($resource)
        );
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'claims.manage');
    }

    public function update(User $user, mixed $resource): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'claims.manage',
            $this->resolveResourceAccountId($resource)
        );
    }

    public function submit(User $user, mixed $resource): bool
    {
        return $this->update($user, $resource);
    }

    public function assign(User $user, mixed $resource): bool
    {
        return $this->update($user, $resource);
    }

    public function investigate(User $user, mixed $resource): bool
    {
        return $this->update($user, $resource);
    }

    public function assess(User $user, mixed $resource): bool
    {
        return $this->update($user, $resource);
    }

    public function approve(User $user, mixed $resource): bool
    {
        return $this->update($user, $resource);
    }

    public function reject(User $user, mixed $resource): bool
    {
        return $this->update($user, $resource);
    }

    public function settle(User $user, mixed $resource): bool
    {
        return $this->update($user, $resource);
    }

    public function close(User $user, mixed $resource): bool
    {
        return $this->update($user, $resource);
    }

    public function resolve(User $user, mixed $resource): bool
    {
        return $this->update($user, $resource);
    }

    public function appeal(User $user, mixed $resource): bool
    {
        return $this->update($user, $resource);
    }

    public function uploadDocument(User $user, mixed $resource): bool
    {
        return $this->update($user, $resource);
    }

    public function deleteDocument(User $user, mixed $resource): bool
    {
        return $this->update($user, $resource);
    }
}
