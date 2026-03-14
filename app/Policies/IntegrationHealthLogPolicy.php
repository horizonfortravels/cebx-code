<?php

namespace App\Policies;

use App\Models\IntegrationHealthLog;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class IntegrationHealthLogPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'integrations.read');
    }

    public function view(User $user, mixed $integrationHealthLog = null): bool
    {
        if ($integrationHealthLog instanceof IntegrationHealthLog && property_exists($integrationHealthLog, 'account_id')) {
            return $this->allowsTenantResourceAction($user, 'integrations.read', $integrationHealthLog->account_id);
        }

        if (is_object($integrationHealthLog) && property_exists($integrationHealthLog, 'account_id')) {
            return $this->allowsTenantResourceAction($user, 'integrations.read', $integrationHealthLog->account_id);
        }

        return $this->allowsTenantAction($user, 'integrations.read');
    }

    public function manage(User $user): bool
    {
        return $this->allowsTenantAction($user, 'integrations.manage');
    }
}

