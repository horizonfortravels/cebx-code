<?php

namespace App\Policies;

use App\Models\ApiKey;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class ApiKeyPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'api_keys.read');
    }

    public function view(User $user, mixed $apiKey): bool
    {
        return $this->allowsApiKeyResource($user, 'api_keys.read', $apiKey);
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'api_keys.manage');
    }

    public function update(User $user, mixed $apiKey): bool
    {
        return $this->allowsApiKeyResource($user, 'api_keys.manage', $apiKey);
    }

    public function delete(User $user, mixed $apiKey): bool
    {
        return $this->allowsApiKeyResource($user, 'api_keys.manage', $apiKey);
    }

    public function revoke(User $user, mixed $apiKey = null): bool
    {
        return $this->allowsApiKeyResource($user, 'api_keys.manage', $apiKey);
    }

    public function rotate(User $user, mixed $apiKey = null): bool
    {
        return $this->allowsApiKeyResource($user, 'api_keys.manage', $apiKey);
    }

    private function allowsApiKeyResource(User $user, string $permission, mixed $apiKey): bool
    {
        if ($apiKey instanceof ApiKey) {
            return $this->allowsTenantResourceAction($user, $permission, $apiKey->account_id);
        }

        if (is_object($apiKey) && property_exists($apiKey, 'account_id')) {
            return $this->allowsTenantResourceAction($user, $permission, $apiKey->account_id);
        }

        return $this->allowsTenantAction($user, $permission);
    }
}
