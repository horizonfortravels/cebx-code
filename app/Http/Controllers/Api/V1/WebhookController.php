<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\WebhookEvent;
use App\Services\OrderService;
use App\Services\Platforms\PlatformAdapterFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WebhookController — Receives webhooks from Shopify, WooCommerce, etc.
 *
 * FR-ST-002: Webhook registration & event handling
 * FR-ST-005: Deduplication via external_event_id
 *
 * These endpoints are PUBLIC (no auth middleware) but verified via signature.
 */
class WebhookController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * POST /api/v1/webhooks/{platform}/{storeId}
     */
    public function handle(Request $request, string $platform, string $storeId): JsonResponse
    {
        $store = Store::where('id', $storeId)
            ->where('platform', $platform)
            ->first();

        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        // Verify signature if platform has adapter
        if (PlatformAdapterFactory::supports($platform)) {
            $adapter = PlatformAdapterFactory::make($store);
            $signature = $this->extractSignature($request, $platform);
            $rawPayload = $request->getContent();

            if (!$adapter->verifyWebhookSignature($rawPayload, $signature, $store)) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $payload = $request->all();
        $eventType = $this->extractEventType($request, $platform);
        $externalEventId = $this->extractEventId($request, $platform, $payload);
        $externalResourceId = (string) ($payload['id'] ?? '');

        // FR-ST-005: Dedup — check if event already processed
        if ($externalEventId) {
            $existing = WebhookEvent::where('store_id', $storeId)
                ->where('external_event_id', $externalEventId)
                ->first();

            if ($existing) {
                $existing->markDuplicate();
                return response()->json(['status' => 'duplicate', 'event_id' => $existing->id]);
            }
        }

        // Record the event
        $event = WebhookEvent::create([
            'account_id'           => $store->account_id,
            'store_id'             => $store->id,
            'platform'             => $platform,
            'event_type'           => $eventType,
            'external_event_id'    => $externalEventId,
            'external_resource_id' => $externalResourceId,
            'status'               => WebhookEvent::STATUS_RECEIVED,
            'payload'              => $payload,
        ]);

        // Process synchronously for now (could be queued in production)
        $this->orderService->processWebhookEvent($event);

        return response()->json([
            'status'   => 'accepted',
            'event_id' => $event->id,
        ]);
    }

    // ─── Platform-Specific Extraction ────────────────────────────

    private function extractSignature(Request $request, string $platform): string
    {
        return match ($platform) {
            'shopify'     => $request->header('X-Shopify-Hmac-Sha256', ''),
            'woocommerce' => $request->header('X-WC-Webhook-Signature', ''),
            default       => $request->header('X-Webhook-Signature', ''),
        };
    }

    private function extractEventType(Request $request, string $platform): string
    {
        return match ($platform) {
            'shopify'     => $request->header('X-Shopify-Topic', 'unknown'),
            'woocommerce' => $request->header('X-WC-Webhook-Topic', 'unknown'),
            default       => $request->header('X-Webhook-Event', 'unknown'),
        };
    }

    private function extractEventId(Request $request, string $platform, array $payload): ?string
    {
        return match ($platform) {
            'shopify'     => $request->header('X-Shopify-Webhook-Id'),
            'woocommerce' => $request->header('X-WC-Webhook-Delivery-Id'),
            default       => $request->header('X-Webhook-Id', isset($payload['id']) ? $platform . '-' . $payload['id'] : null),
        };
    }
}
