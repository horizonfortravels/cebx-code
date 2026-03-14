<?php

namespace App\Policies;

use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\ProofOfDelivery;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\ResolvesTenantResourceAccount;

class DriverPolicy
{
    use AuthorizesTenantResource;
    use ResolvesTenantResourceAccount;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'drivers.read');
    }

    public function view(User $user, mixed $resource): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            $this->readPermissionFor($resource),
            $this->resolveResourceAccountId($resource)
        );
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'drivers.manage');
    }

    public function update(User $user, Driver $driver): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'drivers.manage',
            $this->resolveResourceAccountId($driver)
        );
    }

    public function delete(User $user, Driver $driver): bool
    {
        return $this->update($user, $driver);
    }

    public function updateLocation(User $user, Driver $driver): bool
    {
        return $this->update($user, $driver);
    }

    public function updateStatus(User $user, Driver $driver): bool
    {
        return $this->update($user, $driver);
    }

    public function viewAssignments(User $user): bool
    {
        return $this->allowsTenantAction($user, 'delivery.read');
    }

    public function createAssignment(User $user): bool
    {
        return $this->allowsTenantAction($user, 'delivery.manage');
    }

    public function manageAssignment(User $user, DeliveryAssignment $assignment): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'delivery.manage',
            $this->resolveResourceAccountId($assignment)
        );
    }

    public function viewPods(User $user): bool
    {
        return $this->allowsTenantAction($user, 'proof_of_deliveries.read');
    }

    public function submitPod(User $user, DeliveryAssignment $assignment): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'delivery.manage',
            $this->resolveResourceAccountId($assignment)
        );
    }

    public function viewPod(User $user, ProofOfDelivery $pod): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'proof_of_deliveries.read',
            $this->resolveResourceAccountId($pod)
        );
    }

    private function readPermissionFor(mixed $resource): string
    {
        return match (true) {
            $resource instanceof DeliveryAssignment => 'delivery.read',
            $resource instanceof ProofOfDelivery => 'proof_of_deliveries.read',
            default => 'drivers.read',
        };
    }
}
