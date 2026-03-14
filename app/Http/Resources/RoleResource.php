<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'display_name' => $this->display_name,
            'description'  => $this->description,
            'is_system'    => $this->is_system,
            'template'     => $this->template,
            'users_count'  => $this->whenCounted('users'),
            'permissions'  => $this->whenLoaded('permissions', function () {
                return $this->permissions->map(fn ($p) => [
                    'key'          => $p->key,
                    'group'        => $p->group,
                    'display_name' => $p->display_name,
                ]);
            }),
            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}
