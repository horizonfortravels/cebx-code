<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CarrierError — FR-CR-004
 *
 * Normalized error model for all carrier API interactions.
 * Maps carrier-specific errors to internal codes with retriable flag.
 */
class CarrierError extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'shipment_id', 'carrier_shipment_id', 'carrier_code',
        'correlation_id', 'operation', 'internal_code',
        'carrier_error_code', 'carrier_error_message', 'internal_message',
        'http_status', 'http_method', 'endpoint_url',
        'is_retriable', 'retry_attempt', 'max_retries', 'next_retry_at',
        'was_resolved', 'resolved_at',
        'request_context', 'response_body',
    ];

    protected $casts = [
        'request_context' => 'array',
        'response_body'   => 'array',
        'is_retriable'    => 'boolean',
        'was_resolved'    => 'boolean',
        'next_retry_at'   => 'datetime',
        'resolved_at'     => 'datetime',
    ];

    // ── Operations ───────────────────────────────────────────
    const OP_CREATE_SHIPMENT = 'create_shipment';
    const OP_FETCH_LABEL     = 'fetch_label';
    const OP_CANCEL          = 'cancel';
    const OP_FETCH_RATES     = 'fetch_rates';
    const OP_TRACK           = 'track';
    const OP_RE_FETCH_LABEL  = 're_fetch_label';

    // ── Internal Error Codes ─────────────────────────────────
    const ERR_NETWORK_TIMEOUT     = 'ERR_CR_NETWORK_TIMEOUT';
    const ERR_NETWORK_ERROR       = 'ERR_CR_NETWORK_ERROR';
    const ERR_RATE_LIMITED        = 'ERR_CR_RATE_LIMITED';
    const ERR_AUTH_FAILED         = 'ERR_CR_AUTH_FAILED';
    const ERR_VALIDATION          = 'ERR_CR_VALIDATION';
    const ERR_ADDRESS_INVALID     = 'ERR_CR_ADDRESS_INVALID';
    const ERR_SERVICE_UNAVAILABLE = 'ERR_CR_SERVICE_UNAVAILABLE';
    const ERR_SHIPMENT_NOT_FOUND  = 'ERR_CR_SHIPMENT_NOT_FOUND';
    const ERR_LABEL_NOT_AVAILABLE = 'ERR_CR_LABEL_NOT_AVAILABLE';
    const ERR_CANCEL_REJECTED     = 'ERR_CR_CANCEL_REJECTED';
    const ERR_CANCEL_WINDOW_CLOSED = 'ERR_CR_CANCEL_WINDOW_CLOSED';
    const ERR_DUPLICATE           = 'ERR_CR_DUPLICATE';
    const ERR_CARRIER_INTERNAL    = 'ERR_CR_CARRIER_INTERNAL';
    const ERR_UNKNOWN             = 'ERR_CR_UNKNOWN';

    // ── Retriable Error Codes ────────────────────────────────
    const RETRIABLE_CODES = [
        self::ERR_NETWORK_TIMEOUT,
        self::ERR_NETWORK_ERROR,
        self::ERR_RATE_LIMITED,
        self::ERR_SERVICE_UNAVAILABLE,
        self::ERR_CARRIER_INTERNAL,
        self::ERR_LABEL_NOT_AVAILABLE,
    ];

    // ── DHL Error Code Mapping ───────────────────────────────
    const DHL_ERROR_MAP = [
        '400'    => self::ERR_VALIDATION,
        '401'    => self::ERR_AUTH_FAILED,
        '403'    => self::ERR_AUTH_FAILED,
        '404'    => self::ERR_SHIPMENT_NOT_FOUND,
        '429'    => self::ERR_RATE_LIMITED,
        '500'    => self::ERR_CARRIER_INTERNAL,
        '502'    => self::ERR_SERVICE_UNAVAILABLE,
        '503'    => self::ERR_SERVICE_UNAVAILABLE,
        '504'    => self::ERR_NETWORK_TIMEOUT,
    ];

    // ── Relationships ────────────────────────────────────────

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function carrierShipment(): BelongsTo
    {
        return $this->belongsTo(CarrierShipment::class);
    }

    // ── Factory Methods ──────────────────────────────────────

    /**
     * Create a normalized error from a DHL API response.
     */
    public static function fromDhlResponse(
        string $operation,
        int $httpStatus,
        ?array $responseBody,
        string $correlationId,
        ?string $shipmentId = null,
        ?string $carrierShipmentId = null,
        array $requestContext = []
    ): self {
        $carrierErrorCode = $responseBody['detail']['code'] ?? $responseBody['code'] ?? (string) $httpStatus;
        $carrierErrorMessage = $responseBody['detail']['message'] ?? $responseBody['message'] ?? 'Unknown carrier error';
        $internalCode = self::mapDhlError($httpStatus, $carrierErrorCode);

        return self::create([
            'shipment_id'          => $shipmentId,
            'carrier_shipment_id'  => $carrierShipmentId,
            'carrier_code'         => 'dhl',
            'correlation_id'       => $correlationId,
            'operation'            => $operation,
            'internal_code'        => $internalCode,
            'carrier_error_code'   => $carrierErrorCode,
            'carrier_error_message' => $carrierErrorMessage,
            'internal_message'     => self::getInternalMessage($internalCode),
            'http_status'          => $httpStatus,
            'http_method'          => $requestContext['method'] ?? 'POST',
            'endpoint_url'         => $requestContext['url'] ?? null,
            'is_retriable'         => in_array($internalCode, self::RETRIABLE_CODES),
            'max_retries'          => 3,
            'request_context'      => $requestContext,
            'response_body'        => $responseBody,
        ]);
    }

    /**
     * Map DHL HTTP status + error code to internal code.
     */
    public static function mapDhlError(int $httpStatus, ?string $carrierCode = null): string
    {
        // Check specific carrier error codes first
        if ($carrierCode) {
            if (str_contains(strtolower($carrierCode), 'address')) {
                return self::ERR_ADDRESS_INVALID;
            }
            if (str_contains(strtolower($carrierCode), 'duplicate')) {
                return self::ERR_DUPLICATE;
            }
        }

        return self::DHL_ERROR_MAP[(string) $httpStatus] ?? self::ERR_UNKNOWN;
    }

    /**
     * Get human-readable internal message.
     */
    public static function getInternalMessage(string $internalCode): string
    {
        return match ($internalCode) {
            self::ERR_NETWORK_TIMEOUT      => 'Connection to carrier timed out',
            self::ERR_NETWORK_ERROR        => 'Network error communicating with carrier',
            self::ERR_RATE_LIMITED         => 'Carrier API rate limit exceeded',
            self::ERR_AUTH_FAILED          => 'Carrier authentication failed',
            self::ERR_VALIDATION           => 'Carrier rejected the request due to validation errors',
            self::ERR_ADDRESS_INVALID      => 'Carrier rejected the address as invalid',
            self::ERR_SERVICE_UNAVAILABLE  => 'Carrier service is temporarily unavailable',
            self::ERR_SHIPMENT_NOT_FOUND   => 'Shipment not found at carrier',
            self::ERR_LABEL_NOT_AVAILABLE  => 'Label not yet available from carrier',
            self::ERR_CANCEL_REJECTED      => 'Carrier rejected the cancellation request',
            self::ERR_CANCEL_WINDOW_CLOSED => 'Cancellation window has expired',
            self::ERR_DUPLICATE            => 'Duplicate request detected by carrier',
            self::ERR_CARRIER_INTERNAL     => 'Internal error at carrier system',
            default                        => 'Unknown carrier error',
        };
    }

    /**
     * Calculate next retry time with exponential backoff.
     */
    public function calculateNextRetry(): ?\DateTimeInterface
    {
        if (!$this->is_retriable || $this->retry_attempt >= $this->max_retries) {
            return null;
        }

        $delaySeconds = (int) (pow(2, $this->retry_attempt) * 30); // 30s, 60s, 120s
        return now()->addSeconds(min($delaySeconds, 600)); // Max 10 min
    }

    /**
     * Mark error as resolved.
     */
    public function markResolved(): void
    {
        $this->update([
            'was_resolved' => true,
            'resolved_at'  => now(),
        ]);
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeUnresolved($query)
    {
        return $query->where('was_resolved', false);
    }

    public function scopeRetriable($query)
    {
        return $query->where('is_retriable', true)
                     ->where('was_resolved', false)
                     ->whereColumn('retry_attempt', '<', 'max_retries');
    }

    public function scopeByOperation($query, string $operation)
    {
        return $query->where('operation', $operation);
    }
}
