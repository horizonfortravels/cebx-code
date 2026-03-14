<?php

namespace App\Services\Contracts;

/**
 * PaymentGatewayInterface — C-2: Payment Gateway Skeleton
 *
 * Unified contract for all payment gateway adapters.
 * Every gateway (Moyasar, Stripe, STC Pay, etc.) implements this interface.
 *
 * SKELETON ONLY — no real API calls.
 * Auto-disabled outside production (via EnvironmentSafetyGuard).
 * Feature flag per gateway: config('features.payment_{slug}')
 */
interface PaymentGatewayInterface
{
    /**
     * Gateway identifier slug (e.g., 'moyasar', 'stripe', 'stcpay').
     */
    public function slug(): string;

    /**
     * Human-readable gateway name.
     */
    public function name(): string;

    /**
     * Check if this gateway is enabled via feature flag.
     */
    public function isEnabled(): bool;

    /**
     * Check if running in sandbox/test mode.
     */
    public function isSandbox(): bool;

    /**
     * Charge a payment.
     *
     * @param array $params:
     *   - amount: float (required)
     *   - currency: string (default SAR)
     *   - payment_method: string (card, apple_pay, stcpay, etc.)
     *   - source_token: string (tokenized card/payment method)
     *   - description: string
     *   - metadata: array
     *   - idempotency_key: string
     * @return array:
     *   - success: bool
     *   - transaction_id: string|null
     *   - status: string (captured|authorized|pending|failed)
     *   - amount: float
     *   - currency: string
     *   - error: string|null
     *   - gateway_response: array
     */
    public function charge(array $params): array;

    /**
     * Refund a previous charge.
     *
     * @param array $params:
     *   - transaction_id: string (original gateway transaction ID)
     *   - amount: float (partial or full)
     *   - reason: string
     *   - idempotency_key: string
     * @return array:
     *   - success: bool
     *   - refund_id: string|null
     *   - amount: float
     *   - error: string|null
     */
    public function refund(array $params): array;

    /**
     * Verify a payment status (e.g., after redirect from 3DS).
     *
     * @param string $transactionId Gateway transaction ID
     * @return array:
     *   - success: bool
     *   - status: string (captured|authorized|pending|failed|refunded)
     *   - amount: float
     *   - error: string|null
     */
    public function verify(string $transactionId): array;

    /**
     * Get supported payment methods for this gateway.
     *
     * @return array e.g., ['card', 'apple_pay', 'mada']
     */
    public function supportedMethods(): array;

    /**
     * Get supported currencies.
     *
     * @return array e.g., ['SAR', 'AED', 'USD']
     */
    public function supportedCurrencies(): array;
}
