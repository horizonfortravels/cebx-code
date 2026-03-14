<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use Illuminate\Database\Eloquent\Model;

class ReportPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'reports.read');
    }

    public function view(User $user, mixed $resource): bool
    {
        $accountId = $this->resolveResourceAccountId($resource);
        if ($accountId === null) {
            return false;
        }

        return $this->allowsTenantResourceAction($user, 'reports.read', $accountId);
    }

    public function export(User $user, mixed $resource = null): bool
    {
        if ($resource !== null) {
            $accountId = $this->resolveResourceAccountId($resource);
            if ($accountId === null) {
                return false;
            }

            return $this->allowsTenantResourceAction($user, 'reports.export', $accountId);
        }

        return $this->allowsTenantAction($user, 'reports.export');
    }

    public function manage(User $user, mixed $resource = null): bool
    {
        if ($resource !== null) {
            $accountId = $this->resolveResourceAccountId($resource);
            if ($accountId === null) {
                return false;
            }

            return $this->allowsTenantResourceAction($user, 'reports.manage', $accountId);
        }

        return $this->allowsTenantAction($user, 'reports.manage');
    }

    private function resolveResourceAccountId(mixed $resource): ?string
    {
        if (!$resource instanceof Model && !is_object($resource)) {
            return null;
        }

        $accountId = null;

        if ($resource instanceof Model) {
            $accountId = $resource->getAttribute('account_id');
        } elseif (isset($resource->account_id)) {
            $accountId = $resource->account_id;
        }

        $value = trim((string) ($accountId ?? ''));

        return $value === '' ? null : $value;
    }
}
