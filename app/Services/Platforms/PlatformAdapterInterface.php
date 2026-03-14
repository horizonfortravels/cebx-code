<?php

namespace App\Services\Platforms;

use App\Models\Store;

/**
 * PlatformAdapter — Abstract interface for store platform integrations.
 *
 * FR-ST-001: Store connection
 * FR-ST-002: Webhook registration
 * FR-ST-003: Polling
 * FR-ST-009: Fulfillment/tracking update
 */
interface PlatformAdapterInterface
{
    /**
     * Test connection to store with given credentials.
     * Returns ['success' => bool, 'store_name' => string, 'error' => ?string]
     */
    public function testConnection(Store $store): array;

    /**
     * Register webhooks for order events.
     * Returns ['success' => bool, 'webhooks_registered' => int, 'error' => ?string]
     */
    public function registerWebhooks(Store $store): array;

    /**
     * Fetch orders from platform (for polling/manual sync).
     * Returns array of normalized order data.
     */
    public function fetchOrders(Store $store, array $params = []): array;

    /**
     * Transform raw platform order data into canonical order format.
     */
    public function transformOrder(array $rawOrder, Store $store): array;

    /**
     * Update store with tracking number and fulfillment status.
     */
    public function updateFulfillment(Store $store, string $externalOrderId, string $trackingNumber, string $carrier): array;

    /**
     * Verify webhook signature/authenticity.
     */
    public function verifyWebhookSignature(string $payload, string $signature, Store $store): bool;

    /**
     * Get supported webhook event types.
     */
    public function supportedEvents(): array;
}
