<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * ShareSidebarData — P3 Performance: Sidebar Cache
 *
 * Caches sidebar counts (shipments, orders, unread notifications, open tickets)
 * for 60 seconds per account. Saves 3-4 DB queries on every page load.
 *
 * INTEGRATION:
 *   1. Register in bootstrap/app.php:
 *      $middleware->alias(['sidebar' => \App\Http\Middleware\ShareSidebarData::class]);
 *
 *   2. Add to web route group middleware:
 *      Route::middleware(['auth:web', 'tenant', 'sidebar'])->group(function () { ... });
 *
 * Note: The existing app.blade.php already uses $unreadNotifs and $openTickets
 * as sidebar variables. This middleware provides them via View::share().
 */
class ShareSidebarData
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $accountId = $user->account_id;
        $cacheKey = "sidebar_counts_{$accountId}";

        $counts = Cache::remember($cacheKey, 60, function () use ($accountId) {
            return [
                'shipmentsCount' => \App\Models\Shipment::where('account_id', $accountId)->count(),
                'ordersCount'    => \App\Models\Order::where('account_id', $accountId)->count(),
                'unreadNotifs'   => \App\Models\Notification::where('account_id', $accountId)
                    ->whereNull('read_at')->count(),
                'openTickets'    => \App\Models\SupportTicket::where('account_id', $accountId)
                    ->whereNotIn('status', ['closed', 'resolved'])->count(),
            ];
        });

        // Share to all views (these are used by app.blade.php sidebar)
        View::share($counts);

        return $next($request);
    }
}
