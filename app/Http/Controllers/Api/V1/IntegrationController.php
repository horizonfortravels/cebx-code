<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IntegrationHealthLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * CBEX GROUP — Integration Controller
 *
 * Manages external API integrations:
 * Airline APIs, Shipping Line APIs, Customs APIs,
 * Payment Providers, Tracking IoT.
 */
class IntegrationController extends Controller
{
    /**
     * List all configured integrations
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', IntegrationHealthLog::class);

        $integrations = [
            [
                'id' => 'aramex',
                'name' => 'Aramex',
                'type' => 'carrier',
                'category' => 'shipping_line',
                'status' => 'active',
                'capabilities' => ['shipment', 'tracking', 'rates', 'label'],
                'countries' => ['SA', 'AE', 'EG', 'JO'],
                'modes' => ['air', 'land'],
            ],
            [
                'id' => 'smsa',
                'name' => 'SMSA Express',
                'type' => 'carrier',
                'category' => 'shipping_line',
                'status' => 'active',
                'capabilities' => ['shipment', 'tracking', 'rates'],
                'countries' => ['SA'],
                'modes' => ['land'],
            ],
            [
                'id' => 'dhl',
                'name' => 'DHL Express',
                'type' => 'carrier',
                'category' => 'airline',
                'status' => 'active',
                'capabilities' => ['shipment', 'tracking', 'rates', 'label', 'customs'],
                'countries' => ['*'],
                'modes' => ['air'],
            ],
            [
                'id' => 'maersk',
                'name' => 'Maersk Line',
                'type' => 'carrier',
                'category' => 'shipping_line',
                'status' => 'active',
                'capabilities' => ['booking', 'tracking', 'rates', 'container'],
                'countries' => ['*'],
                'modes' => ['sea'],
            ],
            [
                'id' => 'fasah',
                'name' => 'FASAH (الجمارك السعودية)',
                'type' => 'customs',
                'category' => 'customs_api',
                'status' => 'active',
                'capabilities' => ['declaration', 'duty_calculation', 'clearance'],
                'countries' => ['SA'],
                'modes' => ['*'],
            ],
            [
                'id' => 'sadad',
                'name' => 'SADAD',
                'type' => 'payment',
                'category' => 'payment_provider',
                'status' => 'active',
                'capabilities' => ['payment', 'refund'],
                'countries' => ['SA'],
                'modes' => ['*'],
            ],
            [
                'id' => 'hyperPay',
                'name' => 'HyperPay',
                'type' => 'payment',
                'category' => 'payment_provider',
                'status' => 'active',
                'capabilities' => ['payment', 'refund', 'tokenization'],
                'countries' => ['SA', 'AE', 'BH', 'EG'],
                'modes' => ['*'],
            ],
        ];

        return response()->json(['data' => $integrations]);
    }

    /**
     * Get integration health status
     */
    public function health(Request $request): JsonResponse
    {
        $this->authorize('viewAny', IntegrationHealthLog::class);

        $logs = IntegrationHealthLog::where('account_id', $request->user()->account_id)
            ->where('checked_at', '>=', now()->subHours(24))
            ->orderBy('checked_at', 'desc')
            ->get()
            ->groupBy('integration_id');

        $health = [];
        foreach ($logs as $integrationId => $entries) {
            $latest = $entries->first();
            $total = $entries->count();
            $success = $entries->where('status', 'success')->count();

            $health[] = [
                'integration_id' => $integrationId,
                'status' => $latest->status,
                'uptime_percent' => $total > 0 ? round(($success / $total) * 100, 1) : 0,
                'last_check' => $latest->checked_at,
                'avg_response_ms' => round($entries->avg('response_time_ms'), 0),
                'error_count' => $entries->where('status', 'error')->count(),
            ];
        }

        return response()->json(['data' => $health]);
    }

    /**
     * Test integration connectivity
     */
    public function test(Request $request, string $integrationId): JsonResponse
    {
        $this->authorize('manage', IntegrationHealthLog::class);

        // Simulate connectivity test
        $startTime = microtime(true);

        // In production, this would make actual API calls
        $success = true;
        $responseMs = round((microtime(true) - $startTime) * 1000);

        IntegrationHealthLog::create([
            'id' => Str::uuid()->toString(),
            'account_id' => $request->user()->account_id,
            'integration_id' => $integrationId,
            'status' => $success ? 'success' : 'error',
            'response_time_ms' => $responseMs,
            'checked_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'integration_id' => $integrationId,
                'status' => $success ? 'connected' : 'failed',
                'response_time_ms' => $responseMs,
            ],
            'message' => $success ? 'الاتصال ناجح' : 'فشل الاتصال',
        ]);
    }

    /**
     * Get integration logs
     */
    public function logs(Request $request, string $integrationId): JsonResponse
    {
        $this->authorize('view', IntegrationHealthLog::class);

        $logs = IntegrationHealthLog::where('account_id', $request->user()->account_id)
            ->where('integration_id', $integrationId)
            ->orderBy('checked_at', 'desc')
            ->paginate(50);

        return response()->json(['data' => $logs]);
    }

    /**
     * Webhook configuration for integrations
     */
    public function webhookConfig(Request $request): JsonResponse
    {
        $this->authorize('viewAny', IntegrationHealthLog::class);

        $baseUrl = config('app.url') . '/api/v1/webhooks';

        return response()->json(['data' => [
            'webhook_url' => $baseUrl,
            'endpoints' => [
                'carrier_tracking' => "$baseUrl/carrier-tracking",
                'payment_callback' => "$baseUrl/payment-callback",
                'customs_update' => "$baseUrl/customs-update",
                'store_orders' => "$baseUrl/store-orders",
            ],
            'authentication' => 'HMAC-SHA256',
            'retry_policy' => '3 retries with exponential backoff',
        ]]);
    }
}
