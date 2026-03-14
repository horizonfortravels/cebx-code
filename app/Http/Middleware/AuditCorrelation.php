<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware: Sets the audit correlation/request ID for every incoming request.
 * If X-Request-ID header is provided, it's used; otherwise auto-generated.
 */
class AuditCorrelation
{
    public function handle(Request $request, Closure $next)
    {
        // Reset for each new request
        AuditService::resetRequestId();

        // Use incoming header if present
        $incomingId = $request->header('X-Request-ID');
        if ($incomingId) {
            AuditService::setRequestId($incomingId);
        }

        $response = $next($request);

        // Attach correlation ID to response header for traceability
        $response->headers->set('X-Request-ID', AuditService::getRequestId());

        return $response;
    }
}
