@extends('layouts.app')
@section('title', 'لوحة الإدارة العامة')

@section('content')
<div style="margin-bottom:24px">
    <div style="font-size:12px;color:var(--tm);margin-bottom:8px">الإدارة الداخلية / لوحة الإدارة</div>
    <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">لوحة الإدارة العامة</h1>
    <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:820px">
        هذه هي نقطة البداية الداخلية للمستخدمين ذوي صلاحية الإدارة. يمكنك منها متابعة حالة المنصة، ثم اختيار حساب عميل عند الحاجة للانتقال إلى صفحات المستخدمين أو الأدوار أو التقارير.
    </p>
</div>

@if($selectedAccount)
    <div class="card" style="margin-bottom:20px;background:rgba(15,23,42,.03)">
        <div class="card-title">الحساب المحدد حاليًا</div>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
            <div>
                <div style="font-weight:700;color:var(--tx)">{{ $selectedAccount->name }}</div>
                <div style="font-size:13px;color:var(--td)">{{ $selectedAccount->type === 'organization' ? 'منظمة' : 'فردي' }}</div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="{{ route('admin.users') }}" class="btn btn-s">مستخدمو الحساب</a>
                <a href="{{ route('admin.roles') }}" class="btn btn-ghost">أدوار الحساب</a>
                <a href="{{ route('admin.reports') }}" class="btn btn-ghost">تقارير الحساب</a>
            </div>
        </div>
    </div>
@else
    <div class="card" style="margin-bottom:20px;border:1px dashed var(--bd)">
        <div class="card-title">لا يوجد حساب محدد بعد</div>
        <p style="color:var(--td);margin:0 0 12px">يمكنك تصفح حالة المنصة الآن، لكن الصفحات المرتبطة بعميل محدد ستطلب منك اختيار الحساب أولًا.</p>
        <a href="{{ route('admin.tenant-context') }}" class="btn btn-s">اختيار حساب للتصفح</a>
    </div>
@endif

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="🏢" label="المنظمات" :value="$orgCount ?? 0" />
    <x-stat-card icon="👥" label="المستخدمون" :value="$usersCount ?? 0" />
    <x-stat-card icon="📦" label="إجمالي الشحنات" :value="number_format($totalShipments ?? 0)" />
    <x-stat-card icon="✅" label="حالة النظام" value="متصل" />
</div>

<div class="grid-4" style="margin-bottom:28px">
    @foreach([
        ['icon' => 'CTX', 'label' => 'اختيار الحساب', 'desc' => 'تحديد العميل المستهدف للتصفح الداخلي قبل فتح الصفحات المرتبطة بحساب محدد.', 'route' => 'admin.tenant-context'],
        ['icon' => 'USR', 'label' => 'مستخدمو الحساب', 'desc' => 'عرض قائمة المستخدمين الحالية للحساب المحدد ومراجعة حالاتهم.', 'route' => 'admin.users'],
        ['icon' => 'ROL', 'label' => 'أدوار الحساب', 'desc' => 'مراجعة الأدوار والصلاحيات النشطة داخل الحساب المحدد.', 'route' => 'admin.roles'],
        ['icon' => 'RPT', 'label' => 'تقارير الحساب', 'desc' => 'ملخص تشغيلي سريع للحساب المحدد قبل الانتقال إلى التقارير التفصيلية.', 'route' => 'admin.reports'],
    ] as $item)
        <a href="{{ route($item['route']) }}" class="entity-card" style="text-align:center;text-decoration:none;cursor:pointer">
            <div style="font-size:24px;font-weight:800;margin-bottom:10px;color:#2563eb">{{ $item['icon'] }}</div>
            <div style="font-weight:700;color:var(--tx);font-size:14px">{{ $item['label'] }}</div>
            <div style="color:var(--td);font-size:12px;margin-top:4px">{{ $item['desc'] }}</div>
        </a>
    @endforeach
</div>

<x-card title="حالة النظام">
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px">
        @foreach($systemHealth as $service)
            @php $isOk = $service['status'] === 'ok'; @endphp
            <div style="padding:16px;background:var(--sf);border-radius:12px;border:1px solid var(--bd)">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <span style="font-weight:600;font-size:13px;color:var(--tx)">{{ $service['name'] }}</span>
                    <span style="width:10px;height:10px;border-radius:50%;background:{{ $isOk ? 'var(--ac)' : 'var(--dg)' }}"></span>
                </div>
                <div style="font-size:12px;color:var(--td)">
                    الحالة:
                    <span style="color:{{ $isOk ? 'var(--ac)' : 'var(--dg)' }}">{{ $isOk ? 'متصل' : 'غير متصل' }}</span>
                    &nbsp;•&nbsp; {{ $service['latency'] }}
                </div>
            </div>
        @endforeach
    </div>
</x-card>
@endsection
