<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AccountScope
{
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->user()) {
            app()->instance('current_account_id', $user->account_id);
        }
        return $next($request);
    }
}
