<?php

namespace App\Services\Carriers;

use App\Services\Contracts\CarrierInterface;

/**
 * CarrierAdapterFactory — C-1: Carrier Integration Skeleton
 *
 * Resolves the correct CarrierInterface implementation for a given carrier code.
 * Currently all carriers resolve to DummyCarrierAdapter (skeleton-only).
 *
 * When real adapters are implemented:
 *   1. Create class e.g. DhlCarrierAdapter implements CarrierInterface
 *   2. Register it in the $adapters map below
 *   3. Enable via feature flag: FEATURE_CARRIER_DHL=true
 *
 * Usage:
 *   $adapter = CarrierAdapterFactory::make('dhl');
 *   if ($adapter->isEnabled()) {
 *       $result = $adapter->createShipment($payload);
 *   }
 *
 * Does NOT replace existing CarrierService / CarrierRateAdapter.
 * This factory is for future use once real adapters are implemented.
 */
class CarrierAdapterFactory
{
    /**
     * Map carrier codes to their adapter classes.
     * Replace DummyCarrierAdapter with real implementations when ready.
     *
     * Example future state:
     *   'dhl'    => \App\Services\Carriers\DhlCarrierAdapter::class,
     *   'aramex' => \App\Services\Carriers\AramexCarrierAdapter::class,
     */
    private static array $adapters = [
        // All carriers currently use DummyCarrierAdapter (skeleton)
        // Real adapters will be registered here as they are built
    ];

    /**
     * Carrier human-readable names.
     */
    private static array $names = [
        'dhl'    => 'DHL Express',
        'aramex' => 'Aramex',
        'smsa'   => 'SMSA Express',
        'fedex'  => 'FedEx',
        'ups'    => 'UPS',
        'spl'    => 'البريد السعودي SPL',
        'naqel'  => 'ناقل Naqel',
        'zajil'  => 'زاجل Zajil',
        'imile'  => 'iMile',
        'jandt'  => 'J&T Express',
    ];

    /**
     * Create a carrier adapter instance.
     *
     * @param string $code Carrier code (dhl, aramex, smsa, etc.)
     * @return CarrierInterface
     */
    public static function make(string $code): CarrierInterface
    {
        $code = strtolower($code);

        // Check for registered real adapter
        if (isset(self::$adapters[$code])) {
            $class = self::$adapters[$code];
            return new $class();
        }

        // Fallback: DummyCarrierAdapter with carrier-specific name
        $name = self::$names[$code] ?? ucfirst($code);

        return new DummyCarrierAdapter($code, $name);
    }

    /**
     * Get all supported carrier codes.
     */
    public static function supportedCarriers(): array
    {
        return array_keys(self::$names);
    }

    /**
     * Get only carriers that are enabled via feature flags.
     */
    public static function enabledCarriers(): array
    {
        $enabled = [];

        foreach (self::$names as $code => $name) {
            if (config("features.carrier_{$code}", false)) {
                $enabled[$code] = $name;
            }
        }

        return $enabled;
    }

    /**
     * Check if a specific carrier has a real (non-dummy) adapter registered.
     */
    public static function hasRealAdapter(string $code): bool
    {
        return isset(self::$adapters[strtolower($code)]);
    }
}
