<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnvironmentSafetyGuard — B-2: Environment Safety Guards
 *
 * Prevents real payment execution outside production environment.
 * Simulates external service responses in staging/local/testing.
 * Validates critical ENV variables before allowing sensitive operations.
 *
 * ZERO modification to existing code. Purely additive middleware.
 *
 * Registration (bootstrap/app.php or Kernel.php):
 *   'env_safety' => \App\Http\Middleware\EnvironmentSafetyGuard::class
 *
 * Usage in routes:
 *   Route::post('/payments/charge', ...)->middleware('env_safety');
 *   Route::post('/api/v1/carrier/create', ...)->middleware('env_safety');
 *
 * Or apply to a route group:
 *   Route::middleware(['auth', 'env_safety'])->group(function() { ... });
 */
class EnvironmentSafetyGuard
{
    /**
     * Route patterns that involve real financial transactions.
     * If the current URL matches any of these AND we're NOT in production,
     * the request is blocked or redirected to sandbox.
     */
    private array $paymentPatterns = [
        '*/payments/charge*',
        '*/payments/capture*',
        '*/payments/refund*',
        '*/wallet/topup/confirm*',
        '*/subscription/renew*',
    ];

    /**
     * Route patterns for external carrier API calls.
     */
    private array $carrierPatterns = [
        '*/carrier/create-shipment*',
        '*/carrier/cancel*',
        '*/carrier/label*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $env = app()->environment();

        // ═══ Production: pass through (no guard needed) ═══
        if ($env === 'production') {
            return $this->validateProductionEnv($request, $next);
        }

        // ═══ Non-production: intercept dangerous operations ═══
        $url = $request->fullUrl();

        // Check payment routes
        if ($this->matchesAny($url, $this->paymentPatterns)) {
            return $this->blockOrSimulate($request, 'payment', $env);
        }

        // Check carrier routes
        if ($this->matchesAny($url, $this->carrierPatterns)) {
            return $this->blockOrSimulate($request, 'carrier', $env);
        }

        return $next($request);
    }

    /**
     * In production, verify critical ENV variables are set.
     */
    private function validateProductionEnv(Request $request, Closure $next): Response
    {
        $missing = [];

        // Critical production ENV checks
        $required = [
            'APP_KEY',
            'DB_DATABASE',
        ];

        foreach ($required as $key) {
            if (empty(env($key))) {
                $missing[] = $key;
            }
        }

        // APP_DEBUG must be false in production
        if (config('app.debug') === true) {
            Log::warning('EnvironmentSafetyGuard: APP_DEBUG is true in production!');
        }

        // APP_ENV must actually be "production"
        if (config('app.env') !== 'production') {
            Log::warning('EnvironmentSafetyGuard: APP_ENV mismatch in production');
        }

        if (!empty($missing)) {
            Log::critical('EnvironmentSafetyGuard: Missing critical ENV variables in production', [
                'missing' => $missing,
            ]);
        }

        return $next($request);
    }

    /**
     * Block or simulate the request in non-production environments.
     */
    private function blockOrSimulate(Request $request, string $type, string $env): Response
    {
        $sandboxMode = config('features.sandbox_mode', true);

        Log::info("EnvironmentSafetyGuard: Blocked {$type} operation in [{$env}]", [
            'url'     => $request->path(),
            'method'  => $request->method(),
            'sandbox' => $sandboxMode,
            'user_id' => auth()->id(),
        ]);

        // ── API requests: return simulated response ──────────
        if ($request->expectsJson()) {
            $simulated = $this->simulatedResponse($type);

            return response()->json([
                'status'      => 'simulated',
                'environment' => $env,
                'message'     => "⚠️ {$type} operation simulated — not in production",
                'data'        => $simulated,
                '_sandbox'    => true,
            ], 200);
        }

        // ── Web requests: redirect back with warning ─────────
        $message = match ($type) {
            'payment' => '⚠️ عملية الدفع محاكاة فقط — البيئة ليست Production',
            'carrier' => '⚠️ عملية الناقل محاكاة فقط — البيئة ليست Production',
            default   => '⚠️ العملية محاكاة فقط — البيئة ليست Production',
        };

        return redirect()->back()->with('warning', $message);
    }

    /**
     * Generate simulated responses for external services.
     */
    private function simulatedResponse(string $type): array
    {
        return match ($type) {
            'payment' => [
                'transaction_id' => 'SIM_TXN_' . strtoupper(substr(md5(microtime()), 0, 12)),
                'status'         => 'captured',
                'amount'         => request()->input('amount', 0),
                'currency'       => request()->input('currency', 'SAR'),
                'gateway'        => 'sandbox_simulator',
                'simulated'      => true,
                'simulated_at'   => now()->toIso8601String(),
            ],
            'carrier' => [
                'shipment_id'    => 'SIM_SHP_' . strtoupper(substr(md5(microtime()), 0, 10)),
                'tracking_number'=> 'SIM' . rand(1000000000, 9999999999),
                'status'         => 'created',
                'carrier'        => 'sandbox_carrier',
                'label_url'      => null,
                'simulated'      => true,
                'simulated_at'   => now()->toIso8601String(),
            ],
            default => ['simulated' => true],
        };
    }

    /**
     * Check if URL matches any wildcard pattern.
     */
    private function matchesAny(string $url, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $url)) {
                return true;
            }
        }

        return false;
    }
}
