<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * AccountScope ensures every query is filtered by the current tenant's account_id.
 * This is the core of the multi-tenancy isolation layer.
 */
class AccountScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $currentAccountId = $this->resolveCurrentAccountId();
        if ($currentAccountId !== null) {
            $builder->where($model->getTable() . '.account_id', $currentAccountId);
            return;
        }

        $user = $this->resolveRequestUser();
        if ($user !== null && $this->resolveUserType($user) === 'external') {
            $builder->whereRaw('1 = 0');
        }
    }

    private function resolveCurrentAccountId(): ?string
    {
        if (!app()->bound('current_account_id')) {
            return null;
        }

        $value = app('current_account_id');
        if (!is_scalar($value)) {
            return null;
        }

        $id = trim((string) $value);
        return $id === '' ? null : $id;
    }

    private function resolveRequestUser(): ?object
    {
        try {
            $request = request();
            if ($request === null) {
                return null;
            }

            $user = $request->user();
            return is_object($user) ? $user : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveUserType(object $user): string
    {
        $userType = strtolower(trim((string) ($user->user_type ?? '')));
        if ($userType === 'internal' || $userType === 'external') {
            return $userType;
        }

        return empty($user->account_id) ? 'internal' : 'external';
    }
}
