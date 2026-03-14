<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEvent;
use App\Policies\Concerns\AuthorizesTenantResource;

class WebhookEventPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'webhooks.read');
    }

    public function view(User $user, mixed $event): bool
    {
        return $this->allowsWebhookResource($user, 'webhooks.read', $event);
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'webhooks.manage');
    }

    public function update(User $user, mixed $event): bool
    {
        return $this->allowsWebhookResource($user, 'webhooks.manage', $event);
    }

    public function delete(User $user, mixed $event): bool
    {
        return $this->allowsWebhookResource($user, 'webhooks.manage', $event);
    }

    private function allowsWebhookResource(User $user, string $permission, mixed $event): bool
    {
        if ($event instanceof WebhookEvent) {
            return $this->allowsTenantResourceAction($user, $permission, $event->account_id);
        }

        if (is_object($event) && property_exists($event, 'account_id')) {
            return $this->allowsTenantResourceAction($user, $permission, $event->account_id);
        }

        return $this->allowsTenantAction($user, $permission);
    }
}
