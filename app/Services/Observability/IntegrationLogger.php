<?php

namespace App\Services\Observability;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * IntegrationLogger — E-1: Integration Observability
 *
 * Dedicated logging service for all external integration calls.
 * Writes to 'integration' log channel (integration.log).
 *
 * Features:
 *   - Correlation ID per operation chain
 *   - Automatic sensitive field redaction
 *   - Structured log format
 *   - Separate channel from application logs
 *   - Request/response timing
 *
 * Does NOT modify existing Logger or Monolog configuration.
 *
 * Usage:
 *   $logger = new IntegrationLogger('aramex', 'createShipment');
 *   $logger->request(['tracking' => '123']);
 *   // ... do API call ...
 *   $logger->response(['success' => true, 'tracking' => 'XYZ']);
 *   // or
 *   $logger->error('API timeout', ['code' => 504]);
 */
class IntegrationLogger
{
    private string $provider;
    private string $operation;
    private string $correlationId;
    private float  $startTime;
    private array  $redactFields;

    public function __construct(string $provider, string $operation, ?string $correlationId = null)
    {
        $this->provider      = $provider;
        $this->operation     = $operation;
        $this->correlationId = $correlationId ?? Str::uuid()->toString();
        $this->startTime     = microtime(true);
        $this->redactFields  = config('integration_logging.redact_fields', [
            'password', 'Password', 'secret', 'secret_key', 'api_key',
            'card_number', 'cvv', 'pin', 'AccountPin', 'source_token', 'card_token', 'token',
        ]);
    }

    /**
     * Get the correlation ID for this operation.
     */
    public function correlationId(): string
    {
        return $this->correlationId;
    }

    /**
     * Log an outgoing request.
     */
    public function request(array $context = []): void
    {
        $this->write('info', 'request', $context);
    }

    /**
     * Log a successful response.
     */
    public function response(array $context = []): void
    {
        $context['duration_ms'] = $this->elapsed();
        $this->write('info', 'response', $context);
    }

    /**
     * Log an error.
     */
    public function error(string $message, array $context = []): void
    {
        $context['duration_ms'] = $this->elapsed();
        $context['error_message'] = $message;
        $this->write('error', 'error', $context);
    }

    /**
     * Log a warning.
     */
    public function warning(string $message, array $context = []): void
    {
        $context['warning_message'] = $message;
        $this->write('warning', 'warning', $context);
    }

    /**
     * Log an event (generic).
     */
    public function event(string $event, array $context = [], string $level = 'info'): void
    {
        $this->write($level, $event, $context);
    }

    // ═════════════════════════════════════════════════════════
    // INTERNAL
    // ═════════════════════════════════════════════════════════

    private function write(string $level, string $event, array $context): void
    {
        $logEntry = [
            'correlation_id' => $this->correlationId,
            'provider'       => $this->provider,
            'operation'      => $this->operation,
            'event'          => $event,
            'timestamp'      => now()->toIso8601String(),
        ];

        // Merge context with redaction
        $logEntry = array_merge($logEntry, $this->redact($context));

        // Truncate large payloads
        $maxSize = config('integration_logging.max_payload_size', 10000);
        $serialized = json_encode($logEntry, JSON_UNESCAPED_UNICODE);
        if (strlen($serialized) > $maxSize) {
            $logEntry['_truncated'] = true;
            $logEntry = array_intersect_key($logEntry, array_flip([
                'correlation_id', 'provider', 'operation', 'event',
                'timestamp', 'duration_ms', 'error_message', '_truncated',
            ]));
        }

        $channel = "{$this->provider}.{$this->operation}.{$event}";

        try {
            Log::channel('integration')->{$level}($channel, $logEntry);
        } catch (\Throwable) {
            // Fallback to default log channel if integration channel not configured
            Log::$level("[integration] {$channel}", $logEntry);
        }
    }

    /**
     * Redact sensitive fields from context.
     */
    private function redact(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $this->redactFields, true)) {
                $result[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $result[$key] = $this->redact($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Get elapsed time in milliseconds since logger creation.
     */
    private function elapsed(): float
    {
        return round((microtime(true) - $this->startTime) * 1000, 2);
    }

    // ═════════════════════════════════════════════════════════
    // STATIC FACTORY
    // ═════════════════════════════════════════════════════════

    /**
     * Create a carrier-specific logger.
     */
    public static function carrier(string $carrierCode, string $operation, ?string $correlationId = null): self
    {
        return new self("carrier.{$carrierCode}", $operation, $correlationId);
    }

    /**
     * Create a payment-specific logger.
     */
    public static function payment(string $gatewaySlug, string $operation, ?string $correlationId = null): self
    {
        return new self("payment.{$gatewaySlug}", $operation, $correlationId);
    }
}
