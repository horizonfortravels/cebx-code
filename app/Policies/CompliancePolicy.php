<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class CompliancePolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'compliance.read');
    }

    public function view(User $user, mixed $resource = null): bool
    {
        $resourceAccountId = $this->resolveResourceAccountId($resource);
        if ($resourceAccountId !== null) {
            return $this->allowsTenantResourceAction($user, 'compliance.read', $resourceAccountId);
        }

        return $this->allowsTenantAction($user, 'compliance.read');
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'compliance.manage');
    }

    public function update(User $user, mixed $resource = null): bool
    {
        $resourceAccountId = $this->resolveResourceAccountId($resource);
        if ($resourceAccountId !== null) {
            return $this->allowsTenantResourceAction($user, 'compliance.manage', $resourceAccountId);
        }

        return $this->allowsTenantAction($user, 'compliance.manage');
    }

    public function audit(User $user): bool
    {
        return $this->allowsTenantAction($user, 'compliance.audit.read');
    }

    public function exportAudit(User $user): bool
    {
        return $this->allowsTenantAction($user, 'compliance.audit.export');
    }

    private function resolveResourceAccountId(mixed $resource): ?string
    {
        if (!is_object($resource)) {
            return null;
        }

        $accountId = null;

        if ($resource instanceof \Illuminate\Database\Eloquent\Model) {
            $accountId = $resource->getAttribute('account_id');
        } elseif (isset($resource->account_id)) {
            $accountId = $resource->account_id;
        }

        $value = trim((string) ($accountId ?? ''));

        return $value === '' ? null : $value;
    }
}
