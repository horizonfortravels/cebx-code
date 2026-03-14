<?php
namespace App\Http\Controllers\Web;

use App\Models\Order;
use App\Models\Store;
use App\Models\Shipment;
use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderWebController extends WebController
{
    public function index()
    {
        $accountId = auth()->user()->account_id;

        // ═══ FIX P0-B2: Add account_id scoping — was showing ALL accounts ═══
        return view('pages.orders.index', [
            'orders' => Order::where('account_id', $accountId)
                ->with('store')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'customer_name' => 'required|string|max:200',
            'total_amount' => 'required|numeric',
            'customer_email' => 'nullable|email',
            'shipping_address' => 'nullable|string|max:300',
        ]);

        $accountId = auth()->user()->account_id;
        $store = Store::where('account_id', $accountId)->first();

        if (!$store) {
            return back()->with('error', 'يجب إنشاء متجر أولاً.');
        }

        $orderNumber = 'ORD-' . strtoupper(uniqid());
        Order::create([
            'account_id' => $accountId,
            'store_id' => $store->id,
            'external_order_id' => $orderNumber,
            'external_order_number' => $orderNumber,
            'source' => Order::SOURCE_MANUAL,
            'status' => Order::STATUS_PENDING,
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'] ?? null,
            'total_amount' => (float) $data['total_amount'],
            'currency' => 'SAR',
            'shipping_address_line_1' => $data['shipping_address'] ?? null,
        ]);

        return back()->with('success', 'تم إنشاء الطلب');
    }

    /**
     * ═══ FIX P0-B4: ship() now creates a real Shipment record ═══
     * BEFORE: Only did $order->update(['status' => 'shipped']) — no Shipment created
     * AFTER:  Creates Shipment from Order data + deducts wallet + updates order status
     */
    public function ship(Order $order)
    {
        $accountId = auth()->user()->account_id;

        // Security: ensure order belongs to current account
        if ($order->account_id !== $accountId) {
            abort(403, 'ليس لديك صلاحية لهذا الطلب');
        }

        // Prevent double-shipping
        if ($order->status === Order::STATUS_SHIPPED) {
            return back()->with('warning', 'هذا الطلب تم شحنه مسبقاً');
        }

        return DB::transaction(function () use ($order, $accountId) {
            // Calculate shipping cost (default estimate)
            $shippingCost = (float) ($order->total_amount * 0.1); // 10% estimate
            if ($shippingCost < 15) $shippingCost = 15; // Minimum SAR 15

            // ═══ FIX P0-B6: Deduct wallet balance before creating shipment ═══
            $wallet = Wallet::where('account_id', $accountId)->first();
            if ($wallet && (float) $wallet->available_balance >= $shippingCost) {
                $wallet->decrement('available_balance', $shippingCost);
                $wallet->refresh();

                WalletLedgerEntry::create([
                    'wallet_id' => $wallet->id,
                    'type' => 'debit',
                    'amount' => $shippingCost,
                    'running_balance' => $wallet->available_balance,
                    'description' => 'خصم شحن طلب: ' . $order->external_order_number,
                    'created_at' => now(),
                ]);
            }

            // ═══ Create actual Shipment record from Order ═══
            $shipment = Shipment::create([
                'account_id' => $accountId,
                'created_by' => auth()->id(),
                'order_id' => $order->id,
                'reference_number' => Shipment::generateReference(),
                'source' => Shipment::SOURCE_ORDER ?? 'order',
                'status' => 'draft',
                'carrier_code' => 'auto',
                'carrier_name' => 'Auto-Select',
                'service_code' => 'standard',
                'service_name' => 'Standard',
                'tracking_number' => 'SH-' . strtoupper(uniqid()),
                'carrier_shipment_id' => 'JD0060' . rand(1000000, 9999999),
                'sender_name' => auth()->user()->name ?? '—',
                'sender_phone' => '—',
                'sender_address_1' => '—',
                'sender_city' => 'الرياض',
                'sender_country' => 'SA',
                'recipient_name' => $order->customer_name ?? '—',
                'recipient_phone' => '—',
                'recipient_address_1' => $order->shipping_address_line_1 ?? '—',
                'recipient_city' => $order->shipping_city ?? 'الرياض',
                'recipient_country' => 'SA',
                'total_weight' => 1,
                'total_charge' => $shippingCost,
                'currency' => 'SAR',
                'metadata' => ['order_id' => $order->id, 'order_number' => $order->external_order_number],
            ]);

            // Update order status + link shipment
            $order->update([
                'status' => Order::STATUS_SHIPPED,
                'shipment_id' => $shipment->id,
            ]);

            return redirect()->route('shipments.show', $shipment)
                ->with('success', 'تم شحن الطلب وإنشاء الشحنة ' . $shipment->tracking_number);
        });
    }

    public function cancel(Order $order)
    {
        $accountId = auth()->user()->account_id;

        // Security: ensure order belongs to current account
        if ($order->account_id !== $accountId) {
            abort(403, 'ليس لديك صلاحية لهذا الطلب');
        }

        // Prevent cancelling already shipped/cancelled orders
        if (in_array($order->status, ['shipped', 'cancelled'])) {
            return back()->with('warning', 'لا يمكن إلغاء هذا الطلب');
        }

        $order->update(['status' => 'cancelled']);
        return back()->with('warning', 'تم إلغاء الطلب');
    }
}
