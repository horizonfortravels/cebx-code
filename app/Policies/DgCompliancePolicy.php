<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class DgCompliancePolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'dg.read');
    }

    public function view(User $user, mixed $resource = null): bool
    {
        $resourceAccountId = $this->resolveResourceAccountId($resource);
        if ($resourceAccountId !== null) {
            return $this->allowsTenantResourceAction($user, 'dg.read', $resourceAccountId);
        }

        return $this->allowsTenantAction($user, 'dg.read');
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'dg.manage');
    }

    public function manage(User $user, mixed $resource = null): bool
    {
        $resourceAccountId = $this->resolveResourceAccountId($resource);
        if ($resourceAccountId !== null) {
            return $this->allowsTenantResourceAction($user, 'dg.manage', $resourceAccountId);
        }

        return $this->allowsTenantAction($user, 'dg.manage');
    }

    public function update(User $user, mixed $resource = null): bool
    {
        return $this->manage($user, $resource);
    }

    public function audit(User $user, mixed $resource = null): bool
    {
        return $this->view($user, $resource);
    }

    public function exportAudit(User $user): bool
    {
        return $this->allowsTenantAction($user, 'dg.manage');
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
