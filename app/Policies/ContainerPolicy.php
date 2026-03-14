<?php

namespace App\Policies;

use App\Models\Container;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\ResolvesTenantResourceAccount;

class ContainerPolicy
{
    use AuthorizesTenantResource;
    use ResolvesTenantResourceAccount;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'containers.read');
    }

    public function view(User $user, mixed $resource): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'containers.read',
            $this->resolveResourceAccountId($resource)
        );
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'containers.manage');
    }

    public function update(User $user, Container $container): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'containers.manage',
            $this->resolveResourceAccountId($container)
        );
    }

    public function delete(User $user, Container $container): bool
    {
        return $this->update($user, $container);
    }

    public function assignShipment(User $user, Container $container): bool
    {
        return $this->update($user, $container);
    }

    public function unloadShipment(User $user, Container $container): bool
    {
        return $this->update($user, $container);
    }
}
