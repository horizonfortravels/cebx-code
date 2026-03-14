<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Support\Tenancy\WebTenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->denyAccess($request, 'يرجى تسجيل الدخول.', 401);
        }

        if ($this->resolveUserType($user) === 'internal') {
            return $this->handleInternalUser($request, $next, $user);
        }

        return $this->handleExternalUser($request, $next, $user);
    }

    private function handleExternalUser(Request $request, Closure $next, object $user): Response
    {
        if (empty($user->account_id)) {
            return $this->denyAccess($request, 'الحساب غير مرتبط. تواصل مع الدعم.', 403);
        }

        $account = $user->account()->withoutGlobalScopes()->first();
        if (! $account) {
            return $this->denyAccess($request, 'الحساب غير موجود.', 403);
        }

        if (($account->status ?? 'active') === 'suspended') {
            return $this->denyAccess($request, 'الحساب موقوف. تواصل مع الإدارة.', 403);
        }

        app()->instance('current_account_id', (string) $user->account_id);
        app()->instance('current_account', $account);

        return $next($request);
    }

    private function handleInternalUser(Request $request, Closure $next, object $user): Response
    {
        if (! method_exists($user, 'hasPermission') || ! $user->hasPermission('tenancy.context.select')) {
            return $this->denyAccess($request, 'لا تملك صلاحية اختيار سياق حساب للتصفح الداخلي.', 403);
        }

        $selectedAccountId = WebTenantContext::currentAccountId($request);
        if ($selectedAccountId === null) {
            return $this->denyMissingInternalTenantContext($request);
        }

        $account = Account::query()->withoutGlobalScopes()->find($selectedAccountId);
        if (! $account) {
            WebTenantContext::clear($request);

            return $this->denyMissingInternalTenantContext(
                $request,
                'الحساب المحدد غير متاح. اختر حسابًا آخر لمتابعة التصفح.'
            );
        }

        if (($account->status ?? 'active') === 'suspended') {
            return $this->denyAccess($request, 'الحساب المحدد موقوف. اختر حسابًا آخر.', 403);
        }

        app()->instance('current_account_id', (string) $account->id);
        app()->instance('current_account', $account);

        return $next($request);
    }

    private function denyAccess(Request $request, string $message, int $status): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'error_code' => $status === 401 ? 'ERR_UNAUTHENTICATED' : 'ERR_ACCOUNT_INVALID',
                'message' => $message,
            ], $status);
        }

        if ($status === 401) {
            return redirect($this->resolveLoginRoute($request));
        }

        abort($status, $message);
    }

    private function denyMissingInternalTenantContext(
        Request $request,
        string $message = 'يرجى اختيار حساب أولًا لمتابعة الصفحات المرتبطة بعميل.'
    ): Response {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'error_code' => 'ERR_TENANT_CONTEXT_REQUIRED',
                'message' => $message,
            ], 400);
        }

        return redirect()
            ->route('admin.tenant-context', ['redirect' => $request->fullUrl()])
            ->with('error', $message);
    }

    private function resolveLoginRoute(Request $request): string
    {
        $path = $request->path();

        if (str_starts_with($path, 'admin')) {
            return url('/admin/login');
        }

        if (str_starts_with($path, 'b2b')) {
            return url('/b2b/login');
        }

        if (str_starts_with($path, 'b2c')) {
            return url('/b2c/login');
        }

        return url('/login');
    }

    private function resolveUserType(object $user): string
    {
        $userType = strtolower(trim((string) ($user->user_type ?? '')));

        if (in_array($userType, ['internal', 'external'], true)) {
            return $userType;
        }

        return empty($user->account_id) ? 'internal' : 'external';
    }
}
