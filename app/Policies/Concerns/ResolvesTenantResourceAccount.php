<?php

namespace App\Policies\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Traversable;

trait ResolvesTenantResourceAccount
{
    protected function resolveResourceAccountId(mixed $resource): ?string
    {
        if ($resource instanceof Collection || $resource instanceof Traversable || is_array($resource)) {
            foreach ($resource as $item) {
                $resolved = $this->resolveResourceAccountId($item);
                if ($resolved !== null) {
                    return $resolved;
                }
            }

            return null;
        }

        if (!is_object($resource)) {
            return null;
        }

        $directAccountId = null;

        if ($resource instanceof Model) {
            $directAccountId = $resource->getAttribute('account_id');
        } elseif (isset($resource->account_id)) {
            $directAccountId = $resource->account_id;
        }

        $normalized = $this->normalizeAccountId($directAccountId);
        if ($normalized !== null) {
            return $normalized;
        }

        foreach ([
            'shipment',
            'claim',
            'declaration',
            'broker',
            'user',
            'staff',
            'container',
            'shipments',
            'assignment',
            'assignments',
            'driver',
            'branch',
            'company',
            'vesselSchedule',
            'vessel',
            'tariffRule',
        ] as $relation) {
            $relatedResource = $this->relatedResource($resource, $relation);
            if ($relatedResource === null) {
                continue;
            }

            $resolved = $this->resolveResourceAccountId($relatedResource);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function normalizeAccountId(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function relatedResource(object $resource, string $relation): mixed
    {
        if ($resource instanceof Model) {
            if (!$resource->relationLoaded($relation) && !method_exists($resource, $relation)) {
                return null;
            }

            try {
                return $resource->{$relation};
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return $resource->{$relation} ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
