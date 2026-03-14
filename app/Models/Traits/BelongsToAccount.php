<?php

namespace App\Models\Traits;

use App\Models\Scopes\AccountScope;

/**
 * Trait BelongsToAccount
 *
 * Apply this trait to any model that must be isolated per tenant.
 * It automatically adds a global scope filtering by account_id.
 */
trait BelongsToAccount
{
    public static function bootBelongsToAccount(): void
    {
        static::addGlobalScope(new AccountScope());

        // Auto-assign account_id on creation
        static::creating(function ($model) {
            $currentAccountId = self::resolveCurrentAccountId();
            if ($currentAccountId !== null && empty($model->account_id)) {
                $model->account_id = $currentAccountId;
            }
        });
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
