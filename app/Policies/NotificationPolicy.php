<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use Illuminate\Database\Eloquent\Model;

class NotificationPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'notifications.read');
    }

    public function view(User $user, mixed $resource): bool
    {
        $accountId = $this->resolveResourceAccountId($resource);
        if ($accountId === null) {
            return false;
        }

        return $this->allowsTenantResourceAction($user, 'notifications.read', $accountId);
    }

    public function manage(User $user, mixed $resource = null): bool
    {
        if ($resource !== null) {
            $accountId = $this->resolveResourceAccountId($resource);
            if ($accountId === null) {
                return false;
            }

            return $this->allowsTenantResourceAction($user, 'notifications.manage', $accountId);
        }

        return $this->allowsTenantAction($user, 'notifications.manage');
    }

    public function manageTemplates(User $user, mixed $resource = null): bool
    {
        if ($resource !== null) {
            $accountId = $this->resolveResourceAccountId($resource);
            if ($accountId === null) {
                return false;
            }

            return $this->allowsTenantResourceAction($user, 'notifications.templates.manage', $accountId);
        }

        return $this->allowsTenantAction($user, 'notifications.templates.manage');
    }

    public function manageChannels(User $user, mixed $resource = null): bool
    {
        if ($resource !== null) {
            $accountId = $this->resolveResourceAccountId($resource);
            if ($accountId === null) {
                return false;
            }

            return $this->allowsTenantResourceAction($user, 'notifications.channels.manage', $accountId);
        }

        return $this->allowsTenantAction($user, 'notifications.channels.manage');
    }

    public function manageSchedules(User $user, mixed $resource = null): bool
    {
        if ($resource !== null) {
            $accountId = $this->resolveResourceAccountId($resource);
            if ($accountId === null) {
                return false;
            }

            return $this->allowsTenantResourceAction($user, 'notifications.schedules.manage', $accountId);
        }

        return $this->allowsTenantAction($user, 'notifications.schedules.manage');
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
