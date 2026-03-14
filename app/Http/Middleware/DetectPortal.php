<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Detects B2C/B2B/Admin portal type from authenticated user
 * and shares $portalType with ALL Blade views.
 *
 * This replaces the old $this->middleware() approach which
 * was removed in Laravel 11.
 */
class DetectPortal
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $portalType = 'b2b'; // default

        if ($user && $user->account) {
            $portalType = $user->account->type === 'individual'
                ? 'b2c'
                : 'b2b';
        }

        if ($user && ($user->user_type ?? null) === 'internal') {
            $portalType = 'admin';
        }

        // Store on request for controllers to access
        $request->attributes->set('portalType', $portalType);

        // Share with ALL Blade views
        View::share('portalType', $portalType);

        return $next($request);
    }
}
