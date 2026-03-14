<?php

namespace App\Services\Platforms;

use App\Models\Store;

/**
 * PlatformAdapterFactory — Resolves the correct adapter for a store's platform.
 */
class PlatformAdapterFactory
{
    private static array $adapters = [
        'shopify'     => ShopifyAdapter::class,
        'woocommerce' => WooCommerceAdapter::class,
    ];

    public static function make(Store $store): PlatformAdapterInterface
    {
        $platform = $store->platform;
        $adapterClass = self::$adapters[$platform] ?? null;

        if (!$adapterClass) {
            throw new \InvalidArgumentException("No adapter for platform: {$platform}");
        }

        return new $adapterClass();
    }

    public static function supports(string $platform): bool
    {
        return isset(self::$adapters[$platform]);
    }

    public static function supportedPlatforms(): array
    {
        return array_keys(self::$adapters);
    }
}
