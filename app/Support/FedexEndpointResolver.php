<?php

namespace App\Support;

final class FedexEndpointResolver
{
    public static function baseUrl(): string
    {
        return rtrim((string) config('services.fedex.base_url'), '/');
    }

    public static function oauthUrl(): string
    {
        return static::baseUrl() . '/oauth/token';
    }
}
