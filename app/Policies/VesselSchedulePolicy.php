<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VesselSchedule;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\ResolvesTenantResourceAccount;

class VesselSchedulePolicy
{
    use AuthorizesTenantResource;
    use ResolvesTenantResourceAccount;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'vessel_schedules.read');
    }

    public function view(User $user, VesselSchedule $schedule): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'vessel_schedules.read',
            $this->resolveResourceAccountId($schedule)
        );
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'vessel_schedules.manage');
    }

    public function update(User $user, VesselSchedule $schedule): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'vessel_schedules.manage',
            $this->resolveResourceAccountId($schedule)
        );
    }

    public function delete(User $user, VesselSchedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }
}
