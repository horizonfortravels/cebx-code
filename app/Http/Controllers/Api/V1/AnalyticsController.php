<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Analytics;
use App\Models\Shipment;
use App\Models\Invoice;
use App\Models\Branch;
use App\Models\Driver;
use App\Models\Claim;
use App\Models\SupportTicket;
use App\Services\CommissionCalculationService;
use App\Services\AIDelayService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * CBEX GROUP — Global Analytics Dashboard Controller
 *
 * Comprehensive analytics across all modules:
 * shipments, revenue, performance, carriers, branches, etc.
 */
class AnalyticsController extends Controller
{
    public function __construct(
        protected CommissionCalculationService $commission,
    ) {}

    /**
     * Main analytics overview
     */
    public function overview(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Analytics::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $from = $request->get('from', now()->subDays(30)->toDateString());
        $to = $request->get('to', now()->toDateString());

        $shipments = Shipment::where('account_id', $accountId)
            ->whereBetween('created_at', [$from, "$to 23:59:59"]);

        $invoices = Invoice::where('account_id', $accountId)
            ->whereBetween('issued_at', [$from, "$to 23:59:59"]);

        return response()->json(['data' => [
            'period' => ['from' => $from, 'to' => $to],
            'shipments' => [
                'total' => (clone $shipments)->count(),
                'delivered' => (clone $shipments)->where('status', 'delivered')->count(),
                'in_transit' => (clone $shipments)->where('status', 'in_transit')->count(),
                'pending' => (clone $shipments)->whereIn('status', ['created', 'booked'])->count(),
                'cancelled' => (clone $shipments)->where('status', 'cancelled')->count(),
                'returned' => (clone $shipments)->where('status', 'returned')->count(),
                'exceptions' => (clone $shipments)->where('status', 'exception')->count(),
            ],
            'revenue' => [
                'total_invoiced' => round((clone $invoices)->sum('total_amount'), 2),
                'total_paid' => round((clone $invoices)->where('status', 'paid')->sum('total_amount'), 2),
                'total_pending' => round((clone $invoices)->where('status', 'pending')->sum('total_amount'), 2),
                'currency' => 'SAR',
            ],
            'delivery_rate' => $this->deliveryRate($accountId, $from, $to),
            'avg_delivery_time_hours' => $this->avgDeliveryTime($accountId, $from, $to),
            'claims_count' => Claim::where('account_id', $accountId)
                ->whereBetween('created_at', [$from, "$to 23:59:59"])->count(),
            'tickets_count' => SupportTicket::where('account_id', $accountId)
                ->whereBetween('created_at', [$from, "$to 23:59:59"])->count(),
        ]]);
    }

    /**
     * Shipment trends (daily/weekly/monthly)
     */
    public function shipmentTrends(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Analytics::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $period = $request->get('period', 'daily'); // daily, weekly, monthly
        $days = $request->get('days', 30);

        $format = match ($period) {
            'weekly' => "TO_CHAR(created_at, 'IYYY-IW')",
            'monthly' => "TO_CHAR(created_at, 'YYYY-MM')",
            default => "TO_CHAR(created_at, 'YYYY-MM-DD')",
        };

        $trends = Shipment::where('account_id', $accountId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw("{$format} as period, count(*) as total")
            ->selectRaw("count(*) filter (where status = 'delivered') as delivered")
            ->selectRaw("count(*) filter (where status = 'cancelled') as cancelled")
            ->groupByRaw($format)
            ->orderByRaw("{$format} asc")
            ->get();

        return response()->json(['data' => $trends]);
    }

    /**
     * Revenue analytics
     */
    public function revenue(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Analytics::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $days = $request->get('days', 30);

        $daily = Invoice::where('account_id', $accountId)
            ->where('issued_at', '>=', now()->subDays($days))
            ->selectRaw("TO_CHAR(issued_at, 'YYYY-MM-DD') as date")
            ->selectRaw("sum(total_amount) as total")
            ->selectRaw("count(*) as invoice_count")
            ->selectRaw("sum(total_amount) filter (where status = 'paid') as paid")
            ->groupByRaw("TO_CHAR(issued_at, 'YYYY-MM-DD')")
            ->orderByRaw("TO_CHAR(issued_at, 'YYYY-MM-DD') asc")
            ->get();

        // By shipment type
        $byType = Shipment::where('account_id', $accountId)
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays($days))
            ->join('invoices', 'shipments.id', '=', 'invoices.shipment_id')
            ->selectRaw("shipment_type, sum(invoices.total_amount) as revenue, count(*) as count")
            ->groupBy('shipment_type')
            ->get();

        return response()->json(['data' => [
            'daily' => $daily,
            'by_type' => $byType,
            'total' => $daily->sum('total'),
            'paid' => $daily->sum('paid'),
        ]]);
    }

    /**
     * Carrier performance analytics
     */
    public function carrierPerformance(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Analytics::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $days = $request->get('days', 30);

        $carriers = Shipment::where('account_id', $accountId)
            ->whereNotNull('carrier_id')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw("carrier_id, carrier_name")
            ->selectRaw("count(*) as total")
            ->selectRaw("count(*) filter (where status = 'delivered') as delivered")
            ->selectRaw("count(*) filter (where status = 'cancelled') as cancelled")
            ->selectRaw("count(*) filter (where status = 'exception') as exceptions")
            ->selectRaw("avg(EXTRACT(EPOCH FROM (delivered_at - created_at))/3600) filter (where delivered_at is not null) as avg_hours")
            ->groupBy('carrier_id', 'carrier_name')
            ->orderByRaw("count(*) desc")
            ->get();

        $carriers->each(function ($c) {
            $c->delivery_rate = $c->total > 0 ? round(($c->delivered / $c->total) * 100, 1) : 0;
            $c->avg_days = $c->avg_hours ? round($c->avg_hours / 24, 1) : null;
        });

        return response()->json(['data' => $carriers]);
    }

    /**
     * Branch performance analytics
     */
    public function branchPerformance(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Analytics::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $days = $request->get('days', 30);

        $branches = Branch::where('account_id', $accountId)
            ->withCount([
                'originShipments as total_shipped' => fn($q) => $q->where('created_at', '>=', now()->subDays($days)),
                'originShipments as delivered' => fn($q) => $q->where('status', 'delivered')->where('created_at', '>=', now()->subDays($days)),
            ])
            ->get()
            ->map(fn($b) => [
                'branch_id' => $b->id,
                'name' => $b->name,
                'city' => $b->city,
                'country' => $b->country,
                'type' => $b->branch_type,
                'total_shipped' => $b->total_shipped,
                'delivered' => $b->delivered,
                'delivery_rate' => $b->total_shipped > 0 ? round(($b->delivered / $b->total_shipped) * 100, 1) : 0,
            ]);

        return response()->json(['data' => $branches]);
    }

    /**
     * Geographic distribution
     */
    public function geoDistribution(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Analytics::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $origins = Shipment::where('account_id', $accountId)
            ->selectRaw("origin_country as country, count(*) as count")
            ->groupBy('origin_country')
            ->orderByRaw("count(*) desc")
            ->get();

        $destinations = Shipment::where('account_id', $accountId)
            ->selectRaw("destination_country as country, count(*) as count")
            ->groupBy('destination_country')
            ->orderByRaw("count(*) desc")
            ->get();

        $topRoutes = Shipment::where('account_id', $accountId)
            ->selectRaw("origin_country || ' → ' || destination_country as route, count(*) as count")
            ->groupByRaw("origin_country || ' → ' || destination_country")
            ->orderByRaw("count(*) desc")
            ->limit(10)
            ->get();

        return response()->json(['data' => [
            'origins' => $origins,
            'destinations' => $destinations,
            'top_routes' => $topRoutes,
        ]]);
    }

    /**
     * Commission report
     */
    public function commissions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Analytics::class);

        $accountId = $this->resolveCurrentAccountId($request);
        if ($accountId === null) {
            abort(404);
        }

        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        return response()->json([
            'data' => $this->commission->report($accountId, $from, $to),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────

    protected function deliveryRate(string $accountId, string $from, string $to): float
    {
        $total = Shipment::where('account_id', $accountId)
            ->whereBetween('created_at', [$from, "$to 23:59:59"])
            ->whereNotIn('status', ['created', 'booked'])
            ->count();

        $delivered = Shipment::where('account_id', $accountId)
            ->whereBetween('created_at', [$from, "$to 23:59:59"])
            ->where('status', 'delivered')
            ->count();

        return $total > 0 ? round(($delivered / $total) * 100, 1) : 0;
    }

    protected function avgDeliveryTime(string $accountId, string $from, string $to): ?float
    {
        $avg = Shipment::where('account_id', $accountId)
            ->whereBetween('created_at', [$from, "$to 23:59:59"])
            ->where('status', 'delivered')
            ->whereNotNull('delivered_at')
            ->selectRaw("AVG(EXTRACT(EPOCH FROM (delivered_at - created_at))/3600) as avg")
            ->value('avg');

        return $avg ? round($avg, 1) : null;
    }

    protected function resolveCurrentAccountId(Request $request): ?string
    {
        $current = app()->bound('current_account_id')
            ? trim((string) app('current_account_id'))
            : '';

        if ($current !== '') {
            return $current;
        }

        $fallback = trim((string) ($request->user()?->account_id ?? ''));

        return $fallback === '' ? null : $fallback;
    }
}
