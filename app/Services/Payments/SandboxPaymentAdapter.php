<?php

namespace App\Services\Payments;

use App\Services\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Str;

/**
 * SandboxPaymentAdapter — C-2: Payment Gateway Skeleton
 *
 * Default sandbox-only payment adapter.
 * Returns simulated responses for testing/staging.
 * Automatically disabled in production unless explicitly overridden.
 *
 * Does NOT modify existing PaymentService or PaymentGateway model.
 * Those services continue to work as-is. This skeleton is for
 * future activation via the PaymentGatewayFactory.
 *
 * Test card behaviors (simulated):
 *   4111 1111 1111 1111 → success (captured)
 *   4000 0000 0000 0002 → decline
 *   4000 0000 0000 3220 → 3DS required → success
 *   Any other           → success (default)
 */
class SandboxPaymentAdapter implements PaymentGatewayInterface
{
    private string $gatewaySlug;
    private string $gatewayName;

    public function __construct(string $slug = 'sandbox', string $name = 'Sandbox Gateway')
    {
        $this->gatewaySlug = $slug;
        $this->gatewayName = $name;
    }

    public function slug(): string
    {
        return $this->gatewaySlug;
    }

    public function name(): string
    {
        return $this->gatewayName;
    }

    public function isEnabled(): bool
    {
        return (bool) config("features.payment_{$this->gatewaySlug}", false);
    }

    public function isSandbox(): bool
    {
        // This adapter is ALWAYS sandbox — that's its purpose
        return true;
    }

    // ═════════════════════════════════════════════════════════
    // CHARGE — Simulated
    // ═════════════════════════════════════════════════════════

    public function charge(array $params): array
    {
        if (!$this->isEnabled()) {
            return $this->disabledResponse('charge');
        }

        // Block in production unless explicitly allowed
        if ($this->isProduction() && !$this->productionOverride()) {
            return [
                'success' => false,
                'error'   => 'Sandbox adapter cannot be used in production',
                '_sandbox' => true,
            ];
        }

        $amount   = (float) ($params['amount'] ?? 0);
        $currency = $params['currency'] ?? 'SAR';
        $source   = $params['source_token'] ?? '';
        $txnId    = 'SBX_' . strtoupper(Str::random(16));

        // Simulate card-specific behaviors
        $status = $this->simulateCardBehavior($source);

        return [
            'success'          => $status !== 'failed',
            'transaction_id'   => $txnId,
            'status'           => $status,
            'amount'           => $amount,
            'currency'         => $currency,
            'error'            => $status === 'failed' ? 'Card declined (simulated)' : null,
            'gateway_response' => [
                'id'     => $txnId,
                'object' => 'charge',
                'status' => $status,
            ],
            '_sandbox'  => true,
            '_gateway'  => $this->gatewaySlug,
            '_timestamp' => now()->toIso8601String(),
        ];
    }

    // ═════════════════════════════════════════════════════════
    // REFUND — Simulated
    // ═════════════════════════════════════════════════════════

    public function refund(array $params): array
    {
        if (!$this->isEnabled()) {
            return $this->disabledResponse('refund');
        }

        if ($this->isProduction() && !$this->productionOverride()) {
            return [
                'success' => false,
                'error'   => 'Sandbox adapter cannot be used in production',
                '_sandbox' => true,
            ];
        }

        $amount = (float) ($params['amount'] ?? 0);

        return [
            'success'   => true,
            'refund_id' => 'SBX_REF_' . strtoupper(Str::random(12)),
            'amount'    => $amount,
            'status'    => 'refunded',
            'error'     => null,
            '_sandbox'  => true,
        ];
    }

    // ═════════════════════════════════════════════════════════
    // VERIFY — Simulated
    // ═════════════════════════════════════════════════════════

    public function verify(string $transactionId): array
    {
        if (!$this->isEnabled()) {
            return $this->disabledResponse('verify');
        }

        return [
            'success' => true,
            'status'  => 'captured',
            'amount'  => 0, // Would come from real gateway lookup
            'error'   => null,
            '_sandbox' => true,
        ];
    }

    // ═════════════════════════════════════════════════════════
    // CAPABILITIES
    // ═════════════════════════════════════════════════════════

    public function supportedMethods(): array
    {
        return ['card', 'mada', 'apple_pay', 'stcpay', 'bank_transfer'];
    }

    public function supportedCurrencies(): array
    {
        return ['SAR', 'AED', 'USD', 'EUR', 'GBP'];
    }

    // ═════════════════════════════════════════════════════════
    // HELPERS
    // ═════════════════════════════════════════════════════════

    /**
     * Simulate card behavior based on test card numbers.
     */
    private function simulateCardBehavior(string $source): string
    {
        // Strip spaces/dashes from source token
        $clean = preg_replace('/[\s\-]/', '', $source);

        return match (true) {
            str_contains($clean, '4000000000000002') => 'failed',       // Decline
            str_contains($clean, '4000000000003220') => 'captured',     // 3DS → success
            str_contains($clean, '4111111111111111') => 'captured',     // Standard success
            default => 'captured', // Default: success
        };
    }

    private function isProduction(): bool
    {
        return app()->environment('production');
    }

    private function productionOverride(): bool
    {
        // In production, sandbox can only be used if explicitly configured
        return (bool) env('ALLOW_SANDBOX_IN_PRODUCTION', false);
    }

    private function disabledResponse(string $operation): array
    {
        return [
            'success'  => false,
            'error'    => "Gateway [{$this->gatewaySlug}] is disabled via feature flag (operation: {$operation})",
            '_sandbox' => true,
            '_disabled' => true,
        ];
    }
}
