<?php

namespace App\Support\Tenancy;

use Illuminate\Http\Request;

class WebTenantContext
{
    public const SESSION_KEY = 'internal_web_current_account_id';

    public static function sessionKey(): string
    {
        return self::SESSION_KEY;
    }

    public static function currentAccountId(Request $request): ?string
    {
        $value = $request->session()->get(self::SESSION_KEY);

        if (! is_scalar($value)) {
            return null;
        }

        $accountId = trim((string) $value);

        return $accountId === '' ? null : $accountId;
    }

    public static function setCurrentAccountId(Request $request, string $accountId): void
    {
        $request->session()->put(self::SESSION_KEY, trim($accountId));
    }

    public static function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
