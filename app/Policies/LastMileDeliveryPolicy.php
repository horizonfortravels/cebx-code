<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\Shipment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\ResolvesTenantResourceAccount;

class LastMileDeliveryPolicy
{
    use AuthorizesTenantResource;
    use ResolvesTenantResourceAccount;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'delivery.read');
    }

    public function assign(User $user, Shipment $shipment): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'delivery.manage',
            $this->resolveResourceAccountId($shipment)
        );
    }

    public function submitPod(User $user, Shipment $shipment): bool
    {
        return $this->assign($user, $shipment);
    }

    public function recordFailure(User $user, Shipment $shipment): bool
    {
        return $this->assign($user, $shipment);
    }

    public function viewDriverAssignments(User $user, Driver $driver): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'delivery.read',
            $this->resolveResourceAccountId($driver)
        );
    }
}
