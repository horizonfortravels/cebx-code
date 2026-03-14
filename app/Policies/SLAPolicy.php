<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\ResolvesTenantResourceAccount;

class SLAPolicy
{
    use AuthorizesTenantResource;
    use ResolvesTenantResourceAccount;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'sla.read');
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'sla.read',
            $this->resolveResourceAccountId($shipment)
        );
    }
}
