<?php

namespace App\Services\Platforms;

use App\Models\Store;

/**
 * ShopifyAdapter — Shopify store integration via OAuth.
 *
 * NOTE: Real Shopify API calls are stubbed for now (no external network).
 * In production, these methods call Shopify REST/GraphQL API.
 */
class ShopifyAdapter implements PlatformAdapterInterface
{
    public function testConnection(Store $store): array
    {
        $config = $store->connection_config ?? [];
        if (empty($config['access_token']) || empty($config['shop_domain'])) {
            return ['success' => false, 'store_name' => null, 'error' => 'Missing access_token or shop_domain'];
        }

        // Production: GET /admin/api/2024-01/shop.json
        return [
            'success'    => true,
            'store_name' => $config['shop_domain'],
            'error'      => null,
        ];
    }

    public function registerWebhooks(Store $store): array
    {
        // Production: POST /admin/api/2024-01/webhooks.json for each event
        return [
            'success'             => true,
            'webhooks_registered' => count($this->supportedEvents()),
            'error'               => null,
        ];
    }

    public function fetchOrders(Store $store, array $params = []): array
    {
        // Production: GET /admin/api/2024-01/orders.json with params
        // For now, returns empty (real polling would make HTTP requests)
        return [];
    }

    public function transformOrder(array $rawOrder, Store $store): array
    {
        return [
            'external_order_id'      => (string) ($rawOrder['id'] ?? ''),
            'external_order_number'  => $rawOrder['order_number'] ?? $rawOrder['name'] ?? null,
            'source'                 => 'shopify',
            'customer_name'          => trim(($rawOrder['customer']['first_name'] ?? '') . ' ' . ($rawOrder['customer']['last_name'] ?? '')),
            'customer_email'         => $rawOrder['customer']['email'] ?? null,
            'customer_phone'         => $rawOrder['customer']['phone'] ?? null,
            'shipping_name'          => trim(($rawOrder['shipping_address']['first_name'] ?? '') . ' ' . ($rawOrder['shipping_address']['last_name'] ?? '')),
            'shipping_phone'         => $rawOrder['shipping_address']['phone'] ?? null,
            'shipping_address_line_1' => $rawOrder['shipping_address']['address1'] ?? null,
            'shipping_address_line_2' => $rawOrder['shipping_address']['address2'] ?? null,
            'shipping_city'          => $rawOrder['shipping_address']['city'] ?? null,
            'shipping_state'         => $rawOrder['shipping_address']['province'] ?? null,
            'shipping_postal_code'   => $rawOrder['shipping_address']['zip'] ?? null,
            'shipping_country'       => $rawOrder['shipping_address']['country_code'] ?? null,
            'subtotal'               => (float) ($rawOrder['subtotal_price'] ?? 0),
            'shipping_cost'          => $this->extractShippingCost($rawOrder),
            'tax_amount'             => (float) ($rawOrder['total_tax'] ?? 0),
            'discount_amount'        => (float) ($rawOrder['total_discounts'] ?? 0),
            'total_amount'           => (float) ($rawOrder['total_price'] ?? 0),
            'currency'               => $rawOrder['currency'] ?? 'SAR',
            'items'                  => $this->transformItems($rawOrder['line_items'] ?? []),
            'external_created_at'    => $rawOrder['created_at'] ?? null,
            'external_updated_at'    => $rawOrder['updated_at'] ?? null,
        ];
    }

    public function updateFulfillment(Store $store, string $externalOrderId, string $trackingNumber, string $carrier): array
    {
        // Production: POST /admin/api/2024-01/orders/{id}/fulfillments.json
        return ['success' => true, 'error' => null];
    }

    public function verifyWebhookSignature(string $payload, string $signature, Store $store): bool
    {
        $config = $store->connection_config ?? [];
        $secret = $config['webhook_secret'] ?? $config['client_secret'] ?? '';
        $computed = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        return hash_equals($computed, $signature);
    }

    public function supportedEvents(): array
    {
        return ['orders/create', 'orders/updated', 'orders/cancelled', 'orders/fulfilled'];
    }

    // ─── Private Helpers ─────────────────────────────────────────

    private function extractShippingCost(array $rawOrder): float
    {
        $total = 0;
        foreach ($rawOrder['shipping_lines'] ?? [] as $line) {
            $total += (float) ($line['price'] ?? 0);
        }
        return $total;
    }

    private function transformItems(array $lineItems): array
    {
        return array_map(fn ($item) => [
            'external_item_id'   => (string) ($item['id'] ?? ''),
            'sku'                => $item['sku'] ?? null,
            'name'               => $item['title'] ?? $item['name'] ?? 'Unknown',
            'quantity'           => (int) ($item['quantity'] ?? 1),
            'unit_price'         => (float) ($item['price'] ?? 0),
            'total_price'        => (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1),
            'weight'             => isset($item['grams']) ? $item['grams'] / 1000.0 : null,
            'hs_code'            => null,
            'country_of_origin'  => null,
            'properties'         => $this->transformProperties($item['variant_title'] ?? null, $item['properties'] ?? []),
        ], $lineItems);
    }

    private function transformProperties(?string $variantTitle, array $properties): ?array
    {
        $result = [];
        if ($variantTitle) {
            $result['variant'] = $variantTitle;
        }
        foreach ($properties as $prop) {
            if (!empty($prop['name']) && !empty($prop['value'])) {
                $result[$prop['name']] = $prop['value'];
            }
        }
        return empty($result) ? null : $result;
    }
}
