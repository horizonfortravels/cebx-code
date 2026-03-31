<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Order;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Internal\InternalControlPlane;
use App\Support\Tenancy\WebTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternalAdminWebController extends Controller
{
    public function home(Request $request): View
    {
        $user = $request->user();
        $selectedAccount = $this->selectedAccount($request);
        $controlPlane = app(InternalControlPlane::class);

        return view('pages.admin.internal-home', [
            'selectedAccount' => $selectedAccount,
            'roleProfile' => $controlPlane->roleProfile($user),
            'hasDeprecatedRoleAssignments' => $controlPlane->hasDeprecatedAssignments($user),
            'capabilities' => [
                'adminAccess' => $user->hasPermission('admin.access'),
                'tenantContext' => $user->hasPermission('tenancy.context.select'),
                'ticketsRead' => $user->hasPermission('tickets.read'),
                'ticketsManage' => $user->hasPermission('tickets.manage'),
                'reportsRead' => $user->hasPermission('reports.read'),
                'analyticsRead' => $user->hasPermission('analytics.read'),
            ],
            'surfaces' => [
                'adminDashboard' => $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_ADMIN_DASHBOARD)
                    && $user->hasPermission('admin.access'),
                'tenantContext' => $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_TENANT_CONTEXT)
                    && $user->hasPermission('tenancy.context.select'),
                'smtpSettings' => $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_SMTP_SETTINGS)
                    && $user->hasPermission('notifications.channels.manage'),
            ],
        ]);
    }

    public function index(Request $request): View
    {
        $selectedAccount = $this->selectedAccount($request);

        return view('pages.admin.index', [
            'selectedAccount' => $selectedAccount,
            'orgCount' => Account::query()->withoutGlobalScopes()->where('type', 'organization')->count(),
            'usersCount' => User::query()->count(),
            'totalShipments' => Shipment::query()->withoutGlobalScopes()->count(),
            'systemHealth' => [
                ['name' => 'قاعدة البيانات', 'status' => 'ok', 'latency' => 'n/a'],
                ['name' => 'الجلسات', 'status' => 'ok', 'latency' => 'n/a'],
                ['name' => 'الويب الداخلي', 'status' => 'ok', 'latency' => 'n/a'],
            ],
            'recentActivity' => collect(),
        ]);
    }

    public function tenantContext(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));

        $accounts = Account::query()
            ->withoutGlobalScopes()
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->where('name', 'like', '%' . $query . '%')
                        ->orWhere('slug', 'like', '%' . $query . '%')
                        ->orWhere('type', 'like', '%' . $query . '%');
                });
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('pages.admin.tenant-context', [
            'accounts' => $accounts,
            'selectedAccount' => $this->selectedAccount($request),
            'redirectTo' => (string) $request->query('redirect', route($this->defaultInternalRouteName($request))),
            'search' => $query,
        ]);
    }

    public function storeTenantContext(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'string'],
            'redirect' => ['nullable', 'string'],
        ]);

        $account = Account::query()->withoutGlobalScopes()->findOrFail($validated['account_id']);

        WebTenantContext::setCurrentAccountId($request, (string) $account->id);

        $redirectTo = trim((string) ($validated['redirect'] ?? ''));
        if ($redirectTo === '') {
            $redirectTo = route($this->defaultInternalRouteName($request));
        }

        return redirect()->to($redirectTo)->with('success', 'تم اختيار الحساب: ' . $account->name);
    }

    public function clearTenantContext(Request $request): RedirectResponse
    {
        WebTenantContext::clear($request);

        return redirect()->route($this->tenantContextRouteName($request))
            ->with('success', 'تم مسح سياق الحساب الداخلي.');
    }

    public function users(Request $request): View
    {
        $account = $this->requiredSelectedAccount($request);

        $users = User::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $account->id)
            ->orderBy('name')
            ->paginate(20);

        return view('pages.admin.users', [
            'selectedAccount' => $account,
            'users' => $users,
        ]);
    }

    public function roles(Request $request): View
    {
        $account = $this->requiredSelectedAccount($request);

        $roles = Role::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $account->id)
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->paginate(20);

        return view('pages.admin.roles', [
            'selectedAccount' => $account,
            'roles' => $roles,
        ]);
    }

    public function reports(Request $request): View
    {
        $account = $this->requiredSelectedAccount($request);
        $accountId = (string) $account->id;

        $shipmentsCount = Shipment::query()->withoutGlobalScopes()->where('account_id', $accountId)->count();
        $ordersCount = Order::query()->withoutGlobalScopes()->where('account_id', $accountId)->count();
        $storesCount = Store::query()->withoutGlobalScopes()->where('account_id', $accountId)->count();
        $usersCount = User::query()->withoutGlobalScopes()->where('account_id', $accountId)->count();
        $walletBalance = (float) (Wallet::query()->withoutGlobalScopes()->where('account_id', $accountId)->value('available_balance') ?? 0);

        $recentShipments = Shipment::query()
            ->withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->latest()
            ->limit(10)
            ->get();

        $recentOrders = Order::query()
            ->withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->latest()
            ->limit(10)
            ->get();

        return view('pages.admin.reports', [
            'selectedAccount' => $account,
            'stats' => [
                ['label' => 'الشحنات', 'value' => number_format($shipmentsCount), 'icon' => 'SH'],
                ['label' => 'الطلبات', 'value' => number_format($ordersCount), 'icon' => 'OR'],
                ['label' => 'المتاجر', 'value' => number_format($storesCount), 'icon' => 'ST'],
                ['label' => 'المستخدمون', 'value' => number_format($usersCount), 'icon' => 'US'],
                ['label' => 'الرصيد', 'value' => number_format($walletBalance, 2), 'icon' => 'WL'],
            ],
            'recentShipments' => $recentShipments,
            'recentOrders' => $recentOrders,
        ]);
    }

    private function selectedAccount(Request $request): ?Account
    {
        if (app()->bound('current_account')) {
            $account = app('current_account');
            if ($account instanceof Account) {
                return $account;
            }
        }

        $accountId = WebTenantContext::currentAccountId($request);
        if ($accountId === null) {
            return null;
        }

        return Account::query()->withoutGlobalScopes()->find($accountId);
    }

    private function requiredSelectedAccount(Request $request): Account
    {
        $account = $this->selectedAccount($request);

        if ($account) {
            return $account;
        }

        return redirect()
            ->route($this->tenantContextRouteName($request), ['redirect' => $request->fullUrl()])
            ->with('error', 'اختر حسابًا أولًا قبل فتح هذه الصفحة.')
            ->throwResponse();
    }

    private function defaultInternalRouteName(Request $request): string
    {
        return app(InternalControlPlane::class)->landingRouteName($request->user());
    }

    private function tenantContextRouteName(Request $request): string
    {
        $user = $request->user();
        $controlPlane = app(InternalControlPlane::class);

        return $user
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_ADMIN_DASHBOARD)
            && $user->hasPermission('admin.access')
                ? 'admin.tenant-context'
                : 'internal.tenant-context';
    }
}
