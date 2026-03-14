<?php

namespace App\Http\Middleware;

use App\Models\Account;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContextMiddleware
{
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error_code' => 'ERR_UNAUTHENTICATED',
                'message' => 'Authentication is required.',
            ], 401);
        }

        $this->clearTenantContext();

        $userType = $this->resolveUserType($user);

        if ($userType === 'external') {
            return $this->resolveExternalTenantContext($request, $next);
        }

        if ($mode !== 'required') {
            return $next($request);
        }

        return $this->resolveInternalRequiredTenantContext($request, $next);
    }

    private function resolveExternalTenantContext(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (empty($user->account_id)) {
            return response()->json([
                'success' => false,
                'error_code' => 'ERR_TENANT_CONTEXT_REQUIRED',
                'message' => 'Tenant context is required for external users.',
            ], 403);
        }

        $account = $user->account()->withoutGlobalScopes()->first();
        if (!$account) {
            return response()->json([
                'success' => false,
                'error_code' => 'ERR_TENANT_CONTEXT_REQUIRED',
                'message' => 'Tenant context is missing for the authenticated user.',
            ], 403);
        }

        app()->instance('current_account_id', $account->id);
        app()->instance('current_account', $account);

        return $next($request);
    }

    private function resolveInternalRequiredTenantContext(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenantAccountId = (string) $request->header('X-Tenant-Account-Id', '');

        if (trim($tenantAccountId) === '') {
            return response()->json([
                'success' => false,
                'error_code' => 'ERR_TENANT_CONTEXT_REQUIRED',
                'message' => 'X-Tenant-Account-Id header is required for this endpoint.',
            ], 400);
        }

        if (!method_exists($user, 'hasPermission') || !$user->hasPermission('tenancy.context.select')) {
            return response()->json([
                'success' => false,
                'error_code' => 'ERR_TENANT_CONTEXT_FORBIDDEN',
                'message' => 'You do not have permission to select tenant context.',
            ], 403);
        }

        $account = Account::withoutGlobalScopes()
            ->where('id', $tenantAccountId)
            ->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'error_code' => 'ERR_TENANT_CONTEXT_NOT_FOUND',
                'message' => 'Requested tenant account was not found.',
            ], 404);
        }

        app()->instance('current_account_id', $account->id);
        app()->instance('current_account', $account);

        return $next($request);
    }

    private function clearTenantContext(): void
    {
        if (app()->bound('current_account_id')) {
            app()->forgetInstance('current_account_id');
        }

        if (app()->bound('current_account')) {
            app()->forgetInstance('current_account');
        }
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
