<?php

namespace App\Http\Middleware;

use App\Support\Internal\InternalControlPlane;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalSurfaceAccess
{
    public function __construct(private readonly InternalControlPlane $controlPlane)
    {
    }

    public function handle(Request $request, Closure $next, string $surface): Response
    {
        $user = $request->user();

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'ERR_UNAUTHENTICATED',
                    'message' => 'Authentication is required.',
                ], 401);
            }

            return redirect()->route('login');
        }

        if (!$this->controlPlane->isKnownSurface($surface)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'ERR_INTERNAL_SURFACE_MISCONFIGURED',
                    'message' => 'Configured internal surface middleware value is invalid.',
                ], 500);
            }

            abort(500, 'Configured internal surface middleware value is invalid.');
        }

        if (!$this->controlPlane->canSeeSurface($user, $surface)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'ERR_INTERNAL_SURFACE_FORBIDDEN',
                    'message' => 'Authenticated internal role cannot access this surface.',
                ], 403);
            }

            return response()->view('pages.browser-guidance', $this->browserGuidance($user), 403);
        }

        return $next($request);
    }

    private function browserGuidance(object $user): array
    {
        return [
            'statusCode' => 403,
            'eyebrow' => 'صلاحيات غير كافية',
            'title' => 'الوصول غير متاح',
            'heading' => 'هذه الصفحة ليست ضمن دورك الحالي',
            'message' => 'تم تسجيل دخولك بنجاح، لكن الدور الداخلي المعتمد لك لا يشمل هذه الصفحة الآن.',
            'primaryActionLabel' => $this->controlPlane->landingActionLabel($user),
            'primaryActionUrl' => route($this->controlPlane->landingRouteName($user)),
            'secondaryActionLabel' => 'العودة إلى الصفحة السابقة',
            'secondaryActionUrl' => url()->previous(),
        ];
    }
}
