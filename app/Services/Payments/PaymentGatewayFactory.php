<?php

namespace App\Services\Payments;

use App\Services\Contracts\PaymentGatewayInterface;

/**
 * PaymentGatewayFactory — C-2: Payment Gateway Skeleton
 *
 * Resolves the correct PaymentGatewayInterface for a given gateway slug.
 * Currently all gateways resolve to SandboxPaymentAdapter (skeleton-only).
 *
 * When real adapters are implemented:
 *   1. Create class e.g. MoyasarPaymentAdapter implements PaymentGatewayInterface
 *   2. Register in $adapters map below
 *   3. Enable via feature flag: FEATURE_PAYMENT_MOYASAR=true
 *
 * Usage:
 *   $gateway = PaymentGatewayFactory::make('moyasar');
 *   if ($gateway->isEnabled() && !$gateway->isSandbox()) {
 *       $result = $gateway->charge(['amount' => 100, 'currency' => 'SAR', ...]);
 *   }
 *
 * Does NOT replace existing PaymentService or PaymentGateway model.
 */
class PaymentGatewayFactory
{
    /**
     * Map gateway slugs to real adapter classes.
     * Replace SandboxPaymentAdapter with real implementations when ready.
     *
     * Example future state:
     *   'moyasar' => \App\Services\Payments\MoyasarPaymentAdapter::class,
     *   'stripe'  => \App\Services\Payments\StripePaymentAdapter::class,
     */
    private static array $adapters = [
        // All gateways currently use SandboxPaymentAdapter (skeleton)
    ];

    /**
     * Gateway human-readable names.
     */
    private static array $names = [
        'moyasar' => 'Moyasar',
        'stripe'  => 'Stripe',
        'stcpay'  => 'STC Pay',
        'applepay' => 'Apple Pay',
        'mada'    => 'Mada',
        'sadad'   => 'SADAD',
        'paypal'  => 'PayPal',
    ];

    /**
     * Create a payment gateway adapter instance.
     *
     * @param string $slug Gateway slug (moyasar, stripe, stcpay, etc.)
     * @return PaymentGatewayInterface
     */
    public static function make(string $slug): PaymentGatewayInterface
    {
        $slug = strtolower($slug);

        // Check for registered real adapter
        if (isset(self::$adapters[$slug])) {
            $class = self::$adapters[$slug];
            return new $class();
        }

        // Fallback: SandboxPaymentAdapter with gateway-specific name
        $name = self::$names[$slug] ?? ucfirst($slug);

        return new SandboxPaymentAdapter($slug, $name . ' (Sandbox)');
    }

    /**
     * Get all supported gateway slugs.
     */
    public static function supportedGateways(): array
    {
        return array_keys(self::$names);
    }

    /**
     * Get only gateways that are enabled via feature flags.
     */
    public static function enabledGateways(): array
    {
        $enabled = [];

        foreach (self::$names as $slug => $name) {
            if (config("features.payment_{$slug}", false)) {
                $enabled[$slug] = $name;
            }
        }

        return $enabled;
    }

    /**
     * Check if a specific gateway has a real (non-sandbox) adapter registered.
     */
    public static function hasRealAdapter(string $slug): bool
    {
        return isset(self::$adapters[strtolower($slug)]);
    }

    /**
     * Check if ANY gateway has a real adapter (vs all sandbox).
     */
    public static function hasAnyRealAdapter(): bool
    {
        return !empty(self::$adapters);
    }
}
