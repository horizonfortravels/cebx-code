<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $accountId = $this->currentAccountId();
        $this->authorize('viewAny', Order::class);

        $filters = $request->only(['store_id', 'status', 'source', 'search', 'from', 'to', 'limit', 'offset']);

        $result = $this->service->listOrders($accountId, $filters);

        return response()->json([
            'success' => true,
            'data' => $result['orders'],
            'meta' => [
                'total' => $result['total'],
                'limit' => $result['limit'],
                'offset' => $result['offset'],
            ],
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $accountId = $this->currentAccountId();
        $this->authorize('viewAny', Order::class);

        $stats = $this->service->getOrderStats($accountId, $request->query('store_id'));

        return response()->json(['success' => true, 'data' => $stats]);
    }

    public function show(Request $request, string $orderId): JsonResponse
    {
        $accountId = $this->currentAccountId();
        $order = $this->findOrderForCurrentTenant($orderId);

        $this->authorize('view', $order);

        $order = $this->service->getOrder($accountId, $orderId);

        return response()->json(['success' => true, 'data' => $order]);
    }

    public function store(Request $request): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $request->validate([
            'store_id' => 'required|uuid',
            'customer_name' => 'required|string|max:200',
            'customer_email' => 'sometimes|email|max:255',
            'customer_phone' => 'sometimes|string|max:30',
            'shipping_name' => 'sometimes|string|max:200',
            'shipping_phone' => 'sometimes|string|max:30',
            'shipping_address_line_1' => 'required|string|max:300',
            'shipping_city' => 'required|string|max:100',
            'shipping_country' => 'required|string|size:2',
            'shipping_postal_code' => 'sometimes|string|max:20',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:300',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.weight' => 'sometimes|numeric|min:0',
            'items.*.sku' => 'sometimes|string|max:100',
            'currency' => 'sometimes|string|size:3',
        ]);

        $this->authorize('create', Order::class);

        $order = $this->service->createManualOrder(
            $accountId,
            $request->store_id,
            $request->all(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully.',
            'data' => $order,
        ], 201);
    }

    public function updateStatus(Request $request, string $orderId): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $request->validate([
            'status' => 'required|in:pending,ready,processing,shipped,delivered,cancelled,on_hold,failed',
            'reason' => 'sometimes|string|max:500',
        ]);

        $order = $this->findOrderForCurrentTenant($orderId);
        $this->authorize('updateStatus', $order);

        $order = $this->service->updateOrderStatus(
            $accountId,
            $orderId,
            $request->status,
            $request->user(),
            $request->reason
        );

        return response()->json([
            'success' => true,
            'message' => 'Order status updated.',
            'data' => $order,
        ]);
    }

    public function cancel(Request $request, string $orderId): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $request->validate(['reason' => 'sometimes|string|max:500']);

        $order = $this->findOrderForCurrentTenant($orderId);
        $this->authorize('cancel', $order);

        $order = $this->service->cancelOrder(
            $accountId,
            $orderId,
            $request->user(),
            $request->reason
        );

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled.',
            'data' => $order,
        ]);
    }

    public function testConnection(Request $request, string $storeId): JsonResponse
    {
        $store = Store::where('id', $storeId)
            ->where('account_id', $this->currentAccountId())
            ->firstOrFail();

        $result = $this->service->testStoreConnection($store, $request->user());

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function registerWebhooks(Request $request, string $storeId): JsonResponse
    {
        $store = Store::where('id', $storeId)
            ->where('account_id', $this->currentAccountId())
            ->firstOrFail();

        $result = $this->service->registerWebhooks($store, $request->user());

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function syncStore(Request $request, string $storeId): JsonResponse
    {
        $store = Store::where('id', $storeId)
            ->where('account_id', $this->currentAccountId())
            ->firstOrFail();

        $syncLog = $this->service->syncStore($store, $request->user(), $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Sync completed.',
            'data' => $syncLog,
        ]);
    }

    private function currentAccountId(): string
    {
        $accountId = app()->bound('current_account_id')
            ? trim((string) app('current_account_id'))
            : '';

        if ($accountId !== '') {
            return $accountId;
        }

        return trim((string) request()->user()?->account_id);
    }

    private function findOrderForCurrentTenant(string $orderId): Order
    {
        return Order::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $orderId)
            ->firstOrFail();
    }
}
