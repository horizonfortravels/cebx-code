<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\ResolvesTenantResourceAccount;

class RiskPolicy
{
    use AuthorizesTenantResource;
    use ResolvesTenantResourceAccount;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'risk.read');
    }

    public function view(User $user, mixed $resource): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'risk.read',
            $this->resolveResourceAccountId($resource)
        );
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'risk.manage');
    }

    public function batchScore(User $user): bool
    {
        return $this->allowsTenantAction($user, 'risk.manage');
    }

    public function suggestRoutes(User $user): bool
    {
        return $this->allowsTenantAction($user, 'risk.manage');
    }

    public function selectRoute(User $user, mixed $resource): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'risk.manage',
            $this->resolveResourceAccountId($resource)
        );
    }

    public function predictDelay(User $user, mixed $resource): bool
    {
        return $this->view($user, $resource);
    }

    public function fraudCheck(User $user, mixed $resource): bool
    {
        return $this->view($user, $resource);
    }

    public function analytics(User $user): bool
    {
        return $this->allowsTenantAction($user, 'risk.read');
    }

    public function dashboard(User $user): bool
    {
        return $this->allowsTenantAction($user, 'risk.read');
    }
}
