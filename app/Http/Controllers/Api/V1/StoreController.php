<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRequest;
use App\Services\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * StoreController — FR-IAM-009: Multi-Store Management
 *
 * 8 Endpoints: list, show, create, update, delete, set-default, toggle-status, stats
 */
class StoreController extends Controller
{
    public function __construct(
        protected StoreService $service
    ) {}

    /**
     * GET /api/v1/stores
     * List all stores (with optional filters).
     */
    public function index(Request $request): JsonResponse
    {
        $stores = $this->service->listStores(
            $request->user()->account_id,
            $request->only(['status', 'platform', 'search'])
        );

        return response()->json([
            'success' => true,
            'data'    => $stores,
            'meta'    => ['count' => count($stores)],
        ]);
    }

    /**
     * GET /api/v1/stores/{id}
     */
    public function show(Request $request, string $storeId): JsonResponse
    {
        $store = $this->service->getStore($request->user()->account_id, $storeId);

        return response()->json([
            'success' => true,
            'data'    => $store,
        ]);
    }

    /**
     * POST /api/v1/stores
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $store = $this->service->createStore(
            $request->user()->account_id,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء المتجر بنجاح.',
            'data'    => $this->service->getStore($store->account_id, $store->id),
        ], 201);
    }

    /**
     * PUT /api/v1/stores/{id}
     */
    public function update(StoreRequest $request, string $storeId): JsonResponse
    {
        $store = $this->service->updateStore(
            $request->user()->account_id,
            $storeId,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المتجر بنجاح.',
            'data'    => $this->service->getStore($store->account_id, $store->id),
        ]);
    }

    /**
     * DELETE /api/v1/stores/{id}
     */
    public function destroy(Request $request, string $storeId): JsonResponse
    {
        $this->service->deleteStore(
            $request->user()->account_id,
            $storeId,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المتجر.',
        ]);
    }

    /**
     * POST /api/v1/stores/{id}/set-default
     */
    public function setDefault(Request $request, string $storeId): JsonResponse
    {
        $store = $this->service->setDefault(
            $request->user()->account_id,
            $storeId,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تعيين المتجر كافتراضي.',
            'data'    => $this->service->getStore($store->account_id, $store->id),
        ]);
    }

    /**
     * POST /api/v1/stores/{id}/toggle-status
     */
    public function toggleStatus(Request $request, string $storeId): JsonResponse
    {
        $store = $this->service->toggleStatus(
            $request->user()->account_id,
            $storeId,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => $store->isActive() ? 'تم تفعيل المتجر.' : 'تم تعطيل المتجر.',
            'data'    => $this->service->getStore($store->account_id, $store->id),
        ]);
    }

    /**
     * GET /api/v1/stores/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->service->getStoreStats($request->user()->account_id);

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ]);
    }
}
