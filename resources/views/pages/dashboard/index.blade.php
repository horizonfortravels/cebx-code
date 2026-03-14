@extends('layouts.app')
@section('title', $portalType === 'b2c' ? 'الرئيسية' : 'لوحة التحكم')

@section('content')
{{-- ═══ HEADER ═══ --}}
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <div>
        <h1 style="font-size:24px;font-weight:800;color:var(--tx);margin:0">
            @if($portalType === 'admin')
                لوحة إدارة النظام
            @elseif($portalType === 'b2c')
                مرحباً {{ auth()->user()->name }} 👋
            @else
                لوحة التحكم
            @endif
        </h1>
        <p style="color:var(--td);font-size:14px;margin:6px 0 0">
            @if($portalType === 'admin')
                نظرة عامة على جميع عمليات المنصة
            @elseif($portalType === 'b2c')
                تتبع شحناتك وإدارة حسابك
            @else
                مرحباً {{ auth()->user()->name }}، إليك ملخص اليوم 👋
            @endif
        </p>
    </div>
    @if($portalType !== 'admin')
        <a href="{{ route('shipments.create') }}" class="btn btn-pr">📦 شحنة جديدة</a>
    @endif
</div>

{{-- ═══ ADMIN STATS ═══ --}}
@if($portalType === 'admin')
<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="📦" label="إجمالي الشحنات" :value="$todayShipments ?? 0" note="اليوم" :trend="($shipmentsTrend ?? 0) != 0 ? (($shipmentsTrend > 0 ? '+' : '') . $shipmentsTrend . '%') : null" :up="($shipmentsTrend ?? 0) > 0" />
    <x-stat-card icon="🏢" label="المنظمات" :value="$totalAccounts ?? 0" />
    <x-stat-card icon="👥" label="المستخدمين" :value="$totalUsers ?? 0" />
    <x-stat-card icon="💰" label="إجمالي الإيرادات" :value="'SAR ' . number_format($totalRevenue ?? 0)" />
    <x-stat-card icon="🛒" label="طلبات جديدة" :value="$newOrders ?? 0" />
    <x-stat-card icon="🎧" label="تذاكر مفتوحة" :value="$openTickets ?? 0" />
</div>

{{-- ═══ B2C STATS ═══ --}}
@elseif($portalType === 'b2c')
<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="📦" label="شحناتي النشطة" :value="$todayShipments ?? 0" />
    <x-stat-card icon="✅" label="تم التسليم" :value="$deliveredCount ?? 0" />
    <x-stat-card icon="💰" label="رصيد المحفظة" :value="'SAR ' . number_format($walletBalance ?? 0)" />
    <x-stat-card icon="📊" label="إجمالي الشحنات" :value="$totalShipments ?? 0" />
</div>

{{-- ═══ B2B STATS ═══ --}}
@else
<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="📦" label="شحنات اليوم" :value="$todayShipments ?? 0" :trend="($shipmentsTrend ?? 0) > 0 ? '+' . ($shipmentsTrend ?? 0) . '%' : null" :up="($shipmentsTrend ?? 0) > 0" />
    <x-stat-card icon="🛒" label="طلبات جديدة" :value="$newOrders ?? 0" />
    <x-stat-card icon="💰" label="الرصيد" :value="'SAR ' . number_format($walletBalance ?? 0)" />
    <x-stat-card icon="🏪" label="المتاجر" :value="$storesCount ?? 0" />
    <x-stat-card icon="⚠️" label="استثناءات" :value="$exceptions ?? 0" />
</div>
@endif

{{-- ═══ CHARTS ═══ --}}
<div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;margin-bottom:24px">
    <x-card title="{{ $portalType === 'admin' ? '📊 شحنات المنصة — آخر 6 أشهر' : '📊 أداء الشحنات' }}">
        @php $maxM = collect($monthlyData ?? [])->max('count') ?: 1; @endphp
        <div class="bar-chart" style="height:200px">
            @foreach($monthlyData ?? [] as $month)
                @php $barH = $maxM > 0 ? ($month['count'] / $maxM * 160) : 4; @endphp
                <div class="bar-col">
                    <span class="bar-label" style="font-size:10px;font-weight:600">{{ $month['count'] }}</span>
                    <div class="bar" style="height:{{ max($barH, 4) }}px;background:linear-gradient(180deg,var(--pr),rgba(59,130,246,0.15))"></div>
                    <span class="bar-label">{{ $month['name'] }}</span>
                </div>
            @endforeach
        </div>
    </x-card>

    <x-card title="📈 توزيع الحالات">
        @foreach($statusDistribution ?? [] as $sd)
            <div style="margin-bottom:16px">
                <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--td);margin-bottom:6px">
                    <span>{{ $sd['label'] }}</span><span>{{ $sd['pct'] }}%</span>
                </div>
                <div style="height:8px;background:var(--bg);border-radius:4px">
                    <div style="height:100%;width:{{ $sd['pct'] }}%;background:{{ $sd['color'] }};border-radius:4px;transition:width 1s ease"></div>
                </div>
            </div>
        @endforeach
    </x-card>
</div>

{{-- ═══ CARRIER STATS ═══ --}}
@if($portalType !== 'b2c' && !empty($carrierStats) && count($carrierStats) > 0)
<div style="margin-bottom:24px">
    <x-card title="🚚 توزيع الناقلين">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px">
            @foreach($carrierStats as $cs)
                <div style="text-align:center;padding:14px;background:var(--bg);border-radius:10px">
                    <div style="font-weight:700;font-size:20px;color:var(--pr)">{{ $cs['percent'] }}%</div>
                    <div style="font-size:12px;color:var(--td);margin-top:4px">{{ $cs['name'] }}</div>
                </div>
            @endforeach
        </div>
    </x-card>
</div>
@endif

{{-- ═══ QUICK ACTIONS ═══ --}}
<div class="grid-4" style="margin-bottom:24px">
    @php
        if ($portalType === 'admin') {
            $quickActions = [
                ['icon' => '🏢', 'label' => 'المنظمات', 'desc' => 'إدارة الحسابات', 'route' => 'organizations.index'],
                ['icon' => '📦', 'label' => 'الشحنات', 'desc' => 'جميع الشحنات', 'route' => 'shipments.index'],
                ['icon' => '🪪', 'label' => 'KYC', 'desc' => 'طلبات التحقق', 'route' => 'kyc.index'],
                ['icon' => '📜', 'label' => 'التدقيق', 'desc' => 'سجل العمليات', 'route' => 'audit.index'],
            ];
        } elseif ($portalType === 'b2c') {
            $quickActions = [
                ['icon' => '📦', 'label' => 'شحنة جديدة', 'desc' => 'إنشاء شحنة', 'route' => 'shipments.create'],
                ['icon' => '🔍', 'label' => 'تتبع شحنة', 'desc' => 'تتبع الحالة', 'route' => 'tracking.index'],
                ['icon' => '💳', 'label' => 'شحن الرصيد', 'desc' => 'إضافة رصيد', 'route' => 'wallet.index'],
                ['icon' => '📒', 'label' => 'العناوين', 'desc' => 'دفتر العناوين', 'route' => 'addresses.index'],
            ];
        } else {
            $quickActions = [
                ['icon' => '📦', 'label' => 'شحنة جديدة', 'desc' => 'إنشاء شحنة يدوياً', 'route' => 'shipments.create'],
                ['icon' => '🛒', 'label' => 'الطلبات', 'desc' => 'استيراد من المتاجر', 'route' => 'orders.index'],
                ['icon' => '💳', 'label' => 'شحن الرصيد', 'desc' => 'إضافة رصيد', 'route' => 'wallet.index'],
                ['icon' => '📊', 'label' => 'التقارير', 'desc' => 'عرض التحليلات', 'route' => 'reports.index'],
            ];
        }
    @endphp
    @foreach($quickActions as $action)
        <a href="{{ route($action['route']) }}" class="entity-card" style="text-align:center">
            <div style="font-size:32px;margin-bottom:10px">{{ $action['icon'] }}</div>
            <div style="font-weight:700;color:var(--tx);font-size:14px">{{ $action['label'] }}</div>
            <div style="color:var(--tm);font-size:12px;margin-top:4px">{{ $action['desc'] }}</div>
        </a>
    @endforeach
</div>

{{-- ═══ RECENT SHIPMENTS ═══ --}}
<x-card title="{{ $portalType === 'admin' ? '📦 آخر الشحنات في المنصة' : '📦 آخر الشحنات' }}">
    <x-slot:action>
        <a href="{{ route('shipments.index') }}" class="btn btn-s" style="font-size:12px">عرض الكل</a>
    </x-slot:action>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>رقم التتبع</th>
                    @if($portalType === 'admin')<th>الحساب</th>@endif
                    <th>المستلم</th>
                    <th>الناقل</th>
                    <th>الوجهة</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentShipments as $s)
                    <tr>
                        <td><a href="{{ route('shipments.show', $s) }}" class="td-link td-mono">{{ $s->reference_number }}</a></td>
                        @if($portalType === 'admin')
                            <td style="font-size:12px;color:var(--td)">{{ $s->account->name ?? '—' }}</td>
                        @endif
                        <td>{{ $s->recipient_name }}</td>
                        <td><span class="badge badge-in">{{ $s->carrier_name ?? '—' }}</span></td>
                        <td style="color:var(--td)">{{ $s->recipient_city }}</td>
                        <td><x-badge :status="$s->status" /></td>
                        <td style="font-size:12px;color:var(--tm)">{{ $s->created_at->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $portalType === 'admin' ? 7 : 6 }}" class="empty-state">لا توجد شحنات بعد</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
@endsection
