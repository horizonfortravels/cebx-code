<?php

namespace App\Support\Tenancy;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use LogicException;

class InternalTenantQuery
{
    public static function forPlatformModel(string $modelClass, ?Authenticatable $actor = null): Builder
    {
        self::assertInternalActor(self::resolveActor($actor));
        self::instantiateModel($modelClass);

        return $modelClass::withoutGlobalScopes();
    }

    public static function forTenantModel(string $modelClass, ?Authenticatable $actor = null): Builder
    {
        self::assertInternalActor(self::resolveActor($actor));

        $accountId = self::resolveCurrentAccountId();
        if ($accountId === null) {
            throw new AuthorizationException('ERR_TENANT_CONTEXT_REQUIRED: Tenant context is required for internal tenant queries.');
        }

        $model = self::instantiateModel($modelClass);
        $table = $model->getTable();

        if (!Schema::hasColumn($table, 'account_id')) {
            throw new LogicException("Model [$modelClass] does not expose tenant column [$table.account_id].");
        }

        return $modelClass::withoutGlobalScopes()->where($table . '.account_id', $accountId);
    }

    private static function instantiateModel(string $modelClass): Model
    {
        if (!class_exists($modelClass)) {
            throw new LogicException("Model class [$modelClass] was not found.");
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            throw new LogicException("[$modelClass] is not an Eloquent model.");
        }

        return new $modelClass();
    }

    private static function resolveActor(?Authenticatable $actor): ?Authenticatable
    {
        if ($actor instanceof Authenticatable) {
            return $actor;
        }

        try {
            $request = request();
            if ($request === null) {
                return null;
            }

            $requestUser = $request->user();
            return $requestUser instanceof Authenticatable ? $requestUser : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function assertInternalActor(?Authenticatable $actor): void
    {
        if (!$actor || self::resolveUserType($actor) !== 'internal') {
            throw new AuthorizationException('ERR_USER_TYPE_FORBIDDEN: Internal actor is required for this query.');
        }
    }

    private static function resolveUserType(object $user): string
    {
        $userType = strtolower(trim((string) ($user->user_type ?? '')));
        if ($userType === 'internal' || $userType === 'external') {
            return $userType;
        }

        return empty($user->account_id) ? 'internal' : 'external';
    }

    private static function resolveCurrentAccountId(): ?string
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
}
