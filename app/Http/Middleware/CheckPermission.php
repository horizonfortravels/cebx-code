<?php

namespace App\Http\Middleware;

use App\Services\Auth\PermissionResolver;
use App\Support\Internal\InternalControlPlane;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function __construct(private readonly PermissionResolver $permissionResolver)
    {
    }

    public function handle(Request $request, Closure $next, string $permission): Response
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

        if (!$this->permissionResolver->can($user, $permission)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'ERR_PERMISSION',
                    'message' => 'Permission denied.',
                ], 403);
            }

            return response()->view('pages.browser-guidance', $this->browserGuidance($user), 403);
        }

        return $next($request);
    }

    private function browserGuidance(object $user): array
    {
        $isInternal = strtolower((string) ($user->user_type ?? '')) === 'internal' || empty($user->account_id);

        if ($isInternal) {
            $controlPlane = app(InternalControlPlane::class);

            return [
                'statusCode' => 403,
                'eyebrow' => 'صلاحيات غير كافية',
                'title' => 'الوصول غير متاح',
                'heading' => 'هذه الصفحة ليست ضمن دورك الحالي',
                'message' => 'تم تسجيل دخولك بنجاح، لكن دورك الداخلي لا يتضمن الصلاحية المطلوبة لهذه الصفحة الآن.',
                'primaryActionLabel' => $controlPlane->landingActionLabel($user),
                'primaryActionUrl' => route($controlPlane->landingRouteName($user)),
                'secondaryActionLabel' => 'العودة إلى الصفحة السابقة',
                'secondaryActionUrl' => url()->previous(),
            ];
        }

        $isOrganization = strtolower((string) data_get($user, 'account.type', '')) === 'organization';

        return [
            'statusCode' => 403,
            'eyebrow' => 'صلاحية مطلوبة',
            'title' => 'الوصول غير متاح',
            'heading' => 'هذه الصفحة خارج صلاحيات حسابك',
            'message' => 'يمكنك متابعة العمل من الصفحات المتاحة في بوابتك الحالية، أو طلب توسيع الصلاحيات من مدير الحساب.',
            'primaryActionLabel' => $isOrganization ? 'العودة إلى بوابة الأعمال' : 'العودة إلى بوابة الأفراد',
            'primaryActionUrl' => $isOrganization ? route('b2b.dashboard') : route('b2c.dashboard'),
            'secondaryActionLabel' => 'العودة إلى الصفحة السابقة',
            'secondaryActionUrl' => url()->previous(),
        ];
    }
}
