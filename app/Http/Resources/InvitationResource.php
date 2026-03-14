<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'email'       => $this->email,
            'name'        => $this->name,
            'status'      => $this->status,
            'is_expired'  => $this->isExpired(),
            'is_usable'   => $this->isUsable(),
            'role'        => $this->when($this->resolvedRole(), fn () => [
                'id'           => $this->resolvedRole()?->id,
                'display_name' => $this->resolvedRole()?->display_name,
            ]),
            'invited_by'  => $this->when($this->resolvedInviter(), fn () => [
                'id'   => $this->resolvedInviter()?->id,
                'name' => $this->resolvedInviter()?->name,
            ]),
            'accepted_by' => $this->when($this->accepted_by, fn () => [
                'id' => $this->accepted_by,
            ]),
            'expires_at'   => $this->expires_at?->toISOString(),
            'accepted_at'  => $this->accepted_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'last_sent_at' => $this->last_sent_at?->toISOString(),
            'send_count'   => $this->send_count,
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}
