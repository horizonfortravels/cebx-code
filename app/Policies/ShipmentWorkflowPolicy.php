<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\ResolvesTenantResourceAccount;

class ShipmentWorkflowPolicy
{
    use AuthorizesTenantResource;
    use ResolvesTenantResourceAccount;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'shipment_workflow.read');
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'shipment_workflow.read',
            $this->resolveResourceAccountId($shipment)
        );
    }

    public function manage(User $user, Shipment $shipment): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'shipment_workflow.manage',
            $this->resolveResourceAccountId($shipment)
        );
    }
}
