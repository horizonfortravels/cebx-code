<?php

namespace App\Services\Observability;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CircuitBreaker — E-2: Failure Containment
 *
 * Simple in-memory circuit breaker for external integrations.
 * Auto-disables a service after N consecutive failures.
 * Logs the reason for disabling.
 *
 * States:
 *   CLOSED  → Normal operation (requests pass through)
 *   OPEN    → Tripped (requests blocked, returns failure immediately)
 *   HALF    → Testing recovery (1 request allowed through)
 *
 * Does NOT modify any existing code or feature flags file.
 * Works independently via Cache (in-memory by default).
 *
 * Usage:
 *   $cb = new CircuitBreaker('aramex');
 *
 *   if (!$cb->isAvailable()) {
 *       return ['error' => 'Service temporarily unavailable'];
 *   }
 *
 *   try {
 *       $result = $api->call(...);
 *       $cb->recordSuccess();
 *       return $result;
 *   } catch (\Exception $e) {
 *       $cb->recordFailure($e->getMessage());
 *       throw $e;
 *   }
 *
 * Dashboard check:
 *   CircuitBreaker::status('aramex')  → ['state' => 'closed', 'failures' => 0, ...]
 *   CircuitBreaker::allStatuses()     → ['aramex' => [...], 'moyasar' => [...]]
 */
class CircuitBreaker
{
    private const STATE_CLOSED  = 'closed';
    private const STATE_OPEN    = 'open';
    private const STATE_HALF    = 'half_open';

    private string $service;
    private int    $threshold;
    private int    $recoveryTime; // seconds
    private string $cachePrefix;

    /**
     * All registered circuit breaker services (for allStatuses).
     */
    private static array $registeredServices = [
        'aramex', 'dhl', 'smsa', 'fedex',
        'moyasar', 'stripe', 'stcpay',
    ];

    /**
     * @param string $service     Service identifier (e.g., 'aramex', 'moyasar')
     * @param int    $threshold   Number of consecutive failures before tripping (default: 3)
     * @param int    $recoveryTime Seconds to wait before allowing a test request (default: 300 = 5 min)
     */
    public function __construct(string $service, int $threshold = 3, int $recoveryTime = 300)
    {
        $this->service      = $service;
        $this->threshold    = $threshold;
        $this->recoveryTime = $recoveryTime;
        $this->cachePrefix  = "circuit_breaker:{$service}";
    }

    // ═════════════════════════════════════════════════════════
    // PUBLIC API
    // ═════════════════════════════════════════════════════════

    /**
     * Check if the service is available (circuit is closed or half-open).
     */
    public function isAvailable(): bool
    {
        $state = $this->getState();

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_OPEN) {
            // Check if recovery time has passed → transition to half-open
            $trippedAt = Cache::get("{$this->cachePrefix}:tripped_at", 0);
            if ((time() - $trippedAt) >= $this->recoveryTime) {
                $this->setState(self::STATE_HALF);
                $this->logStateChange(self::STATE_HALF, 'Recovery time elapsed, allowing test request');
                return true;
            }

            return false;
        }

        // Half-open: allow one test request
        return true;
    }

    /**
     * Record a successful call → reset failure count.
     */
    public function recordSuccess(): void
    {
        $previousState = $this->getState();

        Cache::put("{$this->cachePrefix}:failures", 0, now()->addHours(24));
        $this->setState(self::STATE_CLOSED);

        if ($previousState !== self::STATE_CLOSED) {
            $this->logStateChange(self::STATE_CLOSED, 'Service recovered after successful request');
        }
    }

    /**
     * Record a failed call → increment failures, trip if threshold reached.
     */
    public function recordFailure(string $reason = ''): void
    {
        $failures = (int) Cache::get("{$this->cachePrefix}:failures", 0);
        $failures++;

        Cache::put("{$this->cachePrefix}:failures", $failures, now()->addHours(24));
        Cache::put("{$this->cachePrefix}:last_failure", $reason, now()->addHours(24));
        Cache::put("{$this->cachePrefix}:last_failure_at", time(), now()->addHours(24));

        if ($failures >= $this->threshold) {
            $this->trip($reason, $failures);
        }
    }

    /**
     * Manually reset the circuit breaker.
     */
    public function reset(): void
    {
        Cache::forget("{$this->cachePrefix}:failures");
        Cache::forget("{$this->cachePrefix}:state");
        Cache::forget("{$this->cachePrefix}:tripped_at");
        Cache::forget("{$this->cachePrefix}:trip_reason");
        Cache::forget("{$this->cachePrefix}:last_failure");
        Cache::forget("{$this->cachePrefix}:last_failure_at");

        $this->logStateChange(self::STATE_CLOSED, 'Manual reset');
    }

    /**
     * Get current status of this circuit breaker.
     */
    public function status(): array
    {
        return [
            'service'         => $this->service,
            'state'           => $this->getState(),
            'failures'        => (int) Cache::get("{$this->cachePrefix}:failures", 0),
            'threshold'       => $this->threshold,
            'recovery_seconds'=> $this->recoveryTime,
            'tripped_at'      => Cache::get("{$this->cachePrefix}:tripped_at"),
            'trip_reason'     => Cache::get("{$this->cachePrefix}:trip_reason"),
            'last_failure'    => Cache::get("{$this->cachePrefix}:last_failure"),
            'last_failure_at' => Cache::get("{$this->cachePrefix}:last_failure_at"),
        ];
    }

    // ═════════════════════════════════════════════════════════
    // STATIC METHODS
    // ═════════════════════════════════════════════════════════

    /**
     * Get status of a specific service.
     */
    public static function statusOf(string $service): array
    {
        return (new self($service))->status();
    }

    /**
     * Get statuses of all registered services.
     */
    public static function allStatuses(): array
    {
        $statuses = [];

        foreach (self::$registeredServices as $service) {
            $statuses[$service] = (new self($service))->status();
        }

        return $statuses;
    }

    /**
     * Reset all circuit breakers.
     */
    public static function resetAll(): void
    {
        foreach (self::$registeredServices as $service) {
            (new self($service))->reset();
        }
    }

    // ═════════════════════════════════════════════════════════
    // PRIVATE METHODS
    // ═════════════════════════════════════════════════════════

    /**
     * Trip the circuit breaker (transition to OPEN).
     */
    private function trip(string $reason, int $failures): void
    {
        $this->setState(self::STATE_OPEN);
        Cache::put("{$this->cachePrefix}:tripped_at", time(), now()->addHours(24));
        Cache::put("{$this->cachePrefix}:trip_reason", $reason, now()->addHours(24));

        $this->logStateChange(self::STATE_OPEN, "Tripped after {$failures} consecutive failures: {$reason}");

        // Log critical alert
        Log::critical("CircuitBreaker TRIPPED for [{$this->service}]", [
            'service'    => $this->service,
            'failures'   => $failures,
            'threshold'  => $this->threshold,
            'reason'     => substr($reason, 0, 500),
            'recovery_in'=> $this->recoveryTime . 's',
        ]);
    }

    private function getState(): string
    {
        return Cache::get("{$this->cachePrefix}:state", self::STATE_CLOSED);
    }

    private function setState(string $state): void
    {
        Cache::put("{$this->cachePrefix}:state", $state, now()->addHours(24));
    }

    private function logStateChange(string $newState, string $reason): void
    {
        try {
            Log::channel('integration')->info("CircuitBreaker.{$this->service}.state_change", [
                'service'   => $this->service,
                'new_state' => $newState,
                'reason'    => $reason,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Throwable) {
            Log::info("CircuitBreaker.{$this->service} → {$newState}: {$reason}");
        }
    }
}
