<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Limited resource shown to the invitee before accepting.
 * Does NOT expose internal IDs or sensitive data.
 */
class InvitationPreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'email'        => $this->email,
            'name'         => $this->name,
            'status'       => $this->status,
            'is_usable'    => $this->isUsable(),
            'account_name' => $this->account?->name,
            'role_name'    => $this->resolvedRole()?->display_name ?? $this->role_name,
            'expires_at'   => $this->expires_at?->toISOString(),
        ];
    }
}
