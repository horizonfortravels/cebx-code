<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\ResolvesTenantResourceAccount;

class InsurancePolicy
{
    use AuthorizesTenantResource;
    use ResolvesTenantResourceAccount;

    public function quote(User $user): bool
    {
        return $this->allowsTenantAction($user, 'insurance.manage');
    }

    public function purchase(User $user, Shipment $shipment): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'insurance.manage',
            $this->resolveResourceAccountId($shipment)
        );
    }

    public function fileClaim(User $user, Shipment $shipment): bool
    {
        return $this->purchase($user, $shipment);
    }
}
