<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'بوابة إدارة الشحن') - CBEX Shipping Gateway</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @include('components.pwa-meta')
    @stack('styles')
</head>
<body>
@php
    $currentUser = auth()->user();
    $currentRoute = Route::currentRouteName() ?? '';
    $currentPath = request()->path();
    $resolvedUserType = strtolower((string) ($currentUser->user_type ?? ''));
    $isInternalUser = $resolvedUserType === 'internal' || ($resolvedUserType === '' && empty($currentUser->account_id));
    $selectedAccountId = app()->bound('current_account_id')
        ? app('current_account_id')
        : session(\App\Support\Tenancy\WebTenantContext::sessionKey());
    $selectedAccount = $selectedAccountId
        ? \App\Models\Account::query()->withoutGlobalScopes()->find($selectedAccountId)
        : null;

    $accountType = strtolower((string) data_get($currentUser, 'account.type', ''));
    $currentPortal = match (true) {
        $isInternalUser => 'internal',
        request()->routeIs('b2c.*') || request()->is('b2c/*') => 'b2c',
        request()->routeIs('b2b.*') || request()->is('b2b/*') => 'b2b',
        $accountType === 'individual' => 'b2c',
        default => 'b2b',
    };

    $internalControlPlane = $isInternalUser ? app(\App\Support\Internal\InternalControlPlane::class) : null;
    $internalRoleProfile = $internalControlPlane ? $internalControlPlane->roleProfile($currentUser) : null;
    $hasDeprecatedInternalRoles = $internalControlPlane ? $internalControlPlane->hasDeprecatedAssignments($currentUser) : false;
    $canAdminAccess = $currentUser?->hasPermission('admin.access') ?? false;
    $canSelectTenant = $currentUser?->hasPermission('tenancy.context.select') ?? false;
    $canManageNotificationChannels = $currentUser?->hasPermission('notifications.channels.manage') ?? false;
    $canReadAccounts = $currentUser?->hasPermission('accounts.read') ?? false;
    $canViewWalletBalance = $currentUser?->hasPermission('wallet.balance') ?? false;
    $canViewWalletLedger = $currentUser?->hasPermission('wallet.ledger') ?? false;
    $canReadShipments = $currentUser?->hasPermission('shipments.read') ?? false;
    $canReadUsers = $currentUser?->hasPermission('users.read') ?? false;
    $canReadKyc = $currentUser?->hasPermission('kyc.read') ?? false;
    $canReadCompliance = $currentUser?->hasPermission('compliance.read') ?? false;
    $canReadDangerousGoods = $currentUser?->hasPermission('dg.read') ?? false;
    $canReadIntegrations = $currentUser?->hasPermission('integrations.read') ?? false;
    $canReadFeatureFlags = $currentUser?->hasPermission('feature_flags.read') ?? false;
    $canReadInternalWebhooks = $currentUser?->hasPermission('webhooks.read') ?? false;
    $canReadApiKeys = $currentUser?->hasPermission('api_keys.read') ?? false;
    $canReadTickets = $currentUser?->hasPermission('tickets.read') ?? false;
    $canReadReports = $currentUser?->hasPermission('reports.read') ?? false;
    $canReadAnalytics = $currentUser?->hasPermission('analytics.read') ?? false;
    $canReadWebhooks = $currentUser?->hasPermission('webhooks.read') ?? false;
    $showDeveloperWorkspace = ! $isInternalUser && $currentPortal === 'b2b' && ($canReadIntegrations || $canReadApiKeys || $canReadWebhooks);
    $showAdminDashboard = $isInternalUser
        && $canAdminAccess
        && $internalControlPlane?->canSeeSurface($currentUser, \App\Support\Internal\InternalControlPlane::SURFACE_ADMIN_DASHBOARD);
    $showTenantContext = $isInternalUser
        && $canSelectTenant
        && $internalControlPlane?->canSeeSurface($currentUser, \App\Support\Internal\InternalControlPlane::SURFACE_TENANT_CONTEXT);
    $showSmtpSettings = $isInternalUser
        && $canManageNotificationChannels
        && $internalControlPlane?->canSeeSurface($currentUser, \App\Support\Internal\InternalControlPlane::SURFACE_SMTP_SETTINGS)
        && Route::has('internal.smtp-settings.edit');
    $showExternalAccountsReadCenter = $isInternalUser
        && $canReadAccounts
        && $internalControlPlane?->canSeeSurface($currentUser, \App\Support\Internal\InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_INDEX)
        && Route::has('internal.accounts.index');
    $showInternalStaffReadCenter = $isInternalUser
        && $canReadUsers
        && $internalControlPlane?->canSeeSurface($currentUser, \App\Support\Internal\InternalControlPlane::SURFACE_INTERNAL_STAFF_INDEX)
        && Route::has('internal.staff.index');
    $showInternalKycReadCenter = $isInternalUser
        && $canReadKyc
        && $internalControlPlane?->canSeeSurface($currentUser, \App\Support\Internal\InternalControlPlane::SURFACE_INTERNAL_KYC_INDEX)
        && Route::has('internal.kyc.index');
    $showInternalComplianceReadCenter = $isInternalUser
        && $canReadCompliance
        && $canReadDangerousGoods
        && $internalControlPlane?->canSeeSurface($currentUser, \App\Support\Internal\InternalControlPlane::SURFACE_INTERNAL_COMPLIANCE_INDEX)
        && Route::has('internal.compliance.index');
    $showInternalBillingReadCenter = $isInternalUser
        && $canViewWalletBalance
        && $canViewWalletLedger
        && $internalControlPlane?->canSeeSurface($currentUser, \App\Support\Internal\InternalControlPlane::SURFACE_INTERNAL_BILLING_INDEX)
        && Route::has('internal.billing.index');
    $showInternalCarrierReadCenter = $isInternalUser
        && $canReadIntegrations
        && $internalControlPlane?->canSeeSurface($currentUser, \App\Support\Internal\InternalControlPlane::SURFACE_INTERNAL_CARRIERS_INDEX)
        && Route::has('internal.carriers.index');
    $showInternalIntegrationsReadCenter = $isInternalUser
        && $canReadIntegrations
        && $internalControlPlane?->canSeeSurface($currentUser, \App\Support\Internal\InternalControlPlane::SURFACE_INTERNAL_INTEGRATIONS_INDEX)
        && Route::has('internal.integrations.index');
    $showInternalFeatureFlagsCenter = $isInternalUser
        && $canReadFeatureFlags
        && $internalControlPlane?->canSeeSurface($currentUser, \App\Support\Internal\InternalControlPlane::SURFACE_INTERNAL_FEATURE_FLAGS_INDEX)
        && Route::has('internal.feature-flags.index');
    $showInternalApiKeysCenter = $isInternalUser
        && $canReadApiKeys
        && $internalControlPlane?->canSeeSurface($currentUser, \App\Support\Internal\InternalControlPlane::SURFACE_INTERNAL_API_KEYS_INDEX)
        && Route::has('internal.api-keys.index');
    $showInternalWebhookReadCenter = $isInternalUser
        && $canReadInternalWebhooks
        && $internalControlPlane?->canSeeSurface($currentUser, \App\Support\Internal\InternalControlPlane::SURFACE_INTERNAL_WEBHOOKS_INDEX)
        && Route::has('internal.webhooks.index');
    $showInternalTicketsReadCenter = $isInternalUser
        && $canReadTickets
        && $internalControlPlane?->canSeeSurface($currentUser, \App\Support\Internal\InternalControlPlane::SURFACE_INTERNAL_TICKETS_INDEX)
        && Route::has('internal.tickets.index');
    $showInternalReportsHub = $isInternalUser
        && $canReadReports
        && $canReadAnalytics
        && $internalControlPlane?->canSeeSurface($currentUser, \App\Support\Internal\InternalControlPlane::SURFACE_INTERNAL_REPORTS_INDEX)
        && Route::has('internal.reports.index');
    $showInternalShipmentReadCenter = $isInternalUser
        && $canReadShipments
        && $internalControlPlane?->canSeeSurface($currentUser, \App\Support\Internal\InternalControlPlane::SURFACE_INTERNAL_SHIPMENTS_INDEX)
        && Route::has('internal.shipments.index');
    $internalTopbarRole = $hasDeprecatedInternalRoles
        ? 'وصول داخلي قديم تم إخفاء مسماه من الواجهة النشطة'
        : ($internalRoleProfile['label'] ?? 'وصول داخلي مضبوط وفق الدور المعتمد');

    $menu = [];

    if ($isInternalUser) {
        $menu[] = ['divider' => $showAdminDashboard ? 'لوحة الإدارة' : 'المساحة الداخلية'];

        if ($showAdminDashboard) {
            $menu[] = ['active' => ['admin.index'], 'route' => 'admin.index', 'icon' => 'ADM', 'label' => 'لوحة الإدارة'];
        } else {
            $menu[] = ['active' => ['internal.home'], 'route' => 'internal.home', 'icon' => 'IN', 'label' => 'الرئيسية الداخلية'];
        }

        if ($showTenantContext) {
            $menu[] = ['active' => ['admin.tenant-context', 'internal.tenant-context'], 'route' => $showAdminDashboard ? 'admin.tenant-context' : 'internal.tenant-context', 'icon' => 'CTX', 'label' => 'اختيار الحساب'];
        }

        if ($showExternalAccountsReadCenter) {
            $menu[] = ['divider' => 'عمليات العملاء'];
            $menu[] = ['active' => ['internal.accounts.*'], 'route' => 'internal.accounts.index', 'icon' => 'ACC', 'label' => 'حسابات العملاء'];
        }

        if ($showInternalBillingReadCenter) {
            if (! $showExternalAccountsReadCenter) {
                $menu[] = ['divider' => 'عمليات العملاء'];
            }

            $menu[] = ['active' => ['internal.billing.*'], 'route' => 'internal.billing.index', 'icon' => 'WAL', 'label' => 'Wallet & billing'];
        }

        if ($showInternalCarrierReadCenter) {
            if (! $showExternalAccountsReadCenter && ! $showInternalBillingReadCenter) {
                $menu[] = ['divider' => 'عمليات العملاء'];
            }

            $menu[] = ['active' => ['internal.carriers.*'], 'route' => 'internal.carriers.index', 'icon' => 'CAR', 'label' => 'Carrier integrations'];
        }

        if ($showInternalIntegrationsReadCenter) {
            if (! $showExternalAccountsReadCenter && ! $showInternalBillingReadCenter && ! $showInternalCarrierReadCenter) {
                $menu[] = ['divider' => 'عمليات العملاء'];
            }

            $menu[] = ['active' => ['internal.integrations.*'], 'route' => 'internal.integrations.index', 'icon' => 'INT', 'label' => 'Integrations'];
        }

        if ($showInternalFeatureFlagsCenter) {
            if (! $showExternalAccountsReadCenter && ! $showInternalBillingReadCenter && ! $showInternalIntegrationsReadCenter) {
                $menu[] = ['divider' => 'عمليات العملاء'];
            }

            $menu[] = ['active' => ['internal.feature-flags.*'], 'route' => 'internal.feature-flags.index', 'icon' => 'FLG', 'label' => 'Feature flags'];
        }

        if ($showInternalApiKeysCenter) {
            if (! $showExternalAccountsReadCenter && ! $showInternalBillingReadCenter && ! $showInternalIntegrationsReadCenter && ! $showInternalFeatureFlagsCenter) {
                $menu[] = ['divider' => 'عمليات العملاء'];
            }

            $menu[] = ['active' => ['internal.api-keys.*'], 'route' => 'internal.api-keys.index', 'icon' => 'KEY', 'label' => 'API keys'];
        }

        if ($showInternalWebhookReadCenter) {
            if (! $showExternalAccountsReadCenter && ! $showInternalBillingReadCenter && ! $showInternalIntegrationsReadCenter && ! $showInternalApiKeysCenter) {
                $menu[] = ['divider' => 'عمليات العملاء'];
            }

            $menu[] = ['active' => ['internal.webhooks.*'], 'route' => 'internal.webhooks.index', 'icon' => 'WH', 'label' => 'Webhooks'];
        }

        if ($showInternalShipmentReadCenter) {
            if (! $showExternalAccountsReadCenter && ! $showInternalBillingReadCenter && ! $showInternalIntegrationsReadCenter && ! $showInternalWebhookReadCenter) {
                $menu[] = ['divider' => 'عمليات العملاء'];
            }

            $menu[] = ['active' => ['internal.shipments.*'], 'route' => 'internal.shipments.index', 'icon' => 'SHP', 'label' => 'Shipments'];
        }

        if ($showInternalTicketsReadCenter) {
            if (! $showExternalAccountsReadCenter && ! $showInternalShipmentReadCenter && ! $showInternalWebhookReadCenter) {
                $menu[] = ['divider' => 'عمليات العملاء'];
            }

            $menu[] = ['active' => ['internal.tickets.*'], 'route' => 'internal.tickets.index', 'icon' => 'TKT', 'label' => 'Tickets'];
        }

        if ($showInternalReportsHub) {
            if (! $showExternalAccountsReadCenter && ! $showInternalShipmentReadCenter && ! $showInternalTicketsReadCenter) {
                $menu[] = ['divider' => 'عمليات العملاء'];
            }

            $menu[] = ['active' => ['internal.reports.*'], 'route' => 'internal.reports.index', 'icon' => 'RPT', 'label' => 'Reports & analytics'];
        }

        if ($showInternalKycReadCenter) {
            if (! $showExternalAccountsReadCenter && ! $showInternalShipmentReadCenter && ! $showInternalTicketsReadCenter && ! $showInternalReportsHub) {
                $menu[] = ['divider' => 'عمليات العملاء'];
            }

            $menu[] = ['active' => ['internal.kyc.*'], 'route' => 'internal.kyc.index', 'icon' => 'KYC', 'label' => 'حالات التحقق'];
        }

        if ($showInternalComplianceReadCenter) {
            if (! $showExternalAccountsReadCenter && ! $showInternalShipmentReadCenter && ! $showInternalKycReadCenter) {
                $menu[] = ['divider' => 'عمليات العملاء'];
            }

            $menu[] = ['active' => ['internal.compliance.*'], 'route' => 'internal.compliance.index', 'icon' => 'CMP', 'label' => 'Compliance & DG'];
        }

        if ($showInternalStaffReadCenter) {
            $menu[] = ['divider' => 'عمليات المنصة'];
            $menu[] = ['active' => ['internal.staff.*'], 'route' => 'internal.staff.index', 'icon' => 'STF', 'label' => 'فريق المنصة'];
        }

        if ($showSmtpSettings) {
            $menu[] = ['divider' => 'البريد والمنصة'];
            $menu[] = ['active' => ['internal.smtp-settings.*'], 'route' => 'internal.smtp-settings.edit', 'icon' => 'SMTP', 'label' => 'إعدادات SMTP'];
        }

        if ($showAdminDashboard) {
            $menu[] = ['divider' => 'الحساب المحدد'];
            $menu[] = ['active' => ['admin.users'], 'route' => 'admin.users', 'icon' => 'USR', 'label' => 'مستخدمو الحساب'];
            $menu[] = ['active' => ['admin.roles'], 'route' => 'admin.roles', 'icon' => 'ROL', 'label' => 'أدوار الحساب'];
            $menu[] = ['active' => ['admin.reports'], 'route' => 'admin.reports', 'icon' => 'RPT', 'label' => 'تقارير الحساب'];
        }
    } elseif ($currentPortal === 'b2c') {
        $menu = [
            ['divider' => 'بوابة الأفراد'],
            ['active' => ['b2c.dashboard'], 'route' => 'b2c.dashboard', 'icon' => 'HOME', 'label' => 'الرئيسية'],
            ['active' => ['b2c.shipments.*'], 'route' => 'b2c.shipments.index', 'icon' => 'SH', 'label' => 'الشحنات'],
            ['active' => ['b2c.tracking.*'], 'route' => 'b2c.tracking.index', 'icon' => 'TR', 'label' => 'التتبع'],
            ['active' => ['b2c.wallet.*'], 'route' => 'b2c.wallet.index', 'icon' => 'WL', 'label' => 'المحفظة'],
            ['active' => ['b2c.addresses.*'], 'route' => 'b2c.addresses.index', 'icon' => 'ADR', 'label' => 'العناوين'],
            ['active' => ['b2c.support.*'], 'route' => 'b2c.support.index', 'icon' => 'SUP', 'label' => 'الدعم'],
            ['active' => ['b2c.settings.*'], 'route' => 'b2c.settings.index', 'icon' => 'SET', 'label' => 'الإعدادات'],
        ];
    } else {
        $menu = [
            ['divider' => 'بوابة الأعمال'],
            ['active' => ['b2b.dashboard'], 'route' => 'b2b.dashboard', 'icon' => 'HOME', 'label' => 'الرئيسية'],
            ['active' => ['b2b.shipments.*'], 'route' => 'b2b.shipments.index', 'icon' => 'SH', 'label' => 'الشحنات'],
            ['active' => ['b2b.addresses.*'], 'route' => 'b2b.addresses.index', 'icon' => 'ADR', 'label' => 'العناوين'],
            ['active' => ['b2b.orders.*'], 'route' => 'b2b.orders.index', 'icon' => 'OR', 'label' => 'الطلبات'],
            ['active' => ['b2b.wallet.*'], 'route' => 'b2b.wallet.index', 'icon' => 'WL', 'label' => 'المحفظة'],
            ['active' => ['b2b.reports.*'], 'route' => 'b2b.reports.index', 'icon' => 'RPT', 'label' => 'التقارير'],
            ['active' => ['b2b.users.*'], 'route' => 'b2b.users.index', 'icon' => 'USR', 'label' => 'المستخدمون'],
            ['active' => ['b2b.roles.*'], 'route' => 'b2b.roles.index', 'icon' => 'ROL', 'label' => 'الأدوار'],
        ];

        if ($showDeveloperWorkspace) {
            $menu[] = ['divider' => 'أدوات المطور'];
            if ($canReadIntegrations) {
                $menu[] = ['active' => ['b2b.developer.index'], 'route' => 'b2b.developer.index', 'icon' => 'DEV', 'label' => 'واجهة المطور'];
                $menu[] = ['active' => ['b2b.developer.integrations'], 'route' => 'b2b.developer.integrations', 'icon' => 'INT', 'label' => 'التكاملات'];
            }
            if ($canReadApiKeys) {
                $menu[] = ['active' => ['b2b.developer.api-keys'], 'route' => 'b2b.developer.api-keys', 'icon' => 'KEY', 'label' => 'مفاتيح API'];
            }
            if ($canReadWebhooks) {
                $menu[] = ['active' => ['b2b.developer.webhooks'], 'route' => 'b2b.developer.webhooks', 'icon' => 'WH', 'label' => 'الويبهوكات'];
            }
        }

        if (Route::has('b2b.settings.index')) {
            $menu[] = ['divider' => 'إعدادات الحساب'];
            $menu[] = ['active' => ['b2b.settings.*'], 'route' => 'b2b.settings.index', 'icon' => 'SET', 'label' => 'الإعدادات'];
        }
    }

    $topbarSubtitle = match (true) {
        $isInternalUser && $selectedAccount !== null => 'الدور الداخلي: ' . $internalTopbarRole . ' • الحساب المحدد: ' . $selectedAccount->name,
        $isInternalUser => 'الدور الداخلي: ' . $internalTopbarRole,
        $currentPortal === 'b2c' => 'بوابة الأفراد للحساب الفردي الحالي',
        $currentPortal === 'b2b' && $currentUser?->account?->name => 'حساب المنظمة الحالي: ' . $currentUser->account->name,
        default => 'بوابة الأعمال لحسابات المنظمات',
    };
@endphp
<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="{{ asset('images/logo-sidebar.png') }}" alt="CBEX" class="sidebar-logo-img">
            <span class="sidebar-title">CBEX Gateway</span>
        </div>
        <nav class="sidebar-nav">
            @if($isInternalUser)
                <div class="sidebar-divider">السياق الحالي</div>
                <div style="padding:12px 14px;margin:8px 10px 16px;background:rgba(15,23,42,.04);border:1px solid var(--bd);border-radius:12px;font-size:12px;color:var(--td)">
                    @if($selectedAccount)
                        <div style="font-weight:700;color:var(--tx);margin-bottom:4px">{{ $selectedAccount->name }}</div>
                        <div>{{ $selectedAccount->type === 'organization' ? 'منظمة' : 'فردي' }}</div>
                    @else
                        <div style="font-weight:700;color:var(--tx);margin-bottom:4px">لا يوجد حساب محدد</div>
                        <div>اختر حسابًا فقط عندما تحتاج تصفح بيانات عميل محدد.</div>
                    @endif
                </div>
            @endif

            @foreach($menu as $item)
                @if(isset($item['divider']))
                    <div class="sidebar-divider">{{ $item['divider'] }}</div>
                @else
                    @php
                        $patterns = (array) ($item['active'] ?? []);
                        $isActive = false;
                        foreach ($patterns as $pattern) {
                            if (request()->routeIs($pattern)) {
                                $isActive = true;
                                break;
                            }
                        }
                    @endphp
                    <a href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}" class="sidebar-item {{ $isActive ? 'active' : '' }}">
                        <span class="icon">{{ $item['icon'] }}</span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </nav>
        <div class="sidebar-footer">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit"><span>تسجيل الخروج</span></button>
            </form>
        </div>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <div class="topbar-inner">
                <div>
                    <div style="color: var(--tm); font-size: 11px;">مرحبًا، {{ $currentUser->name ?? 'مستخدم' }}</div>
                    <div style="font-size:12px;color:var(--td);margin-top:2px">{{ $topbarSubtitle }}</div>
                </div>
                <div class="topbar-user">
                    @if($isInternalUser && $showTenantContext)
                        <a class="topbar-bell" href="{{ route($showAdminDashboard ? 'admin.tenant-context' : 'internal.tenant-context') }}" title="اختيار الحساب">CTX</a>
                    @endif
                    <div class="topbar-avatar">{{ mb_substr($currentUser->name ?? 'م', 0, 1) }}</div>
                </div>
            </div>
        </header>

        <main class="content">
            <div class="content-shell">
                @if(session('success'))
                    <x-toast type="success" :message="session('success')" />
                @endif
                @if(session('error'))
                    <x-toast type="error" :message="session('error')" />
                @endif
                @yield('content')
            </div>
        </main>
    </div>
</div>

<script src="{{ asset('js/pwa.js') }}"></script>
@stack('scripts')
</body>
</html>
