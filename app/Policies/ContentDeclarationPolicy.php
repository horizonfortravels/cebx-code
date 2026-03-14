<?php

namespace App\Policies;

use App\Models\ContentDeclaration;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;
use App\Policies\Concerns\ResolvesTenantResourceAccount;

class ContentDeclarationPolicy
{
    use AuthorizesTenantResource;
    use ResolvesTenantResourceAccount;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'content_declarations.read');
    }

    public function view(User $user, ContentDeclaration $declaration): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'content_declarations.read',
            $this->resolveResourceAccountId($declaration)
        );
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'content_declarations.manage');
    }

    public function update(User $user, ContentDeclaration $declaration): bool
    {
        return $this->allowsTenantResourceAction(
            $user,
            'content_declarations.manage',
            $this->resolveResourceAccountId($declaration)
        );
    }

    public function submit(User $user, ContentDeclaration $declaration): bool
    {
        return $this->update($user, $declaration);
    }

    public function review(User $user, ContentDeclaration $declaration): bool
    {
        return $this->update($user, $declaration);
    }

    public function delete(User $user, ContentDeclaration $declaration): bool
    {
        return $this->update($user, $declaration);
    }
}
