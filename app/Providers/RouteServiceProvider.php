<?php
namespace App\Providers;

use App\Models\BillingWallet;
use App\Models\Invitation;
use App\Models\Order;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\SupportTicket;
use App\Models\Wallet;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        $bindExisting = static function (string $modelClass, mixed $value): mixed {
            // SubstituteBindings can run before tenantContext middleware, so this
            // must not depend on global tenant scope being already hydrated.
            $query = $modelClass::query()->withoutGlobalScopes();

            $currentAccountId = app()->bound('current_account_id') ? app('current_account_id') : null;
            if (is_string($currentAccountId) && trim($currentAccountId) !== '') {
                $query->where($query->getModel()->getTable().'.account_id', $currentAccountId);
            }

            $query->whereKey($value)->firstOrFail();

            return $value;
        };

        Route::bind('shipment', static fn (mixed $value): mixed => $bindExisting(Shipment::class, $value));
        Route::bind('shipmentId', static fn (mixed $value): mixed => $bindExisting(Shipment::class, $value));
        Route::bind('order', static fn (mixed $value): mixed => $bindExisting(Order::class, $value));
        Route::bind('orderId', static fn (mixed $value): mixed => $bindExisting(Order::class, $value));
        Route::bind('store', static fn (mixed $value): mixed => $bindExisting(Store::class, $value));
        Route::bind('storeId', static fn (mixed $value): mixed => $bindExisting(Store::class, $value));
        Route::bind('role', static fn (mixed $value): mixed => $bindExisting(Role::class, $value));
        Route::bind('roleId', static fn (mixed $value): mixed => $bindExisting(Role::class, $value));
        Route::bind('invitation', static fn (mixed $value): mixed => $bindExisting(Invitation::class, $value));
        Route::bind('invitationId', static fn (mixed $value): mixed => $bindExisting(Invitation::class, $value));
        Route::bind('support_ticket', static fn (mixed $value): mixed => $bindExisting(SupportTicket::class, $value));
        Route::bind('ticketId', static fn (mixed $value): mixed => $bindExisting(SupportTicket::class, $value));
        Route::bind('wallet', static fn (mixed $value): mixed => $bindExisting(Wallet::class, $value));
        Route::bind('walletId', static function (mixed $value, mixed $route) use ($bindExisting): mixed {
            $uri = method_exists($route, 'uri') ? (string) $route->uri() : '';
            $modelClass = str_contains($uri, 'billing/wallets/') ? BillingWallet::class : Wallet::class;

            return $bindExisting($modelClass, $value);
        });

        // Web/API routes are registered in bootstrap/app.php (Laravel 11).
        // Do not re-register api.php here to avoid duplicate api/v1/v1 routes.
    }
}
