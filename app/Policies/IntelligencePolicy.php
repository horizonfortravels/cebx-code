<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use Illuminate\Database\Eloquent\Model;

class IntelligencePolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'intelligence.read');
    }

    public function view(User $user, mixed $resource): bool
    {
        if (!$resource instanceof Model && !is_object($resource)) {
            return false;
        }

        $accountId = null;

        if ($resource instanceof Model) {
            $accountId = $resource->getAttribute('account_id');
        } elseif (isset($resource->account_id)) {
            $accountId = $resource->account_id;
        }

        $value = trim((string) ($accountId ?? ''));
        if ($value === '') {
            return false;
        }

        return $this->allowsTenantResourceAction($user, 'intelligence.read', $value);
    }

    public function manage(User $user): bool
    {
        return $this->allowsTenantAction($user, 'intelligence.manage');
    }
}

