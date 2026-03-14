<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\StoreSyncLog;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Exceptions\BusinessException;
use App\Services\Platforms\PlatformAdapterFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * OrderService — Manages orders lifecycle, import, and sync.
 *
 * FR-ST-001: Store connection management
 * FR-ST-004: Canonical order transformation
 * FR-ST-005: Deduplication
 * FR-ST-007: Manual order creation + Order→Shipment conversion
 * FR-ST-008: Smart rules evaluation (basic)
 * FR-ST-009: Fulfillment update back to store
 * FR-ST-010: Sync logging with retry tracking
 */
class OrderService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // FR-ST-001: Store Connection
    // ═══════════════════════════════════════════════════════════════

    /**
     * Test store connection and update status.
     */
    public function testStoreConnection(Store $store, User $performer): array
    {
        $this->assertCanManageOrders($performer);

        if (!PlatformAdapterFactory::supports($store->platform)) {
            // Manual and unsupported platforms are always "connected"
            $store->update([
                'connection_status' => 'connected',
                'last_synced_at'    => now(),
            ]);
            return ['success' => true, 'message' => 'Manual store — no connection test needed.'];
        }

        $adapter = PlatformAdapterFactory::make($store);
        $result = $adapter->testConnection($store);

        $store->update([
            'connection_status' => $result['success'] ? 'connected' : 'error',
            'last_synced_at'    => $result['success'] ? now() : $store->last_synced_at,
        ]);

        $this->auditService->info(
            $store->account_id, $performer->id,
            'store.connection_tested', AuditLog::CATEGORY_ACCOUNT,
            'Store', $store->id,
            null,
            ['success' => $result['success'], 'platform' => $store->platform],
            $result['success'] ? null : ['error' => $result['error']]
        );

        return $result;
    }

    /**
     * FR-ST-002: Register webhooks for a store.
     */
    public function registerWebhooks(Store $store, User $performer): array
    {
        $this->assertCanManageOrders($performer);

        if (!PlatformAdapterFactory::supports($store->platform)) {
            return ['success' => true, 'webhooks_registered' => 0, 'message' => 'No webhooks for this platform.'];
        }

        $adapter = PlatformAdapterFactory::make($store);
        $result = $adapter->registerWebhooks($store);

        $this->auditService->info(
            $store->account_id, $performer->id,
            'store.webhooks_registered', AuditLog::CATEGORY_ACCOUNT,
            'Store', $store->id,
            null,
            ['webhooks_registered' => $result['webhooks_registered'] ?? 0]
        );

        return $result;
    }

    // ═══════════════════════════════════════════════════════════════
    // Order Import (FR-ST-003/004/005)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Import a single order from raw platform data (FR-ST-004 + FR-ST-005).
     * Used by both webhook handler and polling.
     */
    public function importOrder(Store $store, array $rawData, string $syncType = 'webhook'): Order
    {
        // Transform to canonical format
        $adapter = PlatformAdapterFactory::make($store);
        $normalized = $adapter->transformOrder($rawData, $store);

        if (empty($normalized['external_order_id'])) {
            throw new BusinessException('Missing external_order_id in order data.', 'ERR_MISSING_REQUIRED_FIELDS', 422);
        }

        // FR-ST-005: Dedup — check if order already exists
        $existing = Order::where('account_id', $store->account_id)
            ->where('store_id', $store->id)
            ->where('external_order_id', $normalized['external_order_id'])
            ->first();

        if ($existing) {
            // Update existing order instead of creating duplicate
            return $this->updateExistingOrder($existing, $normalized);
        }

        return $this->createOrderFromNormalized($store, $normalized);
    }

    /**
     * FR-ST-003: Manual sync trigger (polling).
     */
    public function syncStore(Store $store, User $performer, array $params = []): StoreSyncLog
    {
        $this->assertCanManageOrders($performer);

        if (!PlatformAdapterFactory::supports($store->platform)) {
            throw new BusinessException('Manual stores do not support sync.', 'ERR_SYNC_NOT_SUPPORTED', 422);
        }

        $syncLog = StoreSyncLog::create([
            'account_id' => $store->account_id,
            'store_id'   => $store->id,
            'sync_type'  => StoreSyncLog::SYNC_MANUAL,
            'status'     => StoreSyncLog::STATUS_STARTED,
            'started_at' => now(),
        ]);

        try {
            $adapter = PlatformAdapterFactory::make($store);
            $rawOrders = $adapter->fetchOrders($store, $params);

            $syncLog->update(['orders_found' => count($rawOrders)]);

            $imported = 0;
            $skipped = 0;
            $failed = 0;
            $errors = [];

            foreach ($rawOrders as $rawOrder) {
                try {
                    $normalized = $adapter->transformOrder($rawOrder, $store);

                    // Dedup check
                    $exists = Order::where('account_id', $store->account_id)
                        ->where('store_id', $store->id)
                        ->where('external_order_id', $normalized['external_order_id'])
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    $this->createOrderFromNormalized($store, $normalized);
                    $imported++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = ['order' => $rawOrder['id'] ?? 'unknown', 'error' => $e->getMessage()];
                }
            }

            $syncLog->complete($imported, $skipped, $failed);
            if (!empty($errors)) {
                $syncLog->update(['errors' => $errors]);
            }

            $store->update(['last_synced_at' => now()]);

            $this->auditService->info(
                $store->account_id, $performer->id,
                'store.sync_completed', AuditLog::CATEGORY_ACCOUNT,
                'Store', $store->id,
                null,
                ['imported' => $imported, 'skipped' => $skipped, 'failed' => $failed]
            );
        } catch (\Throwable $e) {
            $syncLog->update([
                'status'       => StoreSyncLog::STATUS_FAILED,
                'errors'       => [['error' => $e->getMessage()]],
                'completed_at' => now(),
            ]);
            throw $e;
        }

        return $syncLog->fresh();
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-ST-007: Manual Order Creation
    // ═══════════════════════════════════════════════════════════════

    /**
     * Create a manual order (not from external platform).
     */
    public function createManualOrder(string $accountId, string $storeId, array $data, User $performer): Order
    {
        $this->assertCanManageOrders($performer);

        $store = Store::where('id', $storeId)
            ->where('account_id', $accountId)
            ->firstOrFail();

        $externalId = $data['external_order_id'] ?? ('MAN-' . Str::upper(Str::random(8)));

        // Dedup check for manual too
        $exists = Order::where('account_id', $accountId)
            ->where('store_id', $storeId)
            ->where('external_order_id', $externalId)
            ->exists();

        if ($exists) {
            throw new BusinessException('طلب بنفس المعرف موجود بالفعل.', 'ERR_DUPLICATE_ORDER', 422);
        }

        return DB::transaction(function () use ($accountId, $storeId, $data, $performer, $externalId) {
            $items = $data['items'] ?? [];
            $subtotal = collect($items)->sum(fn ($i) => ($i['unit_price'] ?? 0) * ($i['quantity'] ?? 1));
            $totalWeight = collect($items)->sum(fn ($i) => ($i['weight'] ?? 0) * ($i['quantity'] ?? 1));

            $order = Order::create([
                'account_id'            => $accountId,
                'store_id'              => $storeId,
                'external_order_id'     => $externalId,
                'external_order_number' => $data['order_number'] ?? null,
                'source'                => Order::SOURCE_MANUAL,
                'status'                => Order::STATUS_PENDING,
                'customer_name'         => $data['customer_name'] ?? null,
                'customer_email'        => $data['customer_email'] ?? null,
                'customer_phone'        => $data['customer_phone'] ?? null,
                'shipping_name'          => $data['shipping_name'] ?? $data['customer_name'] ?? null,
                'shipping_phone'         => $data['shipping_phone'] ?? $data['customer_phone'] ?? null,
                'shipping_address_line_1' => $data['shipping_address_line_1'] ?? null,
                'shipping_address_line_2' => $data['shipping_address_line_2'] ?? null,
                'shipping_city'          => $data['shipping_city'] ?? null,
                'shipping_state'         => $data['shipping_state'] ?? null,
                'shipping_postal_code'   => $data['shipping_postal_code'] ?? null,
                'shipping_country'       => $data['shipping_country'] ?? null,
                'subtotal'              => $subtotal,
                'shipping_cost'         => $data['shipping_cost'] ?? 0,
                'tax_amount'            => $data['tax_amount'] ?? 0,
                'discount_amount'       => $data['discount_amount'] ?? 0,
                'total_amount'          => $subtotal + ($data['shipping_cost'] ?? 0) + ($data['tax_amount'] ?? 0) - ($data['discount_amount'] ?? 0),
                'currency'              => $data['currency'] ?? 'SAR',
                'total_weight'          => $totalWeight,
                'items_count'           => count($items),
                'imported_at'           => now(),
                'imported_by'           => $performer->id,
                'metadata'              => $data['metadata'] ?? null,
            ]);

            foreach ($items as $itemData) {
                OrderItem::create([
                    'order_id'          => $order->id,
                    'external_item_id'  => $itemData['external_item_id'] ?? null,
                    'sku'               => $itemData['sku'] ?? null,
                    'name'              => $itemData['name'] ?? 'Unknown Item',
                    'quantity'          => $itemData['quantity'] ?? 1,
                    'unit_price'        => $itemData['unit_price'] ?? 0,
                    'total_price'       => ($itemData['unit_price'] ?? 0) * ($itemData['quantity'] ?? 1),
                    'weight'            => $itemData['weight'] ?? null,
                    'hs_code'           => $itemData['hs_code'] ?? null,
                    'country_of_origin' => $itemData['country_of_origin'] ?? null,
                    'properties'        => $itemData['properties'] ?? null,
                ]);
            }

            // FR-ST-008: Evaluate basic rules
            $order = $this->evaluateRules($order);

            $this->auditService->info(
                $accountId, $performer->id,
                'order.created', AuditLog::CATEGORY_ACCOUNT,
                'Order', $order->id,
                null,
                ['source' => 'manual', 'items_count' => count($items), 'total' => $order->total_amount]
            );

            return $order->load('items');
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // Order Management
    // ═══════════════════════════════════════════════════════════════

    /**
     * List orders with filters.
     */
    public function listOrders(string $accountId, array $filters = []): array
    {
        $query = Order::where('account_id', $accountId)
            ->with('items', 'store:id,name,platform');

        if (!empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('external_order_number', 'ilike', "%{$search}%")
                  ->orWhere('customer_name', 'ilike', "%{$search}%")
                  ->orWhere('customer_email', 'ilike', "%{$search}%")
                  ->orWhere('external_order_id', 'ilike', "%{$search}%");
            });
        }
        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $orders = $query->orderByDesc('created_at')
            ->limit($filters['limit'] ?? 50)
            ->offset($filters['offset'] ?? 0)
            ->get();

        $total = Order::where('account_id', $accountId)
            ->when(!empty($filters['store_id']), fn ($q) => $q->where('store_id', $filters['store_id']))
            ->when(!empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->count();

        return [
            'orders' => $orders,
            'total'  => $total,
            'limit'  => $filters['limit'] ?? 50,
            'offset' => $filters['offset'] ?? 0,
        ];
    }

    /**
     * Get single order with items.
     */
    public function getOrder(string $accountId, string $orderId): Order
    {
        return Order::where('account_id', $accountId)
            ->where('id', $orderId)
            ->with('items', 'store:id,name,platform')
            ->firstOrFail();
    }

    /**
     * Update order status.
     */
    public function updateOrderStatus(string $accountId, string $orderId, string $newStatus, User $performer, ?string $reason = null): Order
    {
        $order = Order::where('account_id', $accountId)->where('id', $orderId)->firstOrFail();

        $this->validateStatusTransition($order->status, $newStatus);

        $oldStatus = $order->status;
        $updateData = ['status' => $newStatus];

        if ($newStatus === Order::STATUS_ON_HOLD && $reason) {
            $updateData['hold_reason'] = $reason;
        }
        if ($newStatus === Order::STATUS_READY) {
            $updateData['hold_reason'] = null;
        }

        $order->update($updateData);

        $this->auditService->info(
            $accountId, $performer->id,
            'order.status_changed', AuditLog::CATEGORY_ACCOUNT,
            'Order', $order->id,
            ['status' => $oldStatus],
            ['status' => $newStatus, 'reason' => $reason]
        );

        return $order->fresh();
    }

    /**
     * Cancel an order.
     */
    public function cancelOrder(string $accountId, string $orderId, User $performer, ?string $reason = null): Order
    {
        $order = Order::where('account_id', $accountId)->where('id', $orderId)->firstOrFail();

        if ($order->isShipped()) {
            throw new BusinessException('لا يمكن إلغاء طلب تم شحنه.', 'ERR_ORDER_ALREADY_SHIPPED', 422);
        }

        $order->update([
            'status'      => Order::STATUS_CANCELLED,
            'hold_reason' => $reason ?? 'Cancelled by user',
        ]);

        $this->auditService->warning(
            $accountId, $performer->id,
            'order.cancelled', AuditLog::CATEGORY_ACCOUNT,
            'Order', $order->id,
            null, null,
            ['reason' => $reason]
        );

        return $order->fresh();
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-ST-009: Fulfillment Update Back to Store
    // ═══════════════════════════════════════════════════════════════

    /**
     * Update store platform with tracking/fulfillment info.
     */
    public function updateStoreFulfillment(Order $order, string $trackingNumber, string $carrier): array
    {
        $store = $order->store;

        if (!PlatformAdapterFactory::supports($store->platform)) {
            return ['success' => true, 'message' => 'No fulfillment update for manual stores.'];
        }

        $adapter = PlatformAdapterFactory::make($store);
        $result = $adapter->updateFulfillment($store, $order->external_order_id, $trackingNumber, $carrier);

        $this->auditService->info(
            $order->account_id, null,
            'order.fulfillment_sent', AuditLog::CATEGORY_ACCOUNT,
            'Order', $order->id,
            null,
            ['tracking' => $trackingNumber, 'carrier' => $carrier, 'success' => $result['success']]
        );

        return $result;
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-ST-008: Smart Rules Evaluation (Basic)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Evaluate basic rules on an order.
     * Full rules engine can be expanded later.
     */
    public function evaluateRules(Order $order): Order
    {
        $log = [];
        $hold = false;
        $holdReasons = [];

        // Rule 1: Missing shipping address → Hold
        if (empty($order->shipping_address_line_1) || empty($order->shipping_city) || empty($order->shipping_country)) {
            $log[] = ['rule' => 'address_validation', 'result' => 'hold', 'reason' => 'Incomplete shipping address'];
            $hold = true;
            $holdReasons[] = 'عنوان شحن غير مكتمل';
        }

        // Rule 2: High value order threshold (> 5000 SAR) → Hold for review
        if ((float) $order->total_amount > 5000) {
            $log[] = ['rule' => 'high_value_check', 'result' => 'hold', 'reason' => 'Order exceeds 5000 SAR threshold'];
            $hold = true;
            $holdReasons[] = 'طلب بقيمة عالية يحتاج مراجعة';
        }

        // Rule 3: Missing customer phone → Hold
        if (empty($order->shipping_phone) && empty($order->customer_phone)) {
            $log[] = ['rule' => 'phone_required', 'result' => 'hold', 'reason' => 'No phone number'];
            $hold = true;
            $holdReasons[] = 'رقم هاتف مفقود';
        }

        if (!$hold) {
            $log[] = ['rule' => 'all_passed', 'result' => 'ready'];
            $order->update([
                'status'                => Order::STATUS_READY,
                'auto_ship_eligible'    => true,
                'rule_evaluation_log'   => $log,
            ]);
        } else {
            $order->update([
                'status'                => Order::STATUS_ON_HOLD,
                'hold_reason'           => implode('؛ ', $holdReasons),
                'auto_ship_eligible'    => false,
                'rule_evaluation_log'   => $log,
            ]);
        }

        return $order->fresh();
    }

    /**
     * Get order statistics per account.
     */
    public function getOrderStats(string $accountId, ?string $storeId = null): array
    {
        $query = Order::where('account_id', $accountId);
        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $byStatus = $query->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $total = array_sum($byStatus);

        return [
            'total'      => $total,
            'pending'    => $byStatus[Order::STATUS_PENDING] ?? 0,
            'ready'      => $byStatus[Order::STATUS_READY] ?? 0,
            'processing' => $byStatus[Order::STATUS_PROCESSING] ?? 0,
            'shipped'    => $byStatus[Order::STATUS_SHIPPED] ?? 0,
            'delivered'  => $byStatus[Order::STATUS_DELIVERED] ?? 0,
            'cancelled'  => $byStatus[Order::STATUS_CANCELLED] ?? 0,
            'on_hold'    => $byStatus[Order::STATUS_ON_HOLD] ?? 0,
            'failed'     => $byStatus[Order::STATUS_FAILED] ?? 0,
            'by_status'  => $byStatus,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // Webhook Processing (FR-ST-002)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Process incoming webhook event.
     */
    public function processWebhookEvent(WebhookEvent $event): void
    {
        $event->update(['status' => WebhookEvent::STATUS_PROCESSING]);

        try {
            $store = Store::findOrFail($event->store_id);
            $payload = $event->payload ?? [];

            // Route by event type
            if (str_contains($event->event_type, 'create') || str_contains($event->event_type, 'created')) {
                $this->importOrder($store, $payload, 'webhook');
            } elseif (str_contains($event->event_type, 'update') || str_contains($event->event_type, 'updated')) {
                $this->importOrder($store, $payload, 'webhook'); // Upsert via dedup
            } elseif (str_contains($event->event_type, 'cancel') || str_contains($event->event_type, 'deleted')) {
                $this->handleCancelledOrderWebhook($store, $payload);
            }

            $event->markProcessed();
        } catch (\Throwable $e) {
            $event->markFailed($e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Internal Helpers
    // ═══════════════════════════════════════════════════════════════

    private function createOrderFromNormalized(Store $store, array $normalized): Order
    {
        return DB::transaction(function () use ($store, $normalized) {
            $items = $normalized['items'] ?? [];
            unset($normalized['items']);

            $totalWeight = collect($items)->sum(fn ($i) => ($i['weight'] ?? 0) * ($i['quantity'] ?? 1));

            $order = Order::create(array_merge($normalized, [
                'account_id'  => $store->account_id,
                'store_id'    => $store->id,
                'status'      => Order::STATUS_PENDING,
                'total_weight' => $totalWeight,
                'items_count' => count($items),
                'imported_at' => now(),
            ]));

            foreach ($items as $itemData) {
                OrderItem::create(array_merge($itemData, [
                    'order_id' => $order->id,
                ]));
            }

            // Evaluate rules
            $order = $this->evaluateRules($order);

            return $order;
        });
    }

    private function updateExistingOrder(Order $existing, array $normalized): Order
    {
        // Only update if not already shipped/delivered
        if (in_array($existing->status, [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED])) {
            return $existing;
        }

        $updateFields = array_filter([
            'customer_name'         => $normalized['customer_name'] ?? null,
            'customer_email'        => $normalized['customer_email'] ?? null,
            'customer_phone'        => $normalized['customer_phone'] ?? null,
            'shipping_name'         => $normalized['shipping_name'] ?? null,
            'shipping_address_line_1' => $normalized['shipping_address_line_1'] ?? null,
            'shipping_city'         => $normalized['shipping_city'] ?? null,
            'shipping_country'      => $normalized['shipping_country'] ?? null,
            'total_amount'          => $normalized['total_amount'] ?? null,
            'external_updated_at'   => $normalized['external_updated_at'] ?? null,
        ], fn ($v) => $v !== null);

        if (!empty($updateFields)) {
            $existing->update($updateFields);
        }

        return $existing->fresh();
    }

    private function handleCancelledOrderWebhook(Store $store, array $payload): void
    {
        $externalId = (string) ($payload['id'] ?? '');
        if (empty($externalId)) return;

        $order = Order::where('account_id', $store->account_id)
            ->where('store_id', $store->id)
            ->where('external_order_id', $externalId)
            ->first();

        if ($order && !$order->isShipped() && !$order->isCancelled()) {
            $order->update(['status' => Order::STATUS_CANCELLED, 'hold_reason' => 'Cancelled from store']);
        }
    }

    private function validateStatusTransition(string $from, string $to): void
    {
        $allowed = [
            Order::STATUS_PENDING    => [Order::STATUS_READY, Order::STATUS_ON_HOLD, Order::STATUS_CANCELLED, Order::STATUS_FAILED],
            Order::STATUS_READY      => [Order::STATUS_PROCESSING, Order::STATUS_ON_HOLD, Order::STATUS_CANCELLED],
            Order::STATUS_ON_HOLD    => [Order::STATUS_READY, Order::STATUS_CANCELLED],
            Order::STATUS_PROCESSING => [Order::STATUS_SHIPPED, Order::STATUS_FAILED, Order::STATUS_CANCELLED],
            Order::STATUS_SHIPPED    => [Order::STATUS_DELIVERED],
            Order::STATUS_FAILED     => [Order::STATUS_PENDING, Order::STATUS_CANCELLED],
        ];

        if (!in_array($to, $allowed[$from] ?? [])) {
            throw new BusinessException(
                "لا يمكن تغيير الحالة من {$from} إلى {$to}.",
                'ERR_INVALID_STATUS_TRANSITION', 422
            );
        }
    }

    private function assertCanManageOrders(User $user): void
    {
        if (!$user->hasPermission('orders.manage')) {
            $this->auditService->warning(
                $user->account_id, $user->id,
                'order.access_denied', AuditLog::CATEGORY_ACCOUNT
            );
            throw BusinessException::permissionDenied();
        }
    }
}
