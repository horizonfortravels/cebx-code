<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Shipment;
use App\Services\AuditService;
use App\Services\DynamicPricingService;
use App\Services\FraudDetectionService;
use App\Services\PricingEngineService;
use App\Services\SmartRoutingService;
use App\Services\StatusTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function __construct(
        protected PricingEngineService $pricing,
        protected StatusTransitionService $statusEngine,
        protected SmartRoutingService $routing,
        protected DynamicPricingService $dynamicPricing,
        protected FraudDetectionService $fraud,
        protected AuditService $audit,
    ) {}

    public function getQuotes(Request $request): JsonResponse
    {
        $this->authorize('viewAny', self::class);

        $data = $request->validate([
            'origin_city' => 'required|string',
            'origin_country' => 'required|string|size:2',
            'destination_city' => 'required|string',
            'destination_country' => 'required|string|size:2',
            'weight' => 'required|numeric|min:0.1',
            'dimensions' => 'nullable|array',
            'dimensions.length' => 'numeric',
            'dimensions.width' => 'numeric',
            'dimensions.height' => 'numeric',
            'service_level' => 'in:express,standard,economy',
            'preferred_mode' => 'nullable|in:air,sea,land',
            'items_count' => 'nullable|integer',
            'declared_value' => 'nullable|numeric',
            'insurance' => 'nullable|boolean',
        ]);

        $routes = $this->routing->suggestRoutes($data);

        $quotes = [];
        foreach ($routes['routes'] as $route) {
            $dynamicPrice = $this->dynamicPricing->calculate([
                'base_price' => $route['estimated_cost'],
                'origin_country' => $data['origin_country'],
                'destination_country' => $data['destination_country'],
                'shipment_type' => $route['mode'],
                'weight' => $data['weight'],
                'service_level' => $data['service_level'] ?? 'standard',
            ]);

            $quotes[] = [
                'quote_id' => Str::uuid()->toString(),
                'route' => $route,
                'pricing' => $dynamicPrice,
                'total_price' => $dynamicPrice['total_price'],
                'currency' => 'SAR',
                'eta' => $route['eta'],
                'estimated_days' => $route['estimated_days'],
                'valid_until' => $dynamicPrice['valid_until'],
            ];
        }

        return response()->json([
            'data' => [
                'quotes' => $quotes,
                'total_options' => count($quotes),
                'params' => $data,
            ],
        ]);
    }

    public function createBooking(Request $request): JsonResponse
    {
        $this->authorize('create', self::class);

        $data = $request->validate([
            'quote_id' => 'nullable|string',
            'sender_name' => 'required|string|max:200',
            'sender_phone' => 'required|string|max:30',
            'sender_email' => 'nullable|email',
            'sender_address' => 'required|string',
            'sender_city' => 'required|string',
            'sender_country' => 'required|string|size:2',
            'sender_postal_code' => 'nullable|string|max:20',
            'receiver_name' => 'required|string|max:200',
            'receiver_phone' => 'required|string|max:30',
            'receiver_email' => 'nullable|email',
            'receiver_address' => 'required|string',
            'receiver_city' => 'required|string',
            'receiver_country' => 'required|string|size:2',
            'receiver_postal_code' => 'nullable|string|max:20',
            'weight' => 'required|numeric|min:0.1',
            'length' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'description' => 'nullable|string|max:500',
            'declared_value' => 'nullable|numeric',
            'insurance' => 'nullable|boolean',
            'shipment_type' => 'required|in:air,sea,land',
            'service_level' => 'in:express,standard,economy',
            'incoterm' => 'nullable|string|max:10',
            'carrier_id' => 'nullable|uuid',
            'selected_rate_id' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.weight' => 'nullable|numeric',
            'items.*.hs_code' => 'nullable|string|max:20',
            'items.*.dangerous' => 'nullable|boolean',
            'items.*.value' => 'nullable|numeric',
        ]);

        $user = $request->user();

        $volumetricWeight = 0.0;
        if (($data['length'] ?? 0) && ($data['width'] ?? 0) && ($data['height'] ?? 0)) {
            $volumetricWeight = ($data['length'] * $data['width'] * $data['height']) / 5000;
        }
        $chargeableWeight = max((float) $data['weight'], $volumetricWeight);

        $shipment = Shipment::create(array_filter([
            'account_id' => $this->currentAccountId(),
            'tracking_number' => Schema::hasColumn('shipments', 'tracking_number') ? 'CBX' . strtoupper(Str::random(10)) : null,
            'reference_number' => Schema::hasColumn('shipments', 'reference_number') ? 'SHP-' . strtoupper(Str::random(10)) : null,
            'user_id' => Schema::hasColumn('shipments', 'user_id') ? $user->id : null,
            'created_by' => Schema::hasColumn('shipments', 'created_by') ? $user->id : null,
            'origin_country' => $data['sender_country'],
            'destination_country' => $data['receiver_country'],
            'shipment_type' => $data['shipment_type'],
            'service_level' => Schema::hasColumn('shipments', 'service_level') ? ($data['service_level'] ?? 'standard') : null,
            'incoterm' => Schema::hasColumn('shipments', 'incoterm') ? ($data['incoterm'] ?? 'DAP') : null,
            'incoterm_code' => Schema::hasColumn('shipments', 'incoterm_code') ? substr((string) ($data['incoterm'] ?? 'DAP'), 0, 3) : null,
            'total_weight' => $data['weight'],
            'total_volume' => Schema::hasColumn('shipments', 'total_volume') ? $volumetricWeight : null,
            'volumetric_weight' => Schema::hasColumn('shipments', 'volumetric_weight') ? $volumetricWeight : null,
            'chargeable_weight' => $chargeableWeight,
            'declared_value' => $data['declared_value'] ?? 0,
            'insurance_flag' => Schema::hasColumn('shipments', 'insurance_flag') ? ($data['insurance'] ?? false) : null,
            'is_insured' => Schema::hasColumn('shipments', 'is_insured') ? ($data['insurance'] ?? false) : null,
            'status' => Schema::hasColumn('shipments', 'status') ? 'draft' : null,
            'source' => Schema::hasColumn('shipments', 'source') ? 'direct' : null,
            'sender_name' => $data['sender_name'],
            'sender_phone' => $data['sender_phone'],
            'sender_email' => Schema::hasColumn('shipments', 'sender_email') ? ($data['sender_email'] ?? null) : null,
            'sender_address' => Schema::hasColumn('shipments', 'sender_address') ? $data['sender_address'] : null,
            'sender_address_1' => Schema::hasColumn('shipments', 'sender_address_1') ? $data['sender_address'] : null,
            'sender_city' => $data['sender_city'],
            'sender_postal_code' => Schema::hasColumn('shipments', 'sender_postal_code') ? ($data['sender_postal_code'] ?? null) : null,
            'receiver_name' => $data['receiver_name'],
            'receiver_phone' => $data['receiver_phone'],
            'receiver_email' => Schema::hasColumn('shipments', 'recipient_email') ? ($data['receiver_email'] ?? null) : null,
            'receiver_address' => Schema::hasColumn('shipments', 'receiver_address') ? $data['receiver_address'] : null,
            'recipient_address' => Schema::hasColumn('shipments', 'recipient_address') ? $data['receiver_address'] : null,
            'recipient_address_1' => Schema::hasColumn('shipments', 'recipient_address_1') ? $data['receiver_address'] : null,
            'recipient_name' => Schema::hasColumn('shipments', 'recipient_name') ? $data['receiver_name'] : null,
            'recipient_phone' => Schema::hasColumn('shipments', 'recipient_phone') ? $data['receiver_phone'] : null,
            'recipient_city' => Schema::hasColumn('shipments', 'recipient_city') ? $data['receiver_city'] : null,
            'recipient_country' => Schema::hasColumn('shipments', 'recipient_country') ? $data['receiver_country'] : null,
            'recipient_postal_code' => Schema::hasColumn('shipments', 'recipient_postal_code') ? ($data['receiver_postal_code'] ?? null) : null,
            'receiver_city' => Schema::hasColumn('shipments', 'receiver_city') ? $data['receiver_city'] : null,
            'description' => $data['description'] ?? null,
            'carrier_id' => Schema::hasColumn('shipments', 'carrier_id') ? ($data['carrier_id'] ?? null) : null,
            'currency' => Schema::hasColumn('shipments', 'currency') ? 'SAR' : null,
        ], static fn ($value) => $value !== null));

        foreach ($data['items'] ?? [] as $item) {
            $shipment->items()->create(array_filter([
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'weight' => $item['weight'] ?? null,
                'hs_code' => $item['hs_code'] ?? null,
                'dangerous_flag' => $item['dangerous'] ?? false,
                'unit_value' => $item['value'] ?? null,
                'total_value' => isset($item['value']) ? ($item['value'] * $item['quantity']) : null,
                'origin_country' => Schema::hasColumn('shipment_items', 'origin_country') ? $data['sender_country'] : null,
                'currency' => Schema::hasColumn('shipment_items', 'currency') ? 'SAR' : null,
            ], static fn ($value) => $value !== null));
        }

        $fraudResult = $this->fraud->scan($shipment);
        if ($fraudResult['level'] === 'blocked') {
            $shipment->update(['status' => 'held', 'hold_reason' => 'fraud_check']);

            return response()->json([
                'data' => $shipment,
                'warning' => 'الشحنة قيد المراجعة الأمنية',
                'fraud_check' => $fraudResult,
            ], 202);
        }

        $invoice = null;
        if (Schema::hasTable('invoices') && Schema::hasTable('invoice_items')) {
            $invoice = $this->generateInvoice($shipment, $data);
        }

        $this->audit->info(
            $this->currentAccountId(),
            (string) $user->id,
            'booking.created',
            AuditLog::CATEGORY_ACCOUNT,
            Shipment::class,
            (string) $shipment->id
        );

        return response()->json([
            'data' => $shipment->fresh()->load('items'),
            'invoice' => $invoice,
            'message' => "تم الحجز بنجاح — #{$shipment->tracking_number}",
        ], 201);
    }

    public function confirmBooking(Request $request, string $id): JsonResponse
    {
        $shipment = $this->findShipmentForCurrentAccount($id);
        $this->authorize('manage', [self::class, $shipment]);

        if (!in_array((string) $shipment->status, ['booked', 'created', 'draft', 'validated', 'rated', 'payment_pending'], true)) {
            return response()->json(['message' => 'الشحنة غير قابلة للتأكيد في حالتها الحالية'], 422);
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'shipment_id')) {
            $invoice = Invoice::query()->where('shipment_id', $shipment->id)->first();
            if ($invoice && $invoice->status !== 'paid') {
                return response()->json([
                    'message' => 'يجب دفع الفاتورة أولاً',
                    'invoice' => $invoice,
                ], 422);
            }
        }

        $updates = [];
        if (Schema::hasColumn('shipments', 'confirmed_at')) {
            $updates['confirmed_at'] = now();
        }
        if (Schema::hasColumn('shipments', 'status')) {
            $updates['status'] = in_array((string) $shipment->status, ['draft', 'validated', 'rated', 'payment_pending'], true)
                ? 'purchased'
                : $shipment->status;
        }

        if ($updates !== []) {
            $shipment->update($updates);
        }

        $this->audit->info(
            $this->currentAccountId(),
            (string) $request->user()->id,
            'booking.confirmed',
            AuditLog::CATEGORY_ACCOUNT,
            Shipment::class,
            (string) $shipment->id
        );

        return response()->json([
            'data' => $shipment->fresh(),
            'message' => 'تم تأكيد الحجز',
        ]);
    }

    public function cancelBooking(Request $request, string $id): JsonResponse
    {
        $shipment = $this->findShipmentForCurrentAccount($id);
        $this->authorize('manage', [self::class, $shipment]);

        $cancellable = ['created', 'booked', 'draft', 'validated', 'rated', 'payment_pending', 'purchased'];
        if (!in_array((string) $shipment->status, $cancellable, true)) {
            return response()->json(['message' => 'لا يمكن إلغاء الشحنة في هذه المرحلة'], 422);
        }

        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $updates = [];
        if (Schema::hasColumn('shipments', 'status')) {
            $updates['status'] = 'cancelled';
        }
        if (Schema::hasColumn('shipments', 'cancelled_at')) {
            $updates['cancelled_at'] = now();
        }
        if (Schema::hasColumn('shipments', 'cancelled_by')) {
            $updates['cancelled_by'] = $request->user()->id;
        }
        if (Schema::hasColumn('shipments', 'cancellation_reason')) {
            $updates['cancellation_reason'] = $data['reason'] ?? 'Cancelled by customer';
        }
        if (Schema::hasColumn('shipments', 'status_reason')) {
            $updates['status_reason'] = $data['reason'] ?? 'Cancelled by customer';
        }

        $shipment->update($updates);

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'shipment_id')) {
            Invoice::query()->where('shipment_id', $shipment->id)->update(['status' => 'void']);
        }

        $this->audit->info(
            $this->currentAccountId(),
            (string) $request->user()->id,
            'booking.cancelled',
            AuditLog::CATEGORY_ACCOUNT,
            Shipment::class,
            (string) $shipment->id,
            null,
            $updates
        );

        return response()->json([
            'data' => $shipment->fresh(),
            'message' => 'تم إلغاء الحجز',
        ]);
    }

    protected function generateInvoice(Shipment $shipment, array $data): Invoice
    {
        $baseCharge = max(
            0,
            (float) ($shipment->chargeable_weight ?? 0) * 5
        );
        $charges = [
            'total' => $baseCharge,
            'items' => [
                [
                    'description' => 'Shipping charge',
                    'amount' => $baseCharge,
                ],
            ],
        ];

        $totalAmount = (float) $charges['total'];

        $invoice = Invoice::create([
            'account_id' => (string) $shipment->account_id,
            'invoice_number' => 'INV-' . now()->format('Ym') . '-' . strtoupper(Str::random(6)),
            'type' => Invoice::TYPE_INVOICE,
            'subtotal' => $totalAmount,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => $totalAmount,
            'currency' => 'SAR',
            'status' => Invoice::STATUS_DRAFT,
            'issued_at' => now(),
            'due_at' => now()->addDays(7),
        ]);

        foreach ($charges['items'] ?? [] as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $item['description'] ?? 'Shipping charge',
                'quantity' => 1,
                'unit_price' => $item['amount'] ?? 0,
                'tax_amount' => 0,
                'total' => $item['amount'] ?? 0,
                'entity_type' => Shipment::class,
                'entity_id' => (string) $shipment->id,
            ]);
        }

        return $invoice;
    }

    private function findShipmentForCurrentAccount(string $id): Shipment
    {
        return Shipment::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $id)
            ->firstOrFail();
    }

    private function currentAccountId(): string
    {
        return trim((string) app('current_account_id'));
    }
}
