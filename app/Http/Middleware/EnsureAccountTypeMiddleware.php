<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountTypeMiddleware
{
    public function handle(Request $request, Closure $next, string $requiredType): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $account = $user->account;

        if (! $account) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'ERR_ACCOUNT_CONTEXT_REQUIRED',
                    'message' => 'The authenticated user is not linked to an account.',
                ], 403);
            }

            return response()->view('pages.browser-guidance', [
                'statusCode' => 403,
                'eyebrow' => 'حساب غير مكتمل',
                'title' => 'لا يوجد حساب مرتبط',
                'heading' => 'لا يمكن فتح هذه البوابة الآن',
                'message' => 'هذا المسار مخصص لحسابات العملاء، بينما المستخدم الحالي غير مرتبط بحساب عميل صالح.',
                'primaryActionLabel' => 'العودة إلى اختيار البوابة',
                'primaryActionUrl' => route('login'),
                'secondaryActionLabel' => 'تسجيل الخروج',
                'secondaryActionUrl' => route('logout'),
                'secondaryActionMethod' => 'post',
            ], 403);
        }

        if ($account->type !== $requiredType) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'ERR_WRONG_PORTAL',
                    'message' => $this->apiErrorMessage($account->type, $requiredType),
                ], 403);
            }

            return response()->view('pages.browser-guidance', $this->browserGuidance($account->type, $requiredType), 403);
        }

        return $next($request);
    }

    private function browserGuidance(string $actualType, string $requiredType): array
    {
        $isB2B = $requiredType === 'organization';

        return [
            'statusCode' => 403,
            'eyebrow' => 'البوابة غير المناسبة',
            'title' => $isB2B ? 'بوابة الأعمال' : 'بوابة الأفراد',
            'heading' => $isB2B
                ? 'هذه المنطقة مخصصة لبوابة الأعمال الخاصة بحسابات المنظمات'
                : 'هذه المنطقة مخصصة لبوابة الأفراد الخاصة بالحسابات الفردية',
            'message' => $actualType === 'organization'
                ? 'حسابك يتبع منظمة خارجية. استخدم بوابة الأعمال لإدارة شحنات المنظمة وفريقها عبر شبكة الناقلين التابعة للمنصة.'
                : 'حسابك فردي خارجي. استخدم بوابة الأفراد لإدارة شحناتك الشخصية ومحفظتك وتتبعك عبر شبكة الناقلين التابعة للمنصة.',
            'primaryActionLabel' => $actualType === 'organization' ? 'العودة إلى بوابة الأعمال' : 'العودة إلى بوابة الأفراد',
            'primaryActionUrl' => $actualType === 'organization' ? route('b2b.dashboard') : route('b2c.dashboard'),
            'secondaryActionLabel' => 'العودة إلى الصفحة السابقة',
            'secondaryActionUrl' => url()->previous(),
        ];
    }

    private function apiErrorMessage(string $actualType, string $requiredType): string
    {
        if ($requiredType === 'organization') {
            return 'This portal is only available for organization accounts.';
        }

        if ($actualType === 'organization') {
            return 'This portal is only available for individual accounts.';
        }

        return 'The authenticated account type is not allowed for this portal.';
    }
}
