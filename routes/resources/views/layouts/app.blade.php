<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'بوابة إدارة الشحن') — CBEX Shipping Gateway</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    {{-- ═══ PWA Meta Tags ═══ --}}
    @include('components.pwa-meta')

    @stack('styles')
</head>
<body>
<div class="app-layout">
    {{-- ═══ SIDEBAR ═══ --}}
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="{{ asset('images/logo-sidebar.png') }}" alt="CBEX" class="sidebar-logo-img">
            <span class="sidebar-title">CBEX Gateway</span>
        </div>
        <nav class="sidebar-nav">
            @php
                $currentRoute = Route::currentRouteName() ?? '';
                $unreadNotifs = \App\Models\Notification::where('read_at', null)->count();
                $openTickets = \App\Models\SupportTicket::where('status', 'open')->count();
                $processingShipments = \App\Models\Shipment::whereIn('status', ['payment_pending', 'purchased', 'picked_up', 'in_transit', 'out_for_delivery'])->count();

                // Sidebar route names must exist in routes/web.php (auth + tenant middleware group)
                $menu = [
                    ['d' => true, 'label' => 'الرئيسية'],
                    ['id' => 'dashboard', 'route' => 'dashboard', 'icon' => '🏠', 'label' => 'لوحة التحكم'],
                    ['id' => 'shipments', 'route' => 'shipments.index', 'icon' => '📦', 'label' => 'الشحنات', 'badge' => $processingShipments],
                    ['id' => 'orders', 'route' => 'orders.index', 'icon' => '🛒', 'label' => 'الطلبات'],
                    ['id' => 'stores', 'route' => 'stores.index', 'icon' => '🏪', 'label' => 'المتاجر'],
                    ['id' => 'tracking', 'route' => 'tracking.index', 'icon' => '🚚', 'label' => 'التتبع'],
                    ['id' => 'pricing', 'route' => 'pricing.index', 'icon' => '🏷', 'label' => 'التسعير'],
                    ['d' => true, 'label' => 'المالية'],
                    ['id' => 'wallet', 'route' => 'wallet.index', 'icon' => '💰', 'label' => 'المحفظة'],
                    ['id' => 'financial', 'route' => 'financial.index', 'icon' => '📊', 'label' => 'المالية'],
                    ['d' => true, 'label' => 'الإدارة'],
                    ['id' => 'users', 'route' => 'users.index', 'icon' => '👥', 'label' => 'المستخدمين'],
                    ['id' => 'roles', 'route' => 'roles.index', 'icon' => '🛡', 'label' => 'الأدوار'],
                    ['id' => 'invitations', 'route' => 'invitations.index', 'icon' => '📧', 'label' => 'الدعوات'],
                    ['id' => 'organizations', 'route' => 'organizations.index', 'icon' => '🏢', 'label' => 'المنظمات'],
                    ['d' => true, 'label' => 'النظام'],
                    ['id' => 'notifications', 'route' => 'notifications.index', 'icon' => '🔔', 'label' => 'الإشعارات', 'badge' => $unreadNotifs],
                    ['id' => 'reports', 'route' => 'reports.index', 'icon' => '📈', 'label' => 'التقارير'],
                    ['id' => 'audit', 'route' => 'audit.index', 'icon' => '📋', 'label' => 'التدقيق'],
                    ['id' => 'kyc', 'route' => 'kyc.index', 'icon' => '✅', 'label' => 'KYC'],
                    ['id' => 'dg', 'route' => 'dg.index', 'icon' => '⚠', 'label' => 'DG'],
                    ['id' => 'support', 'route' => 'support.index', 'icon' => '🎧', 'label' => 'الدعم', 'badge' => $openTickets],
                    ['id' => 'addresses', 'route' => 'addresses.index', 'icon' => '📍', 'label' => 'العناوين'],
                    ['id' => 'settings', 'route' => 'settings.index', 'icon' => '⚙', 'label' => 'الإعدادات'],
                    ['id' => 'admin', 'route' => 'admin.index', 'icon' => '🔑', 'label' => 'الإدارة'],
                    ['d' => true, 'label' => 'Phase 2'],
                    ['id' => 'containers', 'route' => 'containers.index', 'icon' => '📦', 'label' => 'الحاويات'],
                    ['id' => 'customs', 'route' => 'customs.index', 'icon' => '📄', 'label' => 'الجمارك'],
                    ['id' => 'drivers', 'route' => 'drivers.index', 'icon' => '🚗', 'label' => 'السائقين'],
                    ['id' => 'claims', 'route' => 'claims.index', 'icon' => '⚡', 'label' => 'المطالبات'],
                    ['id' => 'risk', 'route' => 'risk.index', 'icon' => '🛡', 'label' => 'المخاطر'],
                    ['id' => 'vessels', 'route' => 'vessels.index', 'icon' => '⚓', 'label' => 'السفن'],
                    ['id' => 'schedules', 'route' => 'schedules.index', 'icon' => '📅', 'label' => 'الجداول'],
                    ['id' => 'branches', 'route' => 'branches.index', 'icon' => '🏛', 'label' => 'الفروع'],
                    ['id' => 'companies', 'route' => 'companies.index', 'icon' => '🌐', 'label' => 'الشركات'],
                    ['id' => 'hscodes', 'route' => 'hscodes.index', 'icon' => '#️⃣', 'label' => 'HS أكواد'],
                ];
            @endphp

            @foreach($menu as $item)
                @if(isset($item['d']))
                    <div class="sidebar-divider">{{ $item['label'] }}</div>
                @else
                    @php
                        $isActive = str_starts_with($currentRoute, $item['id']);
                        // Use web route only: relative path so session/cookie same-origin (avoid redirect to login)
                        $url = \Illuminate\Support\Facades\Route::has($item['route'])
                            ? (\Illuminate\Support\Str::startsWith($item['route'] ?? '', 'api.') ? '#' : route($item['route'], [], false))
                            : '#';
                    @endphp
                    <a href="{{ $url }}"
                       class="sidebar-item {{ $isActive ? 'active' : '' }}"
                       @if($url === '#') title="{{ __('Route not registered: ') }}{{ $item['route'] }}" @endif>
                        <span class="icon">{{ $item['icon'] }}</span>
                        <span>{{ $item['label'] }}</span>
                        @if(isset($item['badge']) && $item['badge'] > 0)
                            <span class="badge-count">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endif
            @endforeach
        </nav>
        <div class="sidebar-footer">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit">🚪 <span>تسجيل الخروج</span></button>
            </form>
        </div>
    </aside>

    {{-- ═══ MAIN AREA ═══ --}}
    <div class="main-area">
        <header class="topbar">
            <div style="color: var(--tm); font-size: 11px;">
                مرحباً، {{ auth()->user()->name ?? 'مستخدم' }}
            </div>
            <div class="topbar-user">
                <button class="topbar-bell" onclick="window.location='/notifications'">
                    🔔
                    @if(($unreadNotifs ?? 0) > 0) <span class="dot"></span> @endif
                </button>
                <div class="topbar-avatar">{{ mb_substr(auth()->user()->name ?? 'م', 0, 1) }}</div>
            </div>
        </header>

        <main class="content">
            @if(session('success'))
                <x-toast type="success" :message="session('success')" />
            @endif
            @if(session('error'))
                <x-toast type="error" :message="session('error')" />
            @endif
            @yield('content')
        </main>
    </div>
</div>

{{-- ═══ PWA Registration ═══ --}}
<script src="{{ asset('js/pwa.js') }}"></script>
@stack('scripts')
</body>
</html>
