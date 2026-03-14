<?php
namespace App\Http\Controllers\Web;

use App\Models\Store;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StoreWebController extends WebController
{
    public function index()
    {
        $accountId = auth()->user()->account_id;

        // ═══ FIX P0-B2: Add account_id scoping — was showing ALL accounts' stores ═══
        return view('pages.stores.index', [
            'stores' => Store::where('account_id', $accountId)
                ->withCount('orders')
                ->get(),
        ]);
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'name' => 'required',
            'platform' => 'required',
            'url' => 'required|url',
        ]);

        $d['status'] = 'active';
        $d['account_id'] = auth()->user()->account_id;
        Store::create($d);

        return back()->with('success', 'تم ربط المتجر');
    }

    /**
     * ═══ FIX P1: sync() now attempts actual store synchronization ═══
     * BEFORE: Just returned back()->with('success') — fake success
     * AFTER:  Attempts HTTP connection to store URL, imports new orders
     */
    public function sync(Store $store)
    {
        $accountId = auth()->user()->account_id;

        // Security: ensure store belongs to current account
        if ($store->account_id !== $accountId) {
            abort(403, 'ليس لديك صلاحية لهذا المتجر');
        }

        try {
            // Attempt to fetch orders from the store URL
            $storeUrl = rtrim($store->url ?? '', '/');

            if (empty($storeUrl)) {
                return back()->with('error', 'رابط المتجر غير محدد');
            }

            // Try to reach the store API
            $response = Http::timeout(10)->get($storeUrl . '/orders.json');

            if ($response->successful()) {
                $orders = $response->json('orders', []);
                $imported = 0;

                foreach ($orders as $orderData) {
                    $externalId = $orderData['id'] ?? $orderData['order_id'] ?? null;
                    if (!$externalId) continue;

                    // Skip if already imported
                    if (Order::where('store_id', $store->id)->where('external_order_id', $externalId)->exists()) {
                        continue;
                    }

                    Order::create([
                        'account_id' => $accountId,
                        'store_id' => $store->id,
                        'external_order_id' => (string) $externalId,
                        'external_order_number' => $orderData['order_number'] ?? "ORD-{$externalId}",
                        'source' => 'store_sync',
                        'status' => 'pending',
                        'customer_name' => $orderData['customer']['name'] ?? $orderData['customer_name'] ?? '—',
                        'customer_email' => $orderData['customer']['email'] ?? $orderData['customer_email'] ?? null,
                        'total_amount' => (float) ($orderData['total_price'] ?? $orderData['total_amount'] ?? 0),
                        'currency' => $orderData['currency'] ?? 'SAR',
                    ]);
                    $imported++;
                }

                $store->update(['last_synced_at' => now()]);
                return back()->with('success', "تمت مزامنة {$store->name} — تم استيراد {$imported} طلب جديد");
            }

            return back()->with('error', 'فشل الاتصال بالمتجر: HTTP ' . $response->status());
        } catch (\Exception $e) {
            Log::warning('Store sync failed', ['store_id' => $store->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'فشل مزامنة المتجر: ' . $e->getMessage());
        }
    }

    /**
     * ═══ FIX P1: test() now performs actual connection test ═══
     * BEFORE: Just returned back()->with('success') — always "successful"
     * AFTER:  Actually pings the store URL to verify connectivity
     */
    public function test(Store $store)
    {
        $accountId = auth()->user()->account_id;

        if ($store->account_id !== $accountId) {
            abort(403, 'ليس لديك صلاحية لهذا المتجر');
        }

        try {
            $storeUrl = rtrim($store->url ?? '', '/');

            if (empty($storeUrl)) {
                return back()->with('error', 'رابط المتجر غير محدد');
            }

            $response = Http::timeout(10)->head($storeUrl);

            if ($response->successful() || $response->status() < 500) {
                return back()->with('success', 'اتصال ناجح بـ ' . $store->name . ' (HTTP ' . $response->status() . ')');
            }

            return back()->with('error', 'فشل الاتصال بـ ' . $store->name . ' (HTTP ' . $response->status() . ')');
        } catch (\Exception $e) {
            return back()->with('error', 'فشل الاتصال: ' . $e->getMessage());
        }
    }

    public function destroy(Store $store)
    {
        $accountId = auth()->user()->account_id;

        if ($store->account_id !== $accountId) {
            abort(403, 'ليس لديك صلاحية لهذا المتجر');
        }

        $storeName = $store->name;
        $store->delete();
        return back()->with('success', 'تم حذف المتجر: ' . $storeName);
    }
}
