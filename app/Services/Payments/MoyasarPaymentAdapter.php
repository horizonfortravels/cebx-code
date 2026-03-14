<?php

namespace App\Services\Payments;

use App\Services\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MoyasarPaymentAdapter — D-2: Real Payment Gateway Adapter (Moyasar Pilot)
 *
 * Production-ready Moyasar adapter implementing PaymentGatewayInterface.
 * Uses Moyasar REST API v2 (https://moyasar.com/docs/api).
 *
 * Guards:
 *   - Feature flag: config('features.payment_moyasar') must be true
 *   - Environment: live mode ONLY in production (sandbox everywhere else)
 *   - EnvironmentSafetyGuard middleware on payment routes
 *   - All responses marked _live=true/false
 *
 * Config (config/services.php → moyasar):
 *   MOYASAR_API_KEY       → Live publishable key
 *   MOYASAR_SECRET_KEY    → Live secret key
 *   MOYASAR_TEST_KEY      → Test publishable key (optional, uses live key in sandbox if empty)
 *   MOYASAR_TEST_SECRET   → Test secret key (optional)
 *
 * Registration: Add to PaymentGatewayFactory::$adapters:
 *   'moyasar' => \App\Services\Payments\MoyasarPaymentAdapter::class,
 *
 * Does NOT modify existing PaymentService, PaymentGateway model, or SandboxPaymentAdapter.
 */
class MoyasarPaymentAdapter implements PaymentGatewayInterface
{
    private const API_BASE    = 'https://api.moyasar.com/v1';
    private const SANDBOX_URL = 'https://api.moyasar.com/v1'; // Moyasar uses same URL, key determines mode

    public function slug(): string
    {
        return 'moyasar';
    }

    public function name(): string
    {
        return 'Moyasar';
    }

    public function isEnabled(): bool
    {
        return (bool) config('features.payment_moyasar', false);
    }

    public function isSandbox(): bool
    {
        // Live mode ONLY in production with live flag
        if (app()->environment('production') && env('MOYASAR_LIVE_MODE', false)) {
            return false;
        }

        return true; // Sandbox by default everywhere else
    }

    // ═════════════════════════════════════════════════════════
    // CHARGE
    // ═════════════════════════════════════════════════════════

    public function charge(array $params): array
    {
        if (!$this->guardEnabled('charge')) {
            return $this->disabledResponse('charge');
        }

        $correlationId = Str::uuid()->toString();
        $amount   = (float) ($params['amount'] ?? 0);
        $currency = strtoupper($params['currency'] ?? 'SAR');

        // Moyasar expects amount in halalas (smallest unit)
        $amountInHalalas = (int) round($amount * 100);

        try {
            $body = [
                'amount'      => $amountInHalalas,
                'currency'    => $currency,
                'description' => $params['description'] ?? 'Shipping Gateway Payment',
                'callback_url' => $params['callback_url'] ?? url('/payments/moyasar/callback'),
                'source'      => $this->buildSource($params),
                'metadata'    => array_merge(
                    $params['metadata'] ?? [],
                    ['correlation_id' => $correlationId, 'platform' => 'shipping_gateway']
                ),
            ];

            $this->logIntegration('moyasar.charge.request', $correlationId, [
                'amount'   => $amount,
                'currency' => $currency,
                'sandbox'  => $this->isSandbox(),
            ]);

            $response = Http::timeout(30)
                ->withBasicAuth($this->secretKey(), '')
                ->post(self::API_BASE . '/payments', $body);

            $data = $response->json();

            if (!$response->successful()) {
                $error = $data['message'] ?? $data['errors'][0] ?? 'Moyasar charge failed';
                $this->logIntegration('moyasar.charge.error', $correlationId, [
                    'status' => $response->status(),
                    'error'  => $error,
                ], 'error');

                return [
                    'success'          => false,
                    'transaction_id'   => null,
                    'status'           => 'failed',
                    'amount'           => $amount,
                    'currency'         => $currency,
                    'error'            => $error,
                    'gateway_response' => $data,
                    '_live'            => !$this->isSandbox(),
                    '_correlation_id'  => $correlationId,
                ];
            }

            $status = $this->mapMoyasarStatus($data['status'] ?? '');

            $this->logIntegration('moyasar.charge.success', $correlationId, [
                'transaction_id' => $data['id'] ?? null,
                'status'         => $status,
                'amount'         => $amount,
            ]);

            return [
                'success'          => in_array($status, ['captured', 'authorized']),
                'transaction_id'   => $data['id'] ?? null,
                'status'           => $status,
                'amount'           => $amount,
                'currency'         => $currency,
                'checkout_url'     => $data['source']['transaction_url'] ?? null, // For 3DS redirect
                'error'            => null,
                'gateway_response' => [
                    'id'     => $data['id'] ?? null,
                    'status' => $data['status'] ?? null,
                    'source' => $data['source']['type'] ?? null,
                    'fee'    => isset($data['fee']) ? $data['fee'] / 100 : null,
                ],
                '_live'            => !$this->isSandbox(),
                '_correlation_id'  => $correlationId,
            ];

        } catch (\Throwable $e) {
            $this->logIntegration('moyasar.charge.exception', $correlationId, [
                'error' => $e->getMessage(),
            ], 'error');

            return [
                'success'          => false,
                'transaction_id'   => null,
                'status'           => 'failed',
                'amount'           => $amount,
                'currency'         => $currency,
                'error'            => 'Moyasar API error: ' . $e->getMessage(),
                'gateway_response' => [],
                '_live'            => !$this->isSandbox(),
                '_correlation_id'  => $correlationId,
            ];
        }
    }

    // ═════════════════════════════════════════════════════════
    // REFUND
    // ═════════════════════════════════════════════════════════

    public function refund(array $params): array
    {
        if (!$this->guardEnabled('refund')) {
            return $this->disabledResponse('refund');
        }

        $correlationId = Str::uuid()->toString();
        $transactionId = $params['transaction_id'] ?? '';
        $amount        = (float) ($params['amount'] ?? 0);
        $amountInHalalas = (int) round($amount * 100);

        try {
            $this->logIntegration('moyasar.refund.request', $correlationId, [
                'transaction_id' => $transactionId,
                'amount'         => $amount,
            ]);

            $response = Http::timeout(30)
                ->withBasicAuth($this->secretKey(), '')
                ->post(self::API_BASE . "/payments/{$transactionId}/refund", [
                    'amount' => $amountInHalalas,
                ]);

            $data = $response->json();

            if (!$response->successful()) {
                $error = $data['message'] ?? 'Moyasar refund failed';
                return [
                    'success'   => false,
                    'refund_id' => null,
                    'amount'    => $amount,
                    'error'     => $error,
                    '_live'     => !$this->isSandbox(),
                    '_correlation_id' => $correlationId,
                ];
            }

            $this->logIntegration('moyasar.refund.success', $correlationId, [
                'refund_id' => $data['id'] ?? null,
                'amount'    => $amount,
            ]);

            return [
                'success'   => true,
                'refund_id' => $data['id'] ?? $transactionId . '_refund',
                'amount'    => $amount,
                'status'    => 'refunded',
                'error'     => null,
                '_live'     => !$this->isSandbox(),
                '_correlation_id' => $correlationId,
            ];

        } catch (\Throwable $e) {
            $this->logIntegration('moyasar.refund.exception', $correlationId, [
                'error' => $e->getMessage(),
            ], 'error');

            return [
                'success'   => false,
                'refund_id' => null,
                'amount'    => $amount,
                'error'     => 'Moyasar refund error: ' . $e->getMessage(),
                '_live'     => !$this->isSandbox(),
            ];
        }
    }

    // ═════════════════════════════════════════════════════════
    // VERIFY
    // ═════════════════════════════════════════════════════════

    public function verify(string $transactionId): array
    {
        if (!$this->guardEnabled('verify')) {
            return $this->disabledResponse('verify');
        }

        $correlationId = Str::uuid()->toString();

        try {
            $response = Http::timeout(15)
                ->withBasicAuth($this->secretKey(), '')
                ->get(self::API_BASE . "/payments/{$transactionId}");

            $data = $response->json();

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'status'  => 'unknown',
                    'amount'  => 0,
                    'error'   => $data['message'] ?? 'Verification failed',
                    '_live'   => !$this->isSandbox(),
                ];
            }

            $status = $this->mapMoyasarStatus($data['status'] ?? '');
            $amount = isset($data['amount']) ? $data['amount'] / 100 : 0;

            $this->logIntegration('moyasar.verify.success', $correlationId, [
                'transaction_id' => $transactionId,
                'status'         => $status,
                'amount'         => $amount,
            ]);

            return [
                'success' => true,
                'status'  => $status,
                'amount'  => $amount,
                'error'   => null,
                '_live'   => !$this->isSandbox(),
                '_correlation_id' => $correlationId,
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status'  => 'unknown',
                'amount'  => 0,
                'error'   => 'Verification error: ' . $e->getMessage(),
                '_live'   => !$this->isSandbox(),
            ];
        }
    }

    // ═════════════════════════════════════════════════════════
    // CAPABILITIES
    // ═════════════════════════════════════════════════════════

    public function supportedMethods(): array
    {
        return ['creditcard', 'mada', 'applepay', 'stcpay'];
    }

    public function supportedCurrencies(): array
    {
        return ['SAR', 'AED', 'USD', 'EUR', 'GBP', 'BHD', 'KWD', 'OMR', 'QAR'];
    }

    // ═════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═════════════════════════════════════════════════════════

    private function secretKey(): string
    {
        if ($this->isSandbox()) {
            return env('MOYASAR_TEST_SECRET', config('services.moyasar.secret_key', ''));
        }

        return config('services.moyasar.secret_key', '');
    }

    private function guardEnabled(string $operation): bool
    {
        if (!$this->isEnabled()) {
            Log::channel('integration')->info("Moyasar.{$operation}: blocked — feature flag disabled");
            return false;
        }

        if (empty($this->secretKey())) {
            Log::channel('integration')->warning("Moyasar.{$operation}: blocked — missing secret key");
            return false;
        }

        return true;
    }

    private function buildSource(array $params): array
    {
        $method = $params['payment_method'] ?? 'creditcard';

        return match ($method) {
            'applepay'   => ['type' => 'applepay', 'token' => $params['source_token'] ?? ''],
            'stcpay'     => ['type' => 'stcpay', 'mobile' => $params['mobile'] ?? ''],
            default      => ['type' => 'creditcard', 'token' => $params['source_token'] ?? $params['card_token'] ?? ''],
        };
    }

    private function mapMoyasarStatus(string $status): string
    {
        return match (strtolower($status)) {
            'paid'              => 'captured',
            'authorized'        => 'authorized',
            'initiated'         => 'pending',
            'failed', 'expired' => 'failed',
            'refunded'          => 'refunded',
            'voided'            => 'voided',
            default             => 'pending',
        };
    }

    private function logIntegration(string $event, string $correlationId, array $context, string $level = 'info'): void
    {
        $logData = array_merge($context, [
            'correlation_id' => $correlationId,
            'gateway'        => 'moyasar',
            'sandbox'        => $this->isSandbox(),
            'timestamp'      => now()->toIso8601String(),
        ]);

        try {
            Log::channel('integration')->{$level}($event, $logData);
        } catch (\Throwable) {
            Log::$level($event, $logData);
        }
    }

    private function disabledResponse(string $operation): array
    {
        return [
            'success'  => false,
            'error'    => "Moyasar gateway is disabled (operation: {$operation}). Enable via feature flag.",
            '_live'    => false,
            '_disabled' => true,
        ];
    }
}
