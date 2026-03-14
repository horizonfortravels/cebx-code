<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'type'       => $this->type,
            'status'     => $this->status,
            'slug'       => $this->slug,
            'settings'   => $this->settings,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'users'      => $this->whenLoaded('users', function () {
                return $this->users->map(fn ($user) => [
                    'id'       => $user->id,
                    'name'     => $user->name,
                    'email'    => $user->email,
                    'is_owner' => $user->is_owner,
                    'status'   => $user->status,
                ]);
            }),
        ];
    }
}
