<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserTypeMiddleware
{
    public function handle(Request $request, Closure $next, string $expectedType): Response
    {
        $user = $request->user();

        if (! $user) {
            if (! $this->isBrowserRequest($request)) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'ERR_UNAUTHENTICATED',
                    'message' => 'Authentication is required.',
                ], 401);
            }

            return redirect()->route('login');
        }

        $expectedType = strtolower(trim($expectedType));
        if (! in_array($expectedType, ['internal', 'external'], true)) {
            if ($this->isBrowserRequest($request)) {
                abort(500, 'تمت تهيئة نوع مستخدم غير صالح لهذا المسار.');
            }

            return response()->json([
                'success' => false,
                'error_code' => 'ERR_USER_TYPE_MISCONFIGURED',
                'message' => 'Configured user type middleware value is invalid.',
            ], 500);
        }

        $actualType = $this->resolveUserType($user);
        if ($actualType !== $expectedType) {
            if ($this->isBrowserRequest($request)) {
                return response()->view('pages.browser-guidance', $this->browserGuidance($user, $expectedType), 403);
            }

            return response()->json([
                'success' => false,
                'error_code' => 'ERR_USER_TYPE_FORBIDDEN',
                'message' => 'Authenticated user type is not allowed on this endpoint.',
            ], 403);
        }

        return $next($request);
    }

    private function browserGuidance(object $user, string $expectedType): array
    {
        if ($expectedType === 'internal') {
            return [
                'statusCode' => 403,
                'eyebrow' => 'وصول غير متاح',
                'title' => 'المنطقة الداخلية',
                'heading' => 'هذه الصفحة مخصصة لفريق التشغيل الداخلي',
                'message' => 'حسابك مسجل كبوابة عميل، لذلك لا يمكن فتح صفحات الإدارة الداخلية من هنا.',
                'primaryActionLabel' => $this->externalPortalLabel($user),
                'primaryActionUrl' => $this->externalPortalUrl($user),
                'secondaryActionLabel' => 'العودة إلى البوابة الرئيسية',
                'secondaryActionUrl' => url('/'),
            ];
        }

        return [
            'statusCode' => 403,
            'eyebrow' => 'بوابة مختلفة',
            'title' => 'المساحة الداخلية',
            'heading' => 'أنت مسجل بحساب داخلي',
            'message' => 'بوابات العملاء B2C وB2B مخصصة للحسابات الخارجية فقط. استخدم المساحة الداخلية للوصول إلى الأدوات المناسبة لدورك.',
            'primaryActionLabel' => $this->internalPortalLabel($user),
            'primaryActionUrl' => $this->internalPortalUrl($user),
            'secondaryActionLabel' => 'العودة إلى الصفحة السابقة',
            'secondaryActionUrl' => url()->previous(),
        ];
    }

    private function externalPortalUrl(object $user): string
    {
        $accountType = strtolower((string) data_get($user, 'account.type', ''));

        return match ($accountType) {
            'organization' => route('b2b.dashboard'),
            'individual' => route('b2c.dashboard'),
            default => route('dashboard'),
        };
    }

    private function externalPortalLabel(object $user): string
    {
        $accountType = strtolower((string) data_get($user, 'account.type', ''));

        return match ($accountType) {
            'organization' => 'العودة إلى بوابة الأعمال',
            'individual' => 'العودة إلى بوابة الأفراد',
            default => 'العودة إلى البوابة الرئيسية',
        };
    }

    private function internalPortalUrl(object $user): string
    {
        if (method_exists($user, 'hasPermission') && $user->hasPermission('admin.access')) {
            return route('admin.index');
        }

        return route('internal.home');
    }

    private function internalPortalLabel(object $user): string
    {
        if (method_exists($user, 'hasPermission') && $user->hasPermission('admin.access')) {
            return 'العودة إلى لوحة الإدارة';
        }

        return 'الانتقال إلى المساحة الداخلية';
    }

    private function isBrowserRequest(Request $request): bool
    {
        return ! $request->expectsJson() && ! $request->is('api/*');
    }

    private function resolveUserType(object $user): string
    {
        $userType = strtolower((string) ($user->user_type ?? ''));

        if (in_array($userType, ['internal', 'external'], true)) {
            return $userType;
        }

        return empty($user->account_id) ? 'internal' : 'external';
    }
}
