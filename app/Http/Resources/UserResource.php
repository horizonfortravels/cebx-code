<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $emailVerifiedAt = $this->email_verified_at instanceof \DateTimeInterface
            ? $this->email_verified_at->toISOString()
            : ($this->email_verified_at ?: null);

        $createdAt = $this->created_at instanceof \DateTimeInterface
            ? $this->created_at->toISOString()
            : ($this->created_at ?: null);

        $updatedAt = $this->updated_at instanceof \DateTimeInterface
            ? $this->updated_at->toISOString()
            : ($this->updated_at ?: null);

        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'phone'             => $this->phone,
            'status'            => $this->status,
            'user_type'         => $this->user_type,
            'is_owner'          => $this->is_owner,
            'locale'            => $this->locale,
            'timezone'          => $this->timezone,
            'email_verified_at' => $emailVerifiedAt,
            'created_at'        => $createdAt,
            'updated_at'        => $updatedAt,
        ];
    }
}
