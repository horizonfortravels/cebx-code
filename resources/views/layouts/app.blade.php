<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'بوابة إدارة الشحن') - بوابة الشحن CBEX</title>
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
    $sidebarIconMap = [
        'dashboard' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <rect x="3.75" y="3.75" width="7.5" height="7.5" rx="1.5"></rect>
    <rect x="12.75" y="3.75" width="7.5" height="5.25" rx="1.5"></rect>
    <rect x="3.75" y="12.75" width="7.5" height="7.5" rx="1.5"></rect>
    <rect x="12.75" y="10.5" width="7.5" height="9.75" rx="1.5"></rect>
</svg>
SVG,
        'workspace' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M4.75 10.25 12 4.5l7.25 5.75"></path>
    <path d="M6.5 9.5v9a1.5 1.5 0 0 0 1.5 1.5h8a1.5 1.5 0 0 0 1.5-1.5v-9"></path>
    <path d="M10 20v-5h4v5"></path>
</svg>
SVG,
        'context-switch' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M5 7.5h10.5"></path>
    <path d="m12.5 4.5 3 3-3 3"></path>
    <path d="M19 16.5H8.5"></path>
    <path d="m11.5 13.5-3 3 3 3"></path>
</svg>
SVG,
        'accounts' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M4.75 8.25 12 4.5l7.25 3.75"></path>
    <path d="M6.5 9.25V18"></path>
    <path d="M12 9.25V18"></path>
    <path d="M17.5 9.25V18"></path>
    <path d="M4.75 19.5h14.5"></path>
</svg>
SVG,
        'billing' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M4.75 8.5A2.75 2.75 0 0 1 7.5 5.75h9A2.75 2.75 0 0 1 19.25 8.5v7A2.75 2.75 0 0 1 16.5 18.25h-9A2.75 2.75 0 0 1 4.75 15.5z"></path>
    <path d="M16.5 11.25h2.75v2.5H16.5a1.25 1.25 0 1 1 0-2.5Z"></path>
</svg>
SVG,
        'carriers' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M4.75 7.25h9.5v7.5h-9.5z"></path>
    <path d="M14.25 10.25h3l2 2v2.5h-5"></path>
    <circle cx="8" cy="17.5" r="1.5"></circle>
    <circle cx="17" cy="17.5" r="1.5"></circle>
</svg>
SVG,
        'integrations' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M9 7.5a2.75 2.75 0 1 1-5.5 0 2.75 2.75 0 0 1 5.5 0Z"></path>
    <path d="M20.5 7.5A2.75 2.75 0 1 1 15 7.5a2.75 2.75 0 0 1 5.5 0Z"></path>
    <path d="M14.25 16.5a2.75 2.75 0 1 1-5.5 0 2.75 2.75 0 0 1 5.5 0Z"></path>
    <path d="m8.2 9.5 2.4 4"></path>
    <path d="m15.8 9.5-2.4 4"></path>
</svg>
SVG,
        'features' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M6 5v14"></path>
    <path d="M18 5v14"></path>
    <path d="M12 5v14"></path>
    <path d="M4.75 9.25H7.25"></path>
    <path d="M10.75 14.75h2.5"></path>
    <path d="M16.75 8h2.5"></path>
</svg>
SVG,
        'api-key' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="8.5" cy="12.5" r="3.5"></circle>
    <path d="M12 12.5h7.25"></path>
    <path d="M16.5 12.5v-2.25"></path>
    <path d="M19 12.5v-1.25"></path>
</svg>
SVG,
        'webhooks' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M9.5 7.75a4.75 4.75 0 0 1 7.37 2.08"></path>
    <path d="M14.5 16.25a4.75 4.75 0 0 1-7.37-2.08"></path>
    <path d="m15.75 7.25 1.75 2.58 2.92-.9"></path>
    <path d="m8.25 16.75-1.75-2.58-2.92.9"></path>
</svg>
SVG,
        'shipments' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M4.75 8.5 12 4.75l7.25 3.75v7L12 19.25l-7.25-3.75z"></path>
    <path d="M12 10.25 4.75 6.5"></path>
    <path d="M12 10.25 19.25 6.5"></path>
    <path d="M12 10.25v9"></path>
</svg>
SVG,
        'tickets' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M5.75 7.25A2.5 2.5 0 0 1 8.25 4.75h7.5a2.5 2.5 0 0 1 2.5 2.5v3a1.75 1.75 0 0 0 0 3.5v3a2.5 2.5 0 0 1-2.5 2.5h-7.5a2.5 2.5 0 0 1-2.5-2.5v-3a1.75 1.75 0 0 0 0-3.5z"></path>
    <path d="M9.5 9.5h5"></path>
    <path d="M9.5 14.5h5"></path>
</svg>
SVG,
        'reports' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M5.25 19.25h13.5"></path>
    <path d="M8 17v-4.5"></path>
    <path d="M12 17V8"></path>
    <path d="M16 17v-6.5"></path>
</svg>
SVG,
        'kyc' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M12 4.75 18 7v4.75c0 3.46-2.27 6.59-6 7.5-3.73-.91-6-4.04-6-7.5V7z"></path>
    <path d="m9.5 12 1.75 1.75L14.75 10"></path>
</svg>
SVG,
        'compliance' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="m12 4.75 7 12.5a1 1 0 0 1-.87 1.5H5.87a1 1 0 0 1-.87-1.5z"></path>
    <path d="M12 9v4.5"></path>
    <path d="M12 16.5h.01"></path>
</svg>
SVG,
        'team' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M9.25 9a2.75 2.75 0 1 1-5.5 0 2.75 2.75 0 0 1 5.5 0Z"></path>
    <path d="M20.25 9a2.75 2.75 0 1 1-5.5 0 2.75 2.75 0 0 1 5.5 0Z"></path>
    <path d="M2.75 18c.6-2.17 2.42-3.5 4.5-3.5S11.15 15.83 11.75 18"></path>
    <path d="M12.25 18c.6-2.17 2.42-3.5 4.5-3.5s3.9 1.33 4.5 3.5"></path>
</svg>
SVG,
        'mail' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <rect x="3.75" y="6" width="16.5" height="12" rx="2"></rect>
    <path d="m5.5 7.75 6.5 5 6.5-5"></path>
</svg>
SVG,
        'users' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M12 12.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z"></path>
    <path d="M5.75 19.25a6.25 6.25 0 0 1 12.5 0"></path>
</svg>
SVG,
        'roles' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M12 4.75 18 7v4.75c0 3.46-2.27 6.59-6 7.5-3.73-.91-6-4.04-6-7.5V7z"></path>
    <path d="M9.5 12h5"></path>
</svg>
SVG,
    ];

    $pushMenuGroup = static function (array &$menu, string $label, array $items): void {
        if ($items === []) {
            return;
        }

        $menu[] = ['group_label' => $label];

        foreach ($items as $item) {
            $menu[] = $item;
        }
    };

    $menu = [];

    if ($isInternalUser) {
        $platformItems = [];

        if ($showAdminDashboard) {
            $platformItems[] = ['active' => ['admin.index'], 'route' => 'admin.index', 'icon' => 'dashboard', 'label' => 'لوحة الإدارة الداخلية'];
        } else {
            $platformItems[] = ['active' => ['internal.home'], 'route' => 'internal.home', 'icon' => 'workspace', 'label' => 'لوحة العمليات الداخلية'];
        }

        if ($showTenantContext) {
            $platformItems[] = ['active' => ['admin.tenant-context', 'internal.tenant-context'], 'route' => $showAdminDashboard ? 'admin.tenant-context' : 'internal.tenant-context', 'icon' => 'context-switch', 'label' => 'اختيار الحساب'];
        }

        $pushMenuGroup($menu, 'المنصة الداخلية', $platformItems);
        $menu[] = ['context_card' => true];

        $selectedAccountItems = [];

        if ($showAdminDashboard) {
            $selectedAccountItems[] = ['active' => ['admin.users'], 'route' => 'admin.users', 'icon' => 'users', 'label' => 'مستخدمو الحساب'];
            $selectedAccountItems[] = ['active' => ['admin.roles'], 'route' => 'admin.roles', 'icon' => 'roles', 'label' => 'أدوار الحساب'];
            $selectedAccountItems[] = ['active' => ['admin.reports'], 'route' => 'admin.reports', 'icon' => 'reports', 'label' => 'تقارير الحساب'];
        }

        $pushMenuGroup($menu, 'أدوات الحساب المحدد', $selectedAccountItems);

        $customerOperationsItems = [];

        if ($showExternalAccountsReadCenter) {
            $customerOperationsItems[] = ['active' => ['internal.accounts.*'], 'route' => 'internal.accounts.index', 'icon' => 'accounts', 'label' => 'حسابات العملاء'];
        }

        if ($showInternalShipmentReadCenter) {
            $customerOperationsItems[] = ['active' => ['internal.shipments.*'], 'route' => 'internal.shipments.index', 'icon' => 'shipments', 'label' => 'الشحنات'];
        }

        if ($showInternalTicketsReadCenter) {
            $customerOperationsItems[] = ['active' => ['internal.tickets.*'], 'route' => 'internal.tickets.index', 'icon' => 'tickets', 'label' => 'التذاكر'];
        }

        if ($showInternalKycReadCenter) {
            $customerOperationsItems[] = ['active' => ['internal.kyc.*'], 'route' => 'internal.kyc.index', 'icon' => 'kyc', 'label' => 'حالات التحقق'];
        }

        if ($showInternalComplianceReadCenter) {
            $customerOperationsItems[] = ['active' => ['internal.compliance.*'], 'route' => 'internal.compliance.index', 'icon' => 'compliance', 'label' => 'الامتثال والمواد الخطرة'];
        }

        if ($showInternalBillingReadCenter) {
            $customerOperationsItems[] = ['active' => ['internal.billing.*'], 'route' => 'internal.billing.index', 'icon' => 'billing', 'label' => 'المحفظة والفوترة'];
        }

        if ($showInternalReportsHub) {
            $customerOperationsItems[] = ['active' => ['internal.reports.*'], 'route' => 'internal.reports.index', 'icon' => 'reports', 'label' => 'التقارير والتحليلات'];
        }

        $pushMenuGroup($menu, 'عمليات العملاء', $customerOperationsItems);

        $partnerItems = [];

        if ($showInternalCarrierReadCenter) {
            $partnerItems[] = ['active' => ['internal.carriers.*'], 'route' => 'internal.carriers.index', 'icon' => 'carriers', 'label' => 'شركات الشحن'];
        }

        if ($showInternalIntegrationsReadCenter) {
            $partnerItems[] = ['active' => ['internal.integrations.*'], 'route' => 'internal.integrations.index', 'icon' => 'integrations', 'label' => 'التكاملات'];
        }

        if ($showInternalFeatureFlagsCenter) {
            $partnerItems[] = ['active' => ['internal.feature-flags.*'], 'route' => 'internal.feature-flags.index', 'icon' => 'features', 'label' => 'إعدادات الميزات'];
        }

        if ($showInternalApiKeysCenter) {
            $partnerItems[] = ['active' => ['internal.api-keys.*'], 'route' => 'internal.api-keys.index', 'icon' => 'api-key', 'label' => 'مفاتيح التكامل'];
        }

        if ($showInternalWebhookReadCenter) {
            $partnerItems[] = ['active' => ['internal.webhooks.*'], 'route' => 'internal.webhooks.index', 'icon' => 'webhooks', 'label' => 'الويبهوكات'];
        }

        $pushMenuGroup($menu, 'الشحن والتكاملات', $partnerItems);

        $platformManagementItems = [];

        if ($showInternalStaffReadCenter) {
            $platformManagementItems[] = ['active' => ['internal.staff.*'], 'route' => 'internal.staff.index', 'icon' => 'team', 'label' => 'فريق المنصة'];
        }

        if ($showSmtpSettings) {
            $platformManagementItems[] = ['active' => ['internal.smtp-settings.*'], 'route' => 'internal.smtp-settings.edit', 'icon' => 'mail', 'label' => 'إعدادات البريد'];
        }

        $pushMenuGroup($menu, 'إدارة المنصة', $platformManagementItems);
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
                $menu[] = ['active' => ['b2b.developer.api-keys'], 'route' => 'b2b.developer.api-keys', 'icon' => 'KEY', 'label' => 'مفاتيح التكامل'];
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

    $internalLandingLabel = match (true) {
        $currentRoute === 'admin.index' => 'لوحة الإدارة الداخلية',
        $currentRoute === 'internal.home' => 'لوحة العمليات الداخلية',
        default => 'المركز الداخلي',
    };
    $topbarSubtitle = match (true) {
        $isInternalUser && $selectedAccount !== null => $internalLandingLabel . ' • الدور الداخلي: ' . $internalTopbarRole . ' • الحساب المحدد: ' . $selectedAccount->name,
        $isInternalUser => $internalLandingLabel . ' • الدور الداخلي: ' . $internalTopbarRole,
        $currentPortal === 'b2c' => 'بوابة الأفراد للحساب الفردي الحالي',
        $currentPortal === 'b2b' && $currentUser?->account?->name => 'حساب المنظمة الحالي: ' . $currentUser->account->name,
        default => 'بوابة الأعمال لحسابات المنظمات',
    };
@endphp
<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="{{ asset('images/logo-sidebar.png') }}" alt="CBEX" class="sidebar-logo-img">
            <span class="sidebar-title">بوابة CBEX</span>
        </div>
        <nav class="sidebar-nav">
            @foreach($menu as $item)
                @if(isset($item['group_label']))
                    <div class="sidebar-group-label">{{ $item['group_label'] }}</div>
                @elseif(isset($item['context_card']) && $isInternalUser)
                    <div class="sidebar-context-card">
                        <div class="sidebar-context-card__eyebrow">الحساب الحالي</div>
                        @if($selectedAccount)
                            <div class="sidebar-context-card__title">{{ $selectedAccount->name }}</div>
                            <div class="sidebar-context-card__meta">{{ $selectedAccount->type === 'organization' ? 'حساب منظمة' : 'حساب فردي' }}</div>
                            <div class="sidebar-context-card__body">
                                الأدوات التالية تعمل على هذا الحساب ما دامت الجلسة الحالية مرتبطة به.
                            </div>
                        @else
                            <div class="sidebar-context-card__title">لا يوجد حساب محدد</div>
                            <div class="sidebar-context-card__body">
                                @if($showTenantContext)
                                    اختر حسابًا عند الحاجة إلى إدارة مستخدمين أو أدوار أو تقارير تخص عميلًا محددًا.
                                @else
                                    سيظهر الحساب هنا عندما تكون مساحة العمل الحالية مرتبطة بسياق عميل محدد.
                                @endif
                            </div>
                        @endif
                    </div>
                @elseif(isset($item['divider']))
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
                        $iconSvg = isset($item['icon']) ? ($sidebarIconMap[$item['icon']] ?? null) : null;
                    @endphp
                    <a href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}" class="sidebar-item {{ $isActive ? 'active' : '' }}">
                        <span class="icon {{ $iconSvg ? 'icon-svg' : 'icon-text' }}" aria-hidden="true">
                            @if($iconSvg)
                                {!! $iconSvg !!}
                            @else
                                {{ $item['icon'] }}
                            @endif
                        </span>
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
                    <div class="topbar-greeting">مرحبًا، {{ $currentUser->name ?? 'مستخدم' }}</div>
                    <div class="topbar-subtitle">{{ $topbarSubtitle }}</div>
                </div>
                <div class="topbar-user">
                    @if($isInternalUser && $showTenantContext)
                        <a class="topbar-bell" href="{{ route($showAdminDashboard ? 'admin.tenant-context' : 'internal.tenant-context') }}" title="اختيار الحساب" aria-label="اختيار الحساب">
                            <span class="icon icon-svg" aria-hidden="true">{!! $sidebarIconMap['context-switch'] !!}</span>
                        </a>
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
