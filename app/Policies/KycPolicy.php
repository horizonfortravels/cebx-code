<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class KycPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'kyc.read');
    }

    public function view(User $user, mixed $resource = null): bool
    {
        $resourceAccountId = $this->resolveResourceAccountId($resource);
        if ($resourceAccountId !== null) {
            return $this->allowsTenantResourceAction($user, 'kyc.read', $resourceAccountId);
        }

        return $this->allowsTenantAction($user, 'kyc.read');
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'kyc.manage');
    }

    public function update(User $user, mixed $resource = null): bool
    {
        $resourceAccountId = $this->resolveResourceAccountId($resource);
        if ($resourceAccountId !== null) {
            return $this->allowsTenantResourceAction($user, 'kyc.manage', $resourceAccountId);
        }

        return $this->allowsTenantAction($user, 'kyc.manage');
    }

    public function review(User $user, mixed $resource = null): bool
    {
        return $this->update($user, $resource);
    }

    public function viewDocuments(User $user, mixed $resource = null): bool
    {
        $resourceAccountId = $this->resolveResourceAccountId($resource);
        if ($resourceAccountId !== null) {
            return $this->allowsTenantResourceAction($user, 'kyc.documents.read', $resourceAccountId);
        }

        return $this->allowsTenantAction($user, 'kyc.documents.read');
    }

    public function manageDocuments(User $user, mixed $resource = null): bool
    {
        $resourceAccountId = $this->resolveResourceAccountId($resource);
        if ($resourceAccountId !== null) {
            return $this->allowsTenantResourceAction($user, 'kyc.documents.manage', $resourceAccountId);
        }

        return $this->allowsTenantAction($user, 'kyc.documents.manage');
    }

    public function audit(User $user, mixed $resource = null): bool
    {
        return $this->view($user, $resource);
    }

    public function exportAudit(User $user, mixed $resource = null): bool
    {
        return $this->update($user, $resource);
    }

    // Backward-compatible aliases for existing calls.
    public function upload(User $user, mixed $resource = null): bool
    {
        return $this->manageDocuments($user, $resource);
    }

    public function submit(User $user, mixed $resource = null): bool
    {
        return $this->update($user, $resource);
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
        } elseif (isset($resource->case?->account_id)) {
            $accountId = $resource->case->account_id;
        } elseif (method_exists($resource, 'case') && method_exists($resource->case(), 'first')) {
            $case = $resource->case()->first();
            $accountId = $case?->account_id;
        }

        $value = trim((string) ($accountId ?? ''));

        return $value === '' ? null : $value;
    }
}
