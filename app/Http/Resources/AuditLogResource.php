<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $action = $this->action ?? $this->event;
        $entityType = $this->entity_type ?? $this->auditable_type;
        $entityId = $this->entity_id ?? ($this->auditable_id !== null ? (string) $this->auditable_id : null);

        return [
            'id'          => $this->id,
            'action'      => $action,
            'severity'    => $this->severity,
            'category'    => $this->category,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_values'  => $this->old_values,
            'new_values'  => $this->new_values,
            'metadata'    => $this->metadata,
            'ip_address'  => $this->ip_address,
            'user_agent'  => $this->user_agent,
            'request_id'  => $this->request_id,
            'performer'   => $this->whenLoaded('performer', fn () => [
                'id'    => $this->performer->id,
                'name'  => $this->performer->name,
                'email' => $this->performer->email,
            ]),
            'performed_by' => $this->user_id,
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}
