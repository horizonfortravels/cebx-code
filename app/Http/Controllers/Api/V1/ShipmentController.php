<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function __construct(protected ShipmentService $shipmentService) {}

    public function store(Request $request): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $data = $request->validate([
            'store_id' => 'nullable|uuid',
            'sender_name' => 'required|string|max:200',
            'sender_company' => 'nullable|string|max:200',
            'sender_phone' => 'required|string|max:30',
            'sender_email' => 'nullable|email|max:255',
            'sender_address_1' => 'required|string|max:300',
            'sender_address_2' => 'nullable|string|max:300',
            'sender_city' => 'required|string|max:100',
            'sender_state' => 'nullable|string|max:100',
            'sender_postal_code' => 'nullable|string|max:20',
            'sender_country' => 'required|string|size:2',
            'sender_address_id' => 'nullable|uuid',
            'recipient_name' => 'required|string|max:200',
            'recipient_company' => 'nullable|string|max:200',
            'recipient_phone' => 'required|string|max:30',
            'recipient_email' => 'nullable|email|max:255',
            'recipient_address_1' => 'required|string|max:300',
            'recipient_address_2' => 'nullable|string|max:300',
            'recipient_city' => 'required|string|max:100',
            'recipient_state' => 'nullable|string|max:100',
            'recipient_postal_code' => 'nullable|string|max:20',
            'recipient_country' => 'required|string|size:2',
            'recipient_address_id' => 'nullable|uuid',
            'cod_amount' => 'nullable|numeric|min:0',
            'insurance_amount' => 'nullable|numeric|min:0',
            'is_return' => 'nullable|boolean',
            'has_dangerous_goods' => 'nullable|boolean',
            'delivery_instructions' => 'nullable|string|max:500',
            'parcels' => 'required|array|min:1|max:50',
            'parcels.*.weight' => 'required|numeric|min:0.01|max:999',
            'parcels.*.length' => 'nullable|numeric|min:0.1|max:999',
            'parcels.*.width' => 'nullable|numeric|min:0.1|max:999',
            'parcels.*.height' => 'nullable|numeric|min:0.1|max:999',
            'parcels.*.packaging_type' => 'nullable|string|in:box,envelope,tube,custom',
            'parcels.*.description' => 'nullable|string|max:300',
            'metadata' => 'nullable|array',
        ]);

        $this->authorize('create', Shipment::class);

        $shipment = $this->shipmentService->createDirect($accountId, $data, $request->user());

        return response()->json(['data' => $shipment], 201);
    }

    public function createFromOrder(Request $request, string $orderId): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $overrides = $request->validate([
            'sender_name' => 'nullable|string|max:200',
            'sender_phone' => 'nullable|string|max:30',
            'sender_address_1' => 'nullable|string|max:300',
            'sender_city' => 'nullable|string|max:100',
            'sender_country' => 'nullable|string|size:2',
            'parcels' => 'nullable|array|min:1',
            'parcels.*.weight' => 'required_with:parcels|numeric|min:0.01',
            'parcels.*.length' => 'nullable|numeric|min:0.1',
            'parcels.*.width' => 'nullable|numeric|min:0.1',
            'parcels.*.height' => 'nullable|numeric|min:0.1',
        ]);

        Order::query()
            ->where('account_id', $accountId)
            ->where('id', $orderId)
            ->firstOrFail();

        $this->authorize('create', Shipment::class);

        $shipment = $this->shipmentService->createFromOrder($accountId, $orderId, $overrides, $request->user());

        return response()->json(['data' => $shipment], 201);
    }

    public function validate(Request $request, string $shipmentId): JsonResponse
    {
        $accountId = $this->currentAccountId();
        $shipment = $this->findShipmentForCurrentTenant($shipmentId);

        $this->authorize('updateDraft', $shipment);

        $shipment = $this->shipmentService->validateShipment($accountId, $shipmentId, $request->user());

        return response()->json(['data' => $shipment]);
    }

    public function walletPreflight(Request $request, string $shipmentId): JsonResponse
    {
        $accountId = $this->currentAccountId();
        $shipment = $this->findShipmentForCurrentTenant($shipmentId);

        $this->authorize('paymentPreflight', $shipment);

        $data = $request->validate([
            'correlation_id' => 'nullable|string|max:200',
            'idempotency_key' => 'nullable|string|max:200',
        ]);

        $result = $this->shipmentService->createWalletPreflightReservation(
            $accountId,
            $shipmentId,
            $request->user(),
            $data
        );

        return response()->json(['data' => $result], 201);
    }

    public function updateStatus(Request $request, string $shipmentId): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $data = $request->validate([
            'status' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        $shipment = $this->findShipmentForCurrentTenant($shipmentId);
        $this->authorize('update', $shipment);

        $shipment = $this->shipmentService->updateStatus(
            $accountId,
            $shipmentId,
            $data['status'],
            $request->user(),
            $data['reason'] ?? null
        );

        return response()->json(['data' => $shipment]);
    }

    public function cancel(Request $request, string $shipmentId): JsonResponse
    {
        $accountId = $this->currentAccountId();
        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $shipment = $this->findShipmentForCurrentTenant($shipmentId);
        $this->authorize('cancel', $shipment);

        $shipment = $this->shipmentService->cancelShipment(
            $accountId,
            $shipmentId,
            $request->user(),
            $data['reason'] ?? null
        );

        return response()->json(['data' => $shipment]);
    }

    public function label(Request $request, string $shipmentId): JsonResponse
    {
        $accountId = $this->currentAccountId();
        $shipment = $this->findShipmentForCurrentTenant($shipmentId);

        $this->authorize('printLabel', $shipment);

        $info = $this->shipmentService->getLabelInfo($accountId, $shipmentId, $request->user());

        return response()->json(['data' => $info]);
    }

    public function index(Request $request): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $filters = $request->validate([
            'store_id' => 'nullable|uuid',
            'status' => 'nullable|string',
            'carrier' => 'nullable|string',
            'source' => 'nullable|string|in:direct,order,bulk,return',
            'is_cod' => 'nullable|boolean',
            'is_international' => 'nullable|boolean',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'search' => 'nullable|string|max:200',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ]);

        $this->authorize('viewAny', Shipment::class);

        $result = $this->shipmentService->listShipments($accountId, $filters, $request->user());

        return response()->json(['data' => $result]);
    }

    public function show(Request $request, string $shipmentId): JsonResponse
    {
        $accountId = $this->currentAccountId();
        $shipment = $this->findShipmentForCurrentTenant($shipmentId);

        $this->authorize('view', $shipment);

        $shipment = $this->shipmentService->getShipment($accountId, $shipmentId);

        return response()->json(['data' => $shipment]);
    }

    public function stats(Request $request): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $this->authorize('viewAny', Shipment::class);

        $storeId = $request->query('store_id');
        $stats = $this->shipmentService->getShipmentStats($accountId, $storeId);

        return response()->json(['data' => $stats]);
    }

    public function bulkCreate(Request $request): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $data = $request->validate([
            'order_ids' => 'required|array|min:1|max:100',
            'order_ids.*' => 'uuid',
            'defaults' => 'nullable|array',
            'defaults.sender_name' => 'nullable|string',
            'defaults.sender_phone' => 'nullable|string',
            'defaults.sender_address_1' => 'nullable|string',
            'defaults.sender_city' => 'nullable|string',
            'defaults.sender_country' => 'nullable|string|size:2',
        ]);

        $this->authorize('bulkImport', Shipment::class);

        $result = $this->shipmentService->bulkCreateFromOrders(
            $accountId,
            $data['order_ids'],
            $data['defaults'] ?? [],
            $request->user()
        );

        return response()->json(['data' => $result]);
    }

    public function createReturn(Request $request, string $shipmentId): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $overrides = $request->validate([
            'parcels' => 'nullable|array|min:1',
            'parcels.*.weight' => 'required_with:parcels|numeric|min:0.01',
        ]);

        $shipment = $this->findShipmentForCurrentTenant($shipmentId);
        $this->authorize('createReturn', $shipment);

        $shipment = $this->shipmentService->createReturnShipment($accountId, $shipmentId, $overrides, $request->user());

        return response()->json(['data' => $shipment], 201);
    }

    public function addParcel(Request $request, string $shipmentId): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $data = $request->validate([
            'weight' => 'required|numeric|min:0.01|max:999',
            'length' => 'nullable|numeric|min:0.1|max:999',
            'width' => 'nullable|numeric|min:0.1|max:999',
            'height' => 'nullable|numeric|min:0.1|max:999',
            'packaging_type' => 'nullable|string|in:box,envelope,tube,custom',
            'description' => 'nullable|string|max:300',
            'reference' => 'nullable|string|max:100',
        ]);

        $shipment = $this->findShipmentForCurrentTenant($shipmentId);
        $this->authorize('updateDraft', $shipment);

        $parcel = $this->shipmentService->addParcel($accountId, $shipmentId, $data, $request->user());

        return response()->json(['data' => $parcel], 201);
    }

    public function removeParcel(Request $request, string $shipmentId, string $parcelId): JsonResponse
    {
        $accountId = $this->currentAccountId();
        $shipment = $this->findShipmentForCurrentTenant($shipmentId);

        $this->authorize('updateDraft', $shipment);

        $this->shipmentService->removeParcel($accountId, $shipmentId, $parcelId, $request->user());

        return response()->json(['message' => 'Parcel removed successfully.']);
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

    private function findShipmentForCurrentTenant(string $shipmentId): Shipment
    {
        return Shipment::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $shipmentId)
            ->firstOrFail();
    }
}
