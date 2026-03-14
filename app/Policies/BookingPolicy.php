<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\ResolvesTenantResourceAccount;

class BookingPolicy
{
    use AuthorizesTenantResource;
    use ResolvesTenantResourceAccount;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'booking.manage');
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'booking.manage');
    }

    public function manage(User $user, Shipment $shipment): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'booking.manage',
            $this->resolveResourceAccountId($shipment)
        );
    }
}
