<?php
namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;

class AuditLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($request->user() && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            AuditLog::create([
                'account_id' => $request->user()->account_id,
                'user_id' => $request->user()->id,
                'action' => $request->method() . ' ' . $request->path(),
                'category' => explode('/', $request->path())[1] ?? 'general',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'payload' => $request->except([
                    'password', 'password_confirmation', 'current_password',
                    'token', 'access_token', 'refresh_token',
                    'card_number', 'cvv', 'cvc', 'expiry',
                    'api_key', 'api_secret', 'secret', 'webhook_secret',
                    'client_secret', 'connection_config',
                ]),
            ]);
        }

        return $response;
    }
}
