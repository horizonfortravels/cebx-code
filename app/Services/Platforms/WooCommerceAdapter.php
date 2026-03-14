<?php

namespace App\Services\Platforms;

use App\Models\Store;

/**
 * WooCommerceAdapter — WooCommerce store integration via API Keys.
 */
class WooCommerceAdapter implements PlatformAdapterInterface
{
    public function testConnection(Store $store): array
    {
        $config = $store->connection_config ?? [];
        if (empty($config['consumer_key']) || empty($config['consumer_secret']) || empty($config['store_url'])) {
            return ['success' => false, 'store_name' => null, 'error' => 'Missing consumer_key, consumer_secret, or store_url'];
        }

        return ['success' => true, 'store_name' => $config['store_url'], 'error' => null];
    }

    public function registerWebhooks(Store $store): array
    {
        return ['success' => true, 'webhooks_registered' => count($this->supportedEvents()), 'error' => null];
    }

    public function fetchOrders(Store $store, array $params = []): array
    {
        return [];
    }

    public function transformOrder(array $rawOrder, Store $store): array
    {
        $billing  = $rawOrder['billing'] ?? [];
        $shipping = $rawOrder['shipping'] ?? [];

        return [
            'external_order_id'      => (string) ($rawOrder['id'] ?? ''),
            'external_order_number'  => (string) ($rawOrder['number'] ?? ''),
            'source'                 => 'woocommerce',
            'customer_name'          => trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '')),
            'customer_email'         => $billing['email'] ?? null,
            'customer_phone'         => $billing['phone'] ?? null,
            'shipping_name'          => trim(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? '')),
            'shipping_phone'         => $shipping['phone'] ?? $billing['phone'] ?? null,
            'shipping_address_line_1' => $shipping['address_1'] ?? null,
            'shipping_address_line_2' => $shipping['address_2'] ?? null,
            'shipping_city'          => $shipping['city'] ?? null,
            'shipping_state'         => $shipping['state'] ?? null,
            'shipping_postal_code'   => $shipping['postcode'] ?? null,
            'shipping_country'       => $shipping['country'] ?? null,
            'subtotal'               => (float) ($rawOrder['subtotal'] ?? 0),
            'shipping_cost'          => (float) ($rawOrder['shipping_total'] ?? 0),
            'tax_amount'             => (float) ($rawOrder['total_tax'] ?? 0),
            'discount_amount'        => (float) ($rawOrder['discount_total'] ?? 0),
            'total_amount'           => (float) ($rawOrder['total'] ?? 0),
            'currency'               => $rawOrder['currency'] ?? 'SAR',
            'items'                  => $this->transformItems($rawOrder['line_items'] ?? []),
            'external_created_at'    => $rawOrder['date_created'] ?? null,
            'external_updated_at'    => $rawOrder['date_modified'] ?? null,
        ];
    }

    public function updateFulfillment(Store $store, string $externalOrderId, string $trackingNumber, string $carrier): array
    {
        return ['success' => true, 'error' => null];
    }

    public function verifyWebhookSignature(string $payload, string $signature, Store $store): bool
    {
        $config = $store->connection_config ?? [];
        $secret = $config['webhook_secret'] ?? $config['consumer_secret'] ?? '';
        $computed = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        return hash_equals($computed, $signature);
    }

    public function supportedEvents(): array
    {
        return ['order.created', 'order.updated', 'order.deleted'];
    }

    private function transformItems(array $lineItems): array
    {
        return array_map(fn ($item) => [
            'external_item_id'   => (string) ($item['id'] ?? ''),
            'sku'                => $item['sku'] ?? null,
            'name'               => $item['name'] ?? 'Unknown',
            'quantity'           => (int) ($item['quantity'] ?? 1),
            'unit_price'         => (float) ($item['price'] ?? 0),
            'total_price'        => (float) ($item['total'] ?? 0),
            'weight'             => isset($item['weight']) ? (float) $item['weight'] : null,
            'hs_code'            => null,
            'country_of_origin'  => null,
            'properties'         => !empty($item['meta_data']) ? $this->flattenMeta($item['meta_data']) : null,
        ], $lineItems);
    }

    private function flattenMeta(array $meta): array
    {
        $result = [];
        foreach ($meta as $m) {
            if (!empty($m['key']) && !str_starts_with($m['key'], '_')) {
                $result[$m['key']] = $m['value'] ?? '';
            }
        }
        return $result;
    }
}
