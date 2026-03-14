<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * FIX P2-1: PortalContextMiddleware
 *
 * يحدد البوابة الحالية (b2b|b2c) بناءً على مسار URL
 * ويشاركها مع Request و View.
 *
 * الاستخدام في routes:
 *   middleware: 'portal:b2b'  أو  'portal:b2c'
 */
class PortalContextMiddleware
{
    public function handle(Request $request, Closure $next, string $portal = ''): Response
    {
        // تحديد البوابة من المعامل أو من المسار
        if (empty($portal)) {
            $portal = $this->detectPortalFromPath($request);
        }

        // التحقق من صحة قيمة البوابة
        if (!in_array($portal, ['b2b', 'b2c'])) {
            abort(404, 'Portal not found.');
        }

        // تعيين البوابة في Request attributes
        $request->attributes->set('portal', $portal);

        // مشاركة البوابة مع Blade views
        view()->share('portal', $portal);

        // تعيين في config لاستخدامها في أي مكان
        config(['app.portal' => $portal]);

        return $next($request);
    }

    /**
     * اكتشاف البوابة من مسار URL تلقائياً.
     */
    private function detectPortalFromPath(Request $request): string
    {
        $path = $request->path();

        if (str_starts_with($path, 'b2b')) {
            return 'b2b';
        }

        if (str_starts_with($path, 'b2c')) {
            return 'b2c';
        }

        // Fallback: يمكن تحديدها لاحقاً من subdomain
        $host = $request->getHost();
        if (str_starts_with($host, 'b2b.')) {
            return 'b2b';
        }

        return 'b2c'; // Default to B2C
    }
}
